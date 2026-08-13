-- =====================================================================
-- goldshop — GOLD & SILVER SHOP ERP
-- Database Schema (MySQL 8+)
-- Built directly from PRD v4 (+ Admin Panel, Labour Cost, Buy-Back fixes)
-- =====================================================================
-- Design notes (read before extending):
--
-- 1. METAL + PURITY are always separate: every rate, every item, every
--    transaction carries a metal_type_id AND a purity_id. Purities
--    belong to exactly one metal (see `purities.metal_type_id`).
--
-- 2. PARTY + TRANSACTION model (PRD §4.5): every counterparty (customer,
--    karigar, wholesaler, other shop, company) is a row in `parties`.
--    Every gold/silver movement, no matter who it's with, is one row in
--    `transactions`. Ledger balance for any party = SUM(IN) - SUM(OUT)
--    on their transactions — there is no separate ledger table per type.
--
-- 3. BUY-BACK, NOT "RETURNS" (PRD §4.4 — corrected from an earlier
--    draft): a customer bringing gold back is a fresh `transaction_type
--    = 'buy_back'` transaction, valued at TODAY's rate with a deduction
--    applied (see `buyback_deduction_settings`). It NEVER copies the
--    original sale's price or weight. `original_sale_transaction_id`
--    is kept only for staff reference / reporting, never for valuation.
--
-- 4. METAL COST vs LABOUR COST are always stored as separate columns,
--    never pre-summed (PRD §4.9, guardrail G-6) — this is what makes
--    the itemized invoice breakdown possible.
--
-- 5. Every manual price override and weight edit is written to
--    `audit_log`, storing old value, new value, who, when, and why
--    (PRD §4.8/§4.7, guardrail G-2).
--
-- 6. Weights are always stored in GRAMS internally. `weight_units` is a
--    reference/conversion table only — used by the UI layer to accept
--    input in tola/masha/ratti and convert before saving.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================================
-- 1. USERS & ROLES
-- =====================================================================

CREATE TABLE users (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(120)        NOT NULL,
    username        VARCHAR(60)         NOT NULL UNIQUE,
    password_hash   VARCHAR(255)        NOT NULL,
    role            ENUM('admin','shopkeeper') NOT NULL DEFAULT 'shopkeeper',
    is_active       TINYINT(1)          NOT NULL DEFAULT 1,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================================
-- 2. METAL, PURITY, WEIGHT UNITS  (PRD §4.1, §6, §9)
-- =====================================================================

CREATE TABLE metal_types (
    id              TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(30)         NOT NULL UNIQUE          -- 'Gold', 'Silver'
) ENGINE=InnoDB;

CREATE TABLE purities (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    metal_type_id   TINYINT UNSIGNED    NOT NULL,
    name            VARCHAR(30)         NOT NULL,                -- '24K', '22K', 'Sterling (925)'
    fineness_percent DECIMAL(5,2)       NOT NULL,                -- 99.90, 91.60, 92.50 ...
    is_active       TINYINT(1)          NOT NULL DEFAULT 1,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_purity_per_metal (metal_type_id, name),
    CONSTRAINT fk_purity_metal FOREIGN KEY (metal_type_id) REFERENCES metal_types(id)
) ENGINE=InnoDB;

CREATE TABLE weight_units (
    id              TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(20)         NOT NULL UNIQUE,         -- 'gram','tola','masha','ratti','point'
    grams_per_unit  DECIMAL(12,6)       NOT NULL                 -- conversion factor to grams
) ENGINE=InnoDB;

-- =====================================================================
-- 3. DAILY RATES  (PRD §4.1 — FR-1..FR-4, rate API + offline cache)
-- =====================================================================
-- Rates are NOT limited to one fetch a day. The API can be checked once
-- in the morning, again mid-day, hourly, or on manual "Refresh" clicks —
-- every fetch is stored as its own row, timestamped. "Today's rate" is
-- always the latest row (by fetched_at) with is_current = 1 for that
-- metal+purity — never a fixed once-a-day value.
--
-- API prices (spot/international, converted to PKR/gram) rarely match
-- what the local market actually charges — shops commonly add their own
-- premium on top. `rate_adjustment_settings` lets Admin configure that
-- premium once (shop-wide, or per metal/purity, same override pattern
-- as buyback_deduction_settings below) and every fetch applies it
-- automatically. Admin can still hand-edit the final rate on any
-- specific fetch if needed — that edit is logged in audit_log.

CREATE TABLE rate_adjustment_settings (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    metal_type_id       TINYINT UNSIGNED NULL,                   -- NULL = shop-wide default row
    purity_id           INT UNSIGNED    NULL,                    -- NULL = applies to whole metal, or default
    adjustment_type      ENUM('amount','percent') NOT NULL DEFAULT 'amount',
    adjustment_value       DECIMAL(10,4) NOT NULL DEFAULT 0,      -- e.g. +50.00 PKR/gram, or +1.5 (%)
    is_shop_default      TINYINT(1)     NOT NULL DEFAULT 0,       -- exactly one row should be 1
    updated_by_user_id     BIGINT UNSIGNED NULL,
    updated_at               DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_radj_metal FOREIGN KEY (metal_type_id) REFERENCES metal_types(id),
    CONSTRAINT fk_radj_purity FOREIGN KEY (purity_id) REFERENCES purities(id),
    CONSTRAINT fk_radj_user FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB;
-- Same resolution order as buyback_deduction_settings:
--   1. exact match on (metal_type_id, purity_id)
--   2. match on (metal_type_id, purity_id IS NULL)  -- whole-metal override
--   3. the row where is_shop_default = 1             -- shop-wide fallback

CREATE TABLE daily_rates (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    metal_type_id        TINYINT UNSIGNED NOT NULL,
    purity_id             INT UNSIGNED   NOT NULL,
    api_raw_rate_per_gram   DECIMAL(12,2) NULL,                  -- fetched value, PKR/gram, before adjustment; NULL if manual entry
    adjustment_type_used     ENUM('amount','percent') NULL,      -- snapshot of setting applied at fetch time
    adjustment_value_used      DECIMAL(10,4) NULL,                -- snapshot, so later setting changes don't rewrite history
    rate_per_gram                 DECIMAL(12,2) NOT NULL,        -- FINAL rate actually used (raw + adjustment, or manual)
    rate_date                       DATE       NOT NULL,
    source                            ENUM('api','manual') NOT NULL DEFAULT 'api',
    fetched_at                         DATETIME NULL,             -- exact time of this fetch/entry — supports many per day
    is_current                          TINYINT(1) NOT NULL DEFAULT 1, -- 1 = this is the active rate for this metal+purity right now
    is_stale                             TINYINT(1) NOT NULL DEFAULT 0, -- 1 if API failed and this row is a carried-over cache
    entered_by_user_id                    BIGINT UNSIGNED NULL,   -- set when source='manual' or a fetched rate was hand-overridden
    created_at                              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rate_metal FOREIGN KEY (metal_type_id) REFERENCES metal_types(id),
    CONSTRAINT fk_rate_purity FOREIGN KEY (purity_id) REFERENCES purities(id),
    CONSTRAINT fk_rate_user FOREIGN KEY (entered_by_user_id) REFERENCES users(id),
    INDEX idx_rate_lookup (metal_type_id, purity_id, is_current),
    INDEX idx_rate_history (metal_type_id, purity_id, fetched_at)
) ENGINE=InnoDB;
-- "Today's rate" query: SELECT rate_per_gram FROM daily_rates
--   WHERE metal_type_id=? AND purity_id=? AND is_current=1 LIMIT 1
-- On every new fetch/entry: set is_current=0 on the previous current row
-- for that metal+purity, then insert the new row with is_current=1.
-- Full history (every fetch, every day) stays in the table for rate
-- history reports (PRD §4.7) and for valuing past transactions correctly.

-- =====================================================================
-- 4. BUY-BACK DEDUCTION SETTINGS  (PRD §4.8 FR-24a)
-- =====================================================================

CREATE TABLE buyback_deduction_settings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    metal_type_id   TINYINT UNSIGNED    NULL,                    -- NULL = shop-wide default row
    purity_id       INT UNSIGNED        NULL,                    -- NULL = applies to whole metal, or default
    deduction_percent DECIMAL(5,2)      NOT NULL,                -- e.g. 2.50
    is_shop_default TINYINT(1)          NOT NULL DEFAULT 0,      -- exactly one row should be 1
    updated_by_user_id BIGINT UNSIGNED  NULL,
    updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_bbd_metal FOREIGN KEY (metal_type_id) REFERENCES metal_types(id),
    CONSTRAINT fk_bbd_purity FOREIGN KEY (purity_id) REFERENCES purities(id),
    CONSTRAINT fk_bbd_user FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB;
-- Resolution order when valuing a buy-back for a given metal+purity:
--   1. exact match on (metal_type_id, purity_id)
--   2. match on (metal_type_id, purity_id IS NULL)  -- whole-metal override
--   3. the row where is_shop_default = 1             -- shop-wide fallback

-- =====================================================================
-- 5. PARTIES  (PRD §4.5 — FR-14)
-- =====================================================================

CREATE TABLE party_types (
    id              TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(30)         NOT NULL UNIQUE          -- Customer, Karigar, Wholesaler, Other Shop, Company
) ENGINE=InnoDB;

CREATE TABLE parties (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    party_type_id   TINYINT UNSIGNED    NOT NULL,
    name            VARCHAR(150)        NOT NULL,
    phone           VARCHAR(30)         NULL,
    address         VARCHAR(255)        NULL,
    notes           TEXT                NULL,
    is_active       TINYINT(1)          NOT NULL DEFAULT 1,
    created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_party_type FOREIGN KEY (party_type_id) REFERENCES party_types(id),
    INDEX idx_party_name (name)
) ENGINE=InnoDB;

-- =====================================================================
-- 6. ITEMS  (PRD §4.2 — item entry & tagging, §4.9 labour cost)
-- =====================================================================

CREATE TABLE items (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_code           VARCHAR(30)     NOT NULL UNIQUE,         -- printed on tag, e.g. 'GRN-2291'
    qr_payload          VARCHAR(255)    NULL,                    -- data encoded in the tag's QR
    item_type           VARCHAR(60)     NOT NULL,                -- Ring, Bangle, Chain, Custom...
    metal_type_id       TINYINT UNSIGNED NOT NULL,
    purity_id           INT UNSIGNED    NOT NULL,
    gross_weight_grams  DECIMAL(10,3)   NOT NULL,
    stone_weight_grams  DECIMAL(10,3)   NOT NULL DEFAULT 0,       -- non-gold material (stones), subtracted from gross
    cutting_loss_grams  DECIMAL(10,3)   NOT NULL DEFAULT 0,       -- actual GOLD/SILVER lost during cutting/shaping (wastage) — distinct from stone_weight, which is not metal at all
    net_weight_grams    DECIMAL(10,3)   AS (gross_weight_grams - stone_weight_grams - cutting_loss_grams) STORED,
    labour_cost         DECIMAL(12,2)   NOT NULL DEFAULT 0,      -- carving/craftsmanship cost, kept separate (G-6)
    polish_cost         DECIMAL(12,2)   NOT NULL DEFAULT 0,      -- polishing/finishing cost, kept separate from labour_cost and metal cost (G-6)
    purchase_rate_per_gram DECIMAL(12,2) NOT NULL,               -- rate on the day it was purchased
    purchase_price       DECIMAL(12,2)  NOT NULL,                -- (net_weight * purchase_rate) + labour_cost + polish_cost, locked
    source_party_id      BIGINT UNSIGNED NOT NULL,               -- karigar / wholesaler / other shop / company
    date_received         DATE          NOT NULL,
    status                ENUM('in_stock','sold','bought_back') NOT NULL DEFAULT 'in_stock',
    created_by_user_id    BIGINT UNSIGNED NULL,
    created_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_item_metal FOREIGN KEY (metal_type_id) REFERENCES metal_types(id),
    CONSTRAINT fk_item_purity FOREIGN KEY (purity_id) REFERENCES purities(id),
    CONSTRAINT fk_item_source_party FOREIGN KEY (source_party_id) REFERENCES parties(id),
    CONSTRAINT fk_item_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id),
    INDEX idx_item_status (status),
    INDEX idx_item_metal_purity (metal_type_id, purity_id)
) ENGINE=InnoDB;

-- =====================================================================
-- 7. TRANSACTIONS  (PRD §4.5 — FR-15, the spine of the whole system)
-- =====================================================================

CREATE TABLE transaction_types (
    id              TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(40)         NOT NULL UNIQUE
    -- seed values: 'sale', 'purchase', 'old_gold_exchange', 'buy_back',
    -- 'karigar_issue', 'karigar_return', 'shop_transfer', 'wholesale_purchase'
) ENGINE=InnoDB;

CREATE TABLE transactions (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    party_id                BIGINT UNSIGNED NOT NULL,
    transaction_type_id     TINYINT UNSIGNED NOT NULL,
    direction                ENUM('IN','OUT') NOT NULL,          -- IN = shop receives, OUT = shop gives
    item_id                  BIGINT UNSIGNED NULL,               -- linked physical item, if applicable
    metal_type_id            TINYINT UNSIGNED NOT NULL,
    purity_id                INT UNSIGNED    NOT NULL,
    weight_grams              DECIMAL(10,3)  NOT NULL,
    rate_per_gram              DECIMAL(12,2) NOT NULL,           -- rate actually used for this transaction
    metal_cost                 DECIMAL(12,2) NOT NULL,           -- weight * rate, stored separately (G-6)
    labour_cost                 DECIMAL(12,2) NOT NULL DEFAULT 0,
    polish_cost                  DECIMAL(12,2) NOT NULL DEFAULT 0, -- kept separate from labour_cost and metal_cost (G-6)
    tax_amount                   DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_amount               DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_reason                 VARCHAR(255) NULL,           -- required if discount_amount > 0
    deduction_percent_applied         DECIMAL(5,2) NULL,         -- for buy_back / old_gold_exchange rows
    total_amount                       DECIMAL(12,2) NOT NULL,   -- final settled value of this transaction line
    original_sale_transaction_id        BIGINT UNSIGNED NULL,    -- reference-only link for buy-backs (PRD §4.4 FR-13); never used to derive value
    invoice_id                           BIGINT UNSIGNED NULL,   -- set when part of a customer sale (see §8)
    payment_method                        ENUM('cash','card','bank_transfer','credit','na') NOT NULL DEFAULT 'na',
    transaction_date                       DATE       NOT NULL,
    notes                                    TEXT      NULL,
    created_by_user_id                       BIGINT UNSIGNED NULL,
    created_at                                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_txn_party FOREIGN KEY (party_id) REFERENCES parties(id),
    CONSTRAINT fk_txn_type FOREIGN KEY (transaction_type_id) REFERENCES transaction_types(id),
    CONSTRAINT fk_txn_item FOREIGN KEY (item_id) REFERENCES items(id),
    CONSTRAINT fk_txn_metal FOREIGN KEY (metal_type_id) REFERENCES metal_types(id),
    CONSTRAINT fk_txn_purity FOREIGN KEY (purity_id) REFERENCES purities(id),
    CONSTRAINT fk_txn_original_sale FOREIGN KEY (original_sale_transaction_id) REFERENCES transactions(id),
    CONSTRAINT fk_txn_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id),
    INDEX idx_txn_party (party_id),
    INDEX idx_txn_date (transaction_date),
    INDEX idx_txn_type_direction (transaction_type_id, direction)
) ENGINE=InnoDB;
-- Party ledger balance = SUM(CASE WHEN direction='IN' THEN total_amount ELSE -total_amount END)
--                         over all transactions for that party_id.
-- This single table powers customer sales, karigar issue/return, wholesaler
-- purchases, shop-to-shop trades, old-gold exchange, and buy-backs alike.

-- =====================================================================
-- 8. INVOICES  (PRD §4.9 — itemized metal/labour breakdown)
-- =====================================================================

CREATE TABLE invoices (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number       VARCHAR(30)     NOT NULL UNIQUE,
    party_id              BIGINT UNSIGNED NOT NULL,              -- customer
    invoice_date            DATE         NOT NULL,
    total_metal_cost          DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_labour_cost           DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_polish_cost             DECIMAL(12,2) NOT NULL DEFAULT 0, -- kept separate from labour_cost (G-6)
    total_tax                     DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_discount                  DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_exchange_deduction          DECIMAL(12,2) NOT NULL DEFAULT 0, -- old gold exchanged within this bill
    grand_total                         DECIMAL(12,2) NOT NULL,
    payment_method                        ENUM('cash','card','bank_transfer','credit') NOT NULL DEFAULT 'cash',
    created_by_user_id                     BIGINT UNSIGNED NULL,
    created_at                              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_invoice_party FOREIGN KEY (party_id) REFERENCES parties(id),
    CONSTRAINT fk_invoice_user FOREIGN KEY (created_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE invoice_line_items (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id      BIGINT UNSIGNED NOT NULL,
    item_id         BIGINT UNSIGNED NOT NULL,
    transaction_id   BIGINT UNSIGNED NOT NULL,                   -- the 'sale' transaction this line settled
    weight_grams      DECIMAL(10,3) NOT NULL,
    purity_id           INT UNSIGNED NOT NULL,
    rate_per_gram         DECIMAL(12,2) NOT NULL,
    metal_cost              DECIMAL(12,2) NOT NULL,               -- stored separately from labour/polish (G-6)
    labour_cost               DECIMAL(12,2) NOT NULL DEFAULT 0,
    polish_cost                 DECIMAL(12,2) NOT NULL DEFAULT 0, -- kept separate from labour_cost (G-6)
    line_total                  DECIMAL(12,2) NOT NULL,
    CONSTRAINT fk_line_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id),
    CONSTRAINT fk_line_item FOREIGN KEY (item_id) REFERENCES items(id),
    CONSTRAINT fk_line_txn FOREIGN KEY (transaction_id) REFERENCES transactions(id),
    CONSTRAINT fk_line_purity FOREIGN KEY (purity_id) REFERENCES purities(id)
) ENGINE=InnoDB;

ALTER TABLE transactions
    ADD CONSTRAINT fk_txn_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id);

-- =====================================================================
-- 9. AUDIT LOG  (PRD §4.7 FR-21, §4.8 FR-22/23 — guardrail G-2)
-- =====================================================================

CREATE TABLE audit_log (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type     ENUM('item_price','sale_line_price','rate','purity','buyback_deduction','transaction_weight','discount') NOT NULL,
    entity_id       BIGINT UNSIGNED NOT NULL,                    -- id in the relevant table above
    field_name      VARCHAR(60)     NOT NULL,
    old_value       VARCHAR(255)    NULL,
    new_value       VARCHAR(255)    NOT NULL,
    reason          VARCHAR(255)    NOT NULL,                    -- required for every override (G-2)
    changed_by_user_id BIGINT UNSIGNED NOT NULL,
    changed_at       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (changed_by_user_id) REFERENCES users(id),
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_date (changed_at)
) ENGINE=InnoDB;

-- =====================================================================
-- 10. ZAKAT  (PRD §4.6 — FR-17, FR-18)
-- =====================================================================

CREATE TABLE zakat_inputs (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cash_in_hand         DECIMAL(14,2) NOT NULL DEFAULT 0,
    liabilities_owed       DECIMAL(14,2) NOT NULL DEFAULT 0,
    nisab_gold_grams         DECIMAL(8,3) NOT NULL DEFAULT 87.480,
    nisab_silver_grams         DECIMAL(8,3) NOT NULL DEFAULT 612.360,
    updated_by_user_id           BIGINT UNSIGNED NULL,
    updated_at                     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_zakat_user FOREIGN KEY (updated_by_user_id) REFERENCES users(id)
) ENGINE=InnoDB;
-- Zakat report is always computed live (never cached):
--   stock_value(metal) = SUM(items.net_weight_grams * today's rate)
--                          WHERE items.status='in_stock' AND items.metal_type_id = metal
--   zakat_due(metal) = 0.025 * (stock_value(metal) + cash_in_hand - liabilities_owed)
--                        IF stock_value(metal) >= nisab_value(metal) ELSE 0

-- =====================================================================
-- 11. RATE API FETCH LOG  (PRD §4.1 FR-3/FR-4 — offline resilience)
-- =====================================================================

CREATE TABLE rate_fetch_log (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attempted_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    success         TINYINT(1)      NOT NULL,
    source_api      VARCHAR(60)     NOT NULL DEFAULT 'gold-api.com',
    response_summary VARCHAR(255)   NULL,
    fallback_used   TINYINT(1)      NOT NULL DEFAULT 0            -- 1 if cached rate was used instead
) ENGINE=InnoDB;

-- =====================================================================
-- SEED DATA — starting reference values (Admin can edit all of these)
-- =====================================================================

INSERT INTO metal_types (name) VALUES ('Gold'), ('Silver');

INSERT INTO purities (metal_type_id, name, fineness_percent) VALUES
    (1, '24K', 99.90),
    (1, '22K', 91.60),
    (1, '21K', 87.50),
    (1, '18K', 75.00),
    (2, 'Fine Silver (999)', 99.90),
    (2, 'Sterling Silver (925)', 92.50),
    (2, 'Coin Silver (900)', 90.00),
    (2, '835 Silver', 83.50);

INSERT INTO weight_units (name, grams_per_unit) VALUES
    ('gram', 1.000000),
    ('tola', 11.663800),
    ('masha', 0.971983),      -- 1 tola / 12
    ('ratti', 0.121498),      -- 1 masha / 8
    ('point', 0.010000);      -- ASSUMPTION, not yet confirmed with shopkeeper: 1 point = 0.01 gram (PRD Open Q7). Update this row once confirmed.

INSERT INTO party_types (name) VALUES
    ('Customer'), ('Karigar'), ('Wholesaler'), ('Other Shop'), ('Company');

INSERT INTO transaction_types (name) VALUES
    ('sale'), ('purchase'), ('old_gold_exchange'), ('buy_back'),
    ('karigar_issue'), ('karigar_return'), ('shop_transfer'), ('wholesale_purchase');

INSERT INTO buyback_deduction_settings (metal_type_id, purity_id, deduction_percent, is_shop_default)
    VALUES (NULL, NULL, 2.50, 1);   -- shop-wide default; Admin can add per metal/purity overrides

INSERT INTO rate_adjustment_settings (metal_type_id, purity_id, adjustment_type, adjustment_value, is_shop_default)
    VALUES (NULL, NULL, 'amount', 0.00, 1);   -- shop-wide default; Admin sets the real premium, and can override per metal/purity

INSERT INTO zakat_inputs (cash_in_hand, liabilities_owed) VALUES (0, 0);

SET FOREIGN_KEY_CHECKS = 1;
