export interface CartItem {
    id: number;
    item_code: string;
    item_type: string;
    metal_name: string;
    purity_name: string;
    gross_weight_grams: number;
    stone_weight_grams: number;
    cutting_loss_grams: number;
    net_weight_grams: number;
    labour_cost: number;
    polish_cost: number;
    // The live rate for this item's own purity, detected the moment it was
    // added to the cart — kept so an edit can be told apart from the system
    // value and reset back to it.
    detected_rate_per_gram: number;
    // The rate actually used for pricing — starts equal to detected_rate_per_gram,
    // editable per line (e.g. a negotiated price), audited server-side if changed.
    rate_per_gram: number;
}

export interface ExchangeItem {
    id: string; // Temporary UUID for UI
    metal_type_id: number;
    purity_id: number;
    metal_name: string;
    purity_name: string;
    purity_fraction: number;
    weight_grams: number;
    deduction_percent: number;
    net_weight_grams: number;
    rate_per_gram: number;
    valuation: number;
}

export interface CartState {
    items: CartItem[];
    exchanges: ExchangeItem[];
    subtotal: number; // Before discount
    discount: number;
    discountType: 'flat' | 'percentage';
    discount_reason?: string;
    // Sales tax (Admin → Settings) — a client-side preview only; the server
    // recomputes it authoritatively from the same setting when the sale posts.
    taxEnabled: boolean;
    taxRatePercent: number;
    taxAmount: number;
    total: number; // subtotal - discount + tax - exchanges
    customer_name: string;
    customer_phone: string;
    paymentMethod: 'Cash' | 'Card' | 'Transfer' | 'Bank QR';
}

export type CartAction =
    | { type: 'ADD_ITEM'; payload: CartItem }
    | { type: 'REMOVE_ITEM'; payload: { id: number } }
    | { type: 'UPDATE_ITEM_RATE'; payload: { id: number; rate_per_gram: number } }
    | { type: 'ADD_EXCHANGE'; payload: ExchangeItem }
    | { type: 'REMOVE_EXCHANGE'; payload: { id: string } }
    | { type: 'SET_DISCOUNT'; payload: { value: number; type: 'flat' | 'percentage' } }
    | { type: 'SET_DISCOUNT_REASON'; payload: string }
    | { type: 'SET_CUSTOMER'; payload: { name: string; phone: string } }
    | { type: 'SET_PAYMENT_METHOD'; payload: 'Cash' | 'Card' | 'Transfer' | 'Bank QR' }
    | { type: 'SET_TAX_CONFIG'; payload: { enabled: boolean; ratePercent: number } }
    | { type: 'CLEAR_CART' };
