import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import '../../../../css/admin.css';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Settings', href: '/admin/settings' },
];

interface MetalRow {
    id: number;
    name: string;
    is_active: boolean;
}

interface ItemTypeCostDefaultRow {
    item_type: string;
    labour_cost_default: number;
}

const ITEM_TYPE_LABELS: Record<string, string> = {
    ring: 'Ring',
    bangle: 'Bangle',
    necklace: 'Necklace',
    earring: 'Earring',
    bracelet: 'Bracelet',
    chain: 'Chain',
    pendant: 'Pendant',
    locket: 'Locket',
    nose_pin: 'Nose Pin',
    tops: 'Tops',
    other: 'Other Jewelry',
    biscuit: 'Biscuit',
    bar: 'Bar',
    nugget: 'Nugget',
    coin: 'Coin',
    piece: 'Raw Gold / Lagdi',
};

interface Settings {
    shop_name: string;
    shop_tagline: string;
    shop_address: string;
    shop_phone: string;
    shop_ntn: string | null;
    shop_logo: string | null;
    receipt_printer_type: 'regular' | 'thermal';
    item_slip_print_width_mm: number;
    item_slip_print_height_mm: number;
    item_slip_print_padding_mm: number;
    buyback_enabled: boolean;
    bank_qr_enabled: boolean;
    bank_qr_manager_confirm_enabled: boolean;
    exchange_enabled: boolean;
    rate_auto_refresh_enabled: boolean;
    tax_enabled: boolean;
    tax_rate_percent: number;
    zakat_enabled: boolean;
    zakat_rate_percent: number;
    zakat_nisab_gold_grams: number;
    zakat_nisab_silver_grams: number;
    maintenance_mode_enabled: boolean;
    maintenance_message: string;
}

function ToggleRow({
    id,
    title,
    desc,
    checked,
    onChange,
}: {
    id: string;
    title: string;
    desc: string;
    checked: boolean;
    onChange: (v: boolean) => void;
}) {
    return (
        <div className="adm-toggle-row">
            <div className="adm-toggle-copy">
                <label htmlFor={id} className="adm-toggle-title">
                    {title}
                </label>
                <span className="adm-toggle-desc">{desc}</span>
            </div>
            <span className="adm-switch">
                <input id={id} type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} />
                <span className="adm-switch-track" />
            </span>
        </div>
    );
}

export default function SystemSettings({
    settings,
    metals,
    itemTypeCostDefaults,
}: {
    settings: Settings;
    metals: MetalRow[];
    itemTypeCostDefaults: ItemTypeCostDefaultRow[];
}) {
    const { flash } = usePage().props as any;
    const [logoPreview, setLogoPreview] = useState<string | null>(settings.shop_logo);

    const form = useForm({
        shop_name: settings.shop_name || '',
        shop_tagline: settings.shop_tagline || '',
        shop_address: settings.shop_address || '',
        shop_phone: settings.shop_phone || '',
        shop_ntn: settings.shop_ntn || '',
        shop_logo: null as File | null,

        receipt_printer_type: settings.receipt_printer_type,
        item_slip_print_width_mm: settings.item_slip_print_width_mm,
        item_slip_print_height_mm: settings.item_slip_print_height_mm,
        item_slip_print_padding_mm: settings.item_slip_print_padding_mm,

        active_metal_ids: metals.filter((m) => m.is_active).map((m) => m.id) as number[],

        item_type_cost_defaults: itemTypeCostDefaults as any[],

        buyback_enabled: settings.buyback_enabled,
        bank_qr_enabled: settings.bank_qr_enabled,
        bank_qr_manager_confirm_enabled: settings.bank_qr_manager_confirm_enabled,
        exchange_enabled: settings.exchange_enabled,
        rate_auto_refresh_enabled: settings.rate_auto_refresh_enabled,

        tax_enabled: settings.tax_enabled,
        tax_rate_percent: settings.tax_rate_percent,

        zakat_enabled: settings.zakat_enabled,
        zakat_rate_percent: settings.zakat_rate_percent,
        zakat_nisab_gold_grams: settings.zakat_nisab_gold_grams,
        zakat_nisab_silver_grams: settings.zakat_nisab_silver_grams,

        maintenance_mode_enabled: settings.maintenance_mode_enabled,
        maintenance_message: settings.maintenance_message || '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/admin/settings', { preserveScroll: true, forceFormData: true });
    };

    const onLogoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] ?? null;
        form.setData('shop_logo', file);
        if (file) {
            const reader = new FileReader();
            reader.onload = () => setLogoPreview(reader.result as string);
            reader.readAsDataURL(file);
        }
    };

    const toggleMetal = (id: number, checked: boolean) => {
        const current = form.data.active_metal_ids;
        form.setData('active_metal_ids', checked ? [...current, id] : current.filter((x) => x !== id));
    };

    const setLabourDefault = (itemType: string, value: number) => {
        form.setData(
            'item_type_cost_defaults',
            form.data.item_type_cost_defaults.map((row) => (row.item_type === itemType ? { ...row, labour_cost_default: value } : row)),
        );
    };

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Settings" />

            <div className="adm-page">
                <div className="adm-head">
                    <h1 className="adm-title">Settings</h1>
                    <p className="adm-desc">
                        Everything that shapes how the shop runs — identity on the invoice, which modules are switched on, sales tax, and the
                        emergency stop.
                    </p>
                </div>

                {flash?.success && <div className="adm-flash adm-flash-success">{flash.success}</div>}
                {flash?.error && <div className="adm-flash adm-flash-error">{flash.error}</div>}

                <form onSubmit={submit}>
                    {/* ── Shop profile ─────────────────────────────── */}
                    <div className="adm-card">
                        <h2 className="adm-card-title">Shop Profile</h2>
                        <p className="adm-card-sub">Printed on every invoice header.</p>

                        <div className="adm-form-row" style={{ marginBottom: '0.9rem' }}>
                            <div className="adm-field" style={{ flex: 1 }}>
                                <label className="adm-label" htmlFor="shop_name">
                                    Shop Name
                                </label>
                                <input
                                    id="shop_name"
                                    className="adm-input"
                                    style={{ width: '100%' }}
                                    value={form.data.shop_name}
                                    onChange={(e) => form.setData('shop_name', e.target.value)}
                                />
                                {form.errors.shop_name && <span className="adm-error">{form.errors.shop_name}</span>}
                            </div>
                            <div className="adm-field" style={{ flex: 1 }}>
                                <label className="adm-label" htmlFor="shop_tagline">
                                    Tagline
                                </label>
                                <input
                                    id="shop_tagline"
                                    className="adm-input"
                                    style={{ width: '100%' }}
                                    value={form.data.shop_tagline}
                                    onChange={(e) => form.setData('shop_tagline', e.target.value)}
                                />
                            </div>
                        </div>

                        <div className="adm-form-row" style={{ marginBottom: '0.9rem' }}>
                            <div className="adm-field" style={{ flex: 2 }}>
                                <label className="adm-label" htmlFor="shop_address">
                                    Address
                                </label>
                                <input
                                    id="shop_address"
                                    className="adm-input"
                                    style={{ width: '100%' }}
                                    value={form.data.shop_address}
                                    onChange={(e) => form.setData('shop_address', e.target.value)}
                                />
                            </div>
                            <div className="adm-field" style={{ flex: 1 }}>
                                <label className="adm-label" htmlFor="shop_phone">
                                    Phone
                                </label>
                                <input
                                    id="shop_phone"
                                    className="adm-input"
                                    style={{ width: '100%' }}
                                    value={form.data.shop_phone}
                                    onChange={(e) => form.setData('shop_phone', e.target.value)}
                                />
                            </div>
                        </div>

                        <div className="adm-form-row" style={{ marginTop: '0.9rem' }}>
                            <div className="adm-field" style={{ maxWidth: '260px' }}>
                                <label className="adm-label" htmlFor="shop_ntn">
                                    NTN (optional)
                                </label>
                                <input
                                    id="shop_ntn"
                                    className="adm-input"
                                    style={{ width: '100%' }}
                                    value={form.data.shop_ntn}
                                    onChange={(e) => form.setData('shop_ntn', e.target.value)}
                                    placeholder="e.g. 1234567-8"
                                />
                            </div>
                            <div className="adm-field">
                                <label className="adm-label" htmlFor="shop_logo">
                                    Shop Logo
                                </label>
                                <div style={{ display: 'flex', alignItems: 'center', gap: '0.9rem' }}>
                                    <div
                                        style={{
                                            width: 56,
                                            height: 56,
                                            flexShrink: 0,
                                            borderRadius: 8,
                                            border: '1px solid var(--adm-line)',
                                            background: 'var(--adm-surface-alt)',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            overflow: 'hidden',
                                        }}
                                    >
                                        {logoPreview ? (
                                            <img src={logoPreview} alt="Shop logo preview" style={{ maxWidth: '100%', maxHeight: '100%' }} />
                                        ) : (
                                            <span className="adm-muted" style={{ fontSize: '.65rem', textAlign: 'center' }}>
                                                No logo
                                            </span>
                                        )}
                                    </div>
                                    <input id="shop_logo" type="file" accept="image/*" className="adm-file" onChange={onLogoChange} />
                                </div>
                                {form.errors.shop_logo && <span className="adm-error">{form.errors.shop_logo}</span>}
                            </div>
                        </div>
                    </div>

                    {/* ── Receipt printing ──────────────────────────── */}
                    <div className="adm-card">
                        <h2 className="adm-card-title">Receipt Printing</h2>
                        <p className="adm-card-sub">
                            Which layout the POS "Print" button produces — choose whichever matches the printer actually connected at the counter.
                        </p>

                        <div className="adm-form-row" style={{ gap: '0.75rem' }}>
                            <label
                                className="adm-field"
                                style={{
                                    flex: 1,
                                    cursor: 'pointer',
                                    padding: '0.8rem 1rem',
                                    borderRadius: 8,
                                    border: `1px solid ${form.data.receipt_printer_type === 'regular' ? 'var(--adm-gold)' : 'var(--adm-line)'}`,
                                    background: form.data.receipt_printer_type === 'regular' ? 'var(--adm-gold-soft)' : 'var(--adm-surface-alt)',
                                }}
                            >
                                <input
                                    type="radio"
                                    name="receipt_printer_type"
                                    value="regular"
                                    style={{ marginRight: '0.5rem' }}
                                    checked={form.data.receipt_printer_type === 'regular'}
                                    onChange={() => form.setData('receipt_printer_type', 'regular')}
                                />
                                <span style={{ fontWeight: 600 }}>Regular Printer</span>
                                <p className="adm-card-sub" style={{ margin: '0.3rem 0 0' }}>
                                    A full-width printable sheet — for an ordinary A4/letter printer.
                                </p>
                            </label>
                            <label
                                className="adm-field"
                                style={{
                                    flex: 1,
                                    cursor: 'pointer',
                                    padding: '0.8rem 1rem',
                                    borderRadius: 8,
                                    border: `1px solid ${form.data.receipt_printer_type === 'thermal' ? 'var(--adm-gold)' : 'var(--adm-line)'}`,
                                    background: form.data.receipt_printer_type === 'thermal' ? 'var(--adm-gold-soft)' : 'var(--adm-surface-alt)',
                                }}
                            >
                                <input
                                    type="radio"
                                    name="receipt_printer_type"
                                    value="thermal"
                                    style={{ marginRight: '0.5rem' }}
                                    checked={form.data.receipt_printer_type === 'thermal'}
                                    onChange={() => form.setData('receipt_printer_type', 'thermal')}
                                />
                                <span style={{ fontWeight: 600 }}>Thermal Printer</span>
                                <p className="adm-card-sub" style={{ margin: '0.3rem 0 0' }}>
                                    A narrow receipt-roll layout — for a dedicated POS thermal printer.
                                </p>
                            </label>
                        </div>

                        <p className="adm-card-sub" style={{ marginTop: '1.1rem' }}>
                            Item Entry's "Save &amp; Print" weight/value slip — size it to whatever paper roll or sheet is actually loaded.
                        </p>
                        <div className="adm-form-row">
                            <div className="adm-field" style={{ maxWidth: '180px' }}>
                                <label className="adm-label" htmlFor="item_slip_print_width_mm">
                                    Slip Width (mm)
                                </label>
                                <input
                                    id="item_slip_print_width_mm"
                                    type="number"
                                    step="1"
                                    min="20"
                                    max="300"
                                    className="adm-input"
                                    style={{ width: '100%' }}
                                    value={form.data.item_slip_print_width_mm}
                                    onChange={(e) => form.setData('item_slip_print_width_mm', parseFloat(e.target.value) || 0)}
                                />
                                {form.errors.item_slip_print_width_mm && <span className="adm-error">{form.errors.item_slip_print_width_mm}</span>}
                            </div>
                            <div className="adm-field" style={{ maxWidth: '180px' }}>
                                <label className="adm-label" htmlFor="item_slip_print_height_mm">
                                    Slip Height (mm)
                                </label>
                                <input
                                    id="item_slip_print_height_mm"
                                    type="number"
                                    step="1"
                                    min="0"
                                    max="500"
                                    className="adm-input"
                                    style={{ width: '100%' }}
                                    value={form.data.item_slip_print_height_mm}
                                    onChange={(e) => form.setData('item_slip_print_height_mm', parseFloat(e.target.value) || 0)}
                                />
                                <span className="adm-card-sub" style={{ margin: '0.3rem 0 0' }}>
                                    0 = auto (grows to fit content)
                                </span>
                                {form.errors.item_slip_print_height_mm && <span className="adm-error">{form.errors.item_slip_print_height_mm}</span>}
                            </div>
                            <div className="adm-field" style={{ maxWidth: '180px' }}>
                                <label className="adm-label" htmlFor="item_slip_print_padding_mm">
                                    Slip Padding (mm)
                                </label>
                                <input
                                    id="item_slip_print_padding_mm"
                                    type="number"
                                    step="0.5"
                                    min="0"
                                    max="50"
                                    className="adm-input"
                                    style={{ width: '100%' }}
                                    value={form.data.item_slip_print_padding_mm}
                                    onChange={(e) => form.setData('item_slip_print_padding_mm', parseFloat(e.target.value) || 0)}
                                />
                                {form.errors.item_slip_print_padding_mm && (
                                    <span className="adm-error">{form.errors.item_slip_print_padding_mm}</span>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* ── Metals dealt in ──────────────────────────── */}
                    <div className="adm-card">
                        <h2 className="adm-card-title">Metals Dealt In</h2>
                        <p className="adm-card-sub">
                            Turn off a metal the shop doesn't currently trade — it disappears from item entry, POS, buy-back, and rate management. At
                            least one must stay on.
                        </p>

                        {metals.map((m) => (
                            <ToggleRow
                                key={m.id}
                                id={`metal_${m.id}`}
                                title={m.name}
                                desc={`Offer ${m.name} throughout item entry, POS, buy-back, and rate management.`}
                                checked={form.data.active_metal_ids.includes(m.id)}
                                onChange={(v) => toggleMetal(m.id, v)}
                            />
                        ))}
                        {form.errors.active_metal_ids && <span className="adm-error">{form.errors.active_metal_ids}</span>}
                    </div>

                    {/* ── Labour cost defaults ─────────────────────── */}
                    <div className="adm-card">
                        <h2 className="adm-card-title">Labour Cost Defaults</h2>
                        <p className="adm-card-sub">
                            Pre-fills Item Entry's Labour Cost field the moment an item type is picked — still fully editable per item, this just
                            saves re-typing the usual charge every time. Leave at 0 for a type that should stay manual.
                        </p>

                        <div className="adm-labour-defaults-grid">
                            {form.data.item_type_cost_defaults.map((row) => (
                                <div className="adm-field" key={row.item_type}>
                                    <label className="adm-label" htmlFor={`labour_default_${row.item_type}`}>
                                        {ITEM_TYPE_LABELS[row.item_type] || row.item_type}
                                    </label>
                                    <input
                                        id={`labour_default_${row.item_type}`}
                                        type="number"
                                        step="any"
                                        min="0"
                                        className="adm-input"
                                        style={{ width: '100%' }}
                                        value={row.labour_cost_default || ''}
                                        onChange={(e) => setLabourDefault(row.item_type, parseFloat(e.target.value) || 0)}
                                    />
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* ── Feature toggles ──────────────────────────── */}
                    <div className="adm-card">
                        <h2 className="adm-card-title">Modules</h2>
                        <p className="adm-card-sub">
                            Turn a module off and it disappears from the menu and its pages stop accepting requests — nothing is deleted, and flipping
                            it back on restores it exactly as it was.
                        </p>

                        <ToggleRow
                            id="buyback_enabled"
                            title="Buy-Back"
                            desc="Lets a cashier re-weigh and buy back old gold/silver as its own transaction."
                            checked={form.data.buyback_enabled}
                            onChange={(v) => form.setData('buyback_enabled', v)}
                        />
                        <ToggleRow
                            id="bank_qr_enabled"
                            title="Bank QR Payment"
                            desc="Offers 'pay by scanning our bank QR' as a payment method at checkout."
                            checked={form.data.bank_qr_enabled}
                            onChange={(v) => form.setData('bank_qr_enabled', v)}
                        />
                        {form.data.bank_qr_enabled && (
                            <div style={{ marginLeft: '1.5rem', paddingLeft: '1rem', borderLeft: '2px solid var(--adm-line)' }}>
                                <ToggleRow
                                    id="bank_qr_manager_confirm_enabled"
                                    title="Allow Managers to Confirm"
                                    desc="Off by default. When on, a user with the Manager role can upload a bank slip, have its transaction ID auto-detected, and confirm the payment themselves — the same ability admins already have."
                                    checked={form.data.bank_qr_manager_confirm_enabled}
                                    onChange={(v) => form.setData('bank_qr_manager_confirm_enabled', v)}
                                />
                            </div>
                        )}
                        <ToggleRow
                            id="exchange_enabled"
                            title="Old Gold / Silver Exchange"
                            desc="Lets a sale include a trade-in that's deducted from the bill."
                            checked={form.data.exchange_enabled}
                            onChange={(v) => form.setData('exchange_enabled', v)}
                        />
                        <ToggleRow
                            id="rate_auto_refresh_enabled"
                            title="Automatic Rate Refresh"
                            desc="Quietly re-fetches the gold/silver rate from the live feed — both while a screen is open and on the scheduled job. Off means rates only ever update from the 'Refresh now' button."
                            checked={form.data.rate_auto_refresh_enabled}
                            onChange={(v) => form.setData('rate_auto_refresh_enabled', v)}
                        />
                    </div>

                    {/* ── Sales tax ─────────────────────────────────── */}
                    <div className="adm-card">
                        <h2 className="adm-card-title">Sales Tax</h2>
                        <p className="adm-card-sub">
                            Applied to the taxable amount (after discount, before any old-gold exchange deduction) and shown as its own line on the
                            invoice.
                        </p>

                        <ToggleRow
                            id="tax_enabled"
                            title="Charge Sales Tax"
                            desc="When off, no tax is added or shown on any sale."
                            checked={form.data.tax_enabled}
                            onChange={(v) => form.setData('tax_enabled', v)}
                        />

                        {form.data.tax_enabled && (
                            <div className="adm-field" style={{ maxWidth: '200px', marginTop: '0.9rem' }}>
                                <label className="adm-label" htmlFor="tax_rate_percent">
                                    Tax Rate (%)
                                </label>
                                <input
                                    id="tax_rate_percent"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    className="adm-input"
                                    style={{ width: '100%' }}
                                    value={form.data.tax_rate_percent}
                                    onChange={(e) => form.setData('tax_rate_percent', parseFloat(e.target.value) || 0)}
                                />
                                {form.errors.tax_rate_percent && <span className="adm-error">{form.errors.tax_rate_percent}</span>}
                            </div>
                        )}
                    </div>

                    {/* ── Zakat calculator ──────────────────────────── */}
                    <div className="adm-card">
                        <h2 className="adm-card-title">Zakat Calculator</h2>
                        <p className="adm-card-sub">
                            A live Zakat report on the shop's own stock, plus a manual calculator for anyone's gold/silver — both priced at today's
                            rate, never cached.
                        </p>

                        <ToggleRow
                            id="zakat_enabled"
                            title="Zakat Calculator"
                            desc="When off, the Zakat page disappears from the menu and stops accepting requests."
                            checked={form.data.zakat_enabled}
                            onChange={(v) => form.setData('zakat_enabled', v)}
                        />

                        {form.data.zakat_enabled && (
                            <div className="adm-form-row" style={{ marginTop: '0.9rem', flexWrap: 'wrap' }}>
                                <div className="adm-field" style={{ maxWidth: '160px' }}>
                                    <label className="adm-label" htmlFor="zakat_rate_percent">
                                        Zakat Rate (%)
                                    </label>
                                    <input
                                        id="zakat_rate_percent"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        className="adm-input"
                                        style={{ width: '100%' }}
                                        value={form.data.zakat_rate_percent}
                                        onChange={(e) => form.setData('zakat_rate_percent', parseFloat(e.target.value) || 0)}
                                    />
                                    {form.errors.zakat_rate_percent && <span className="adm-error">{form.errors.zakat_rate_percent}</span>}
                                </div>
                                <div className="adm-field" style={{ maxWidth: '220px' }}>
                                    <label className="adm-label" htmlFor="zakat_nisab_gold_grams">
                                        Gold Nisab (grams)
                                    </label>
                                    <input
                                        id="zakat_nisab_gold_grams"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        className="adm-input"
                                        style={{ width: '100%' }}
                                        value={form.data.zakat_nisab_gold_grams}
                                        onChange={(e) => form.setData('zakat_nisab_gold_grams', parseFloat(e.target.value) || 0)}
                                    />
                                    {form.errors.zakat_nisab_gold_grams && <span className="adm-error">{form.errors.zakat_nisab_gold_grams}</span>}
                                </div>
                                <div className="adm-field" style={{ maxWidth: '220px' }}>
                                    <label className="adm-label" htmlFor="zakat_nisab_silver_grams">
                                        Silver Nisab (grams)
                                    </label>
                                    <input
                                        id="zakat_nisab_silver_grams"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        className="adm-input"
                                        style={{ width: '100%' }}
                                        value={form.data.zakat_nisab_silver_grams}
                                        onChange={(e) => form.setData('zakat_nisab_silver_grams', parseFloat(e.target.value) || 0)}
                                    />
                                    {form.errors.zakat_nisab_silver_grams && (
                                        <span className="adm-error">{form.errors.zakat_nisab_silver_grams}</span>
                                    )}
                                </div>
                            </div>
                        )}
                        <p className="adm-card-sub" style={{ marginTop: '0.9rem' }}>
                            Zakat is due once holdings reach the value of whichever Nisab is lower — the standard, more inclusive basis for trade
                            goods. Defaults (2.5% rate, 87.48g gold / 612.36g silver) follow the commonly used classical thresholds; adjust them here
                            if your shop follows a different standard.
                        </p>
                    </div>

                    {/* ── Maintenance mode ──────────────────────────── */}
                    <div className="adm-card">
                        <h2 className="adm-card-title">Maintenance Mode</h2>
                        <p className="adm-card-sub">
                            The emergency stop — use it while fixing something that could otherwise corrupt a sale. Admins are never blocked, so you
                            can always come back here and switch it off.
                        </p>

                        <div className="adm-danger-zone">
                            <ToggleRow
                                id="maintenance_mode_enabled"
                                title="Shop system is under maintenance"
                                desc="Every other signed-in account sees a maintenance message instead of the app until this is switched off."
                                checked={form.data.maintenance_mode_enabled}
                                onChange={(v) => form.setData('maintenance_mode_enabled', v)}
                            />
                        </div>

                        {form.data.maintenance_mode_enabled && (
                            <div className="adm-field" style={{ marginTop: '0.9rem' }}>
                                <label className="adm-label" htmlFor="maintenance_message">
                                    Message shown to staff
                                </label>
                                <textarea
                                    id="maintenance_message"
                                    className="adm-input"
                                    style={{ width: '100%', minHeight: '70px', resize: 'vertical' }}
                                    value={form.data.maintenance_message}
                                    onChange={(e) => form.setData('maintenance_message', e.target.value)}
                                    placeholder="e.g. Closed for stock-taking, back by 5pm."
                                />
                            </div>
                        )}
                    </div>

                    <button type="submit" className="adm-btn adm-btn-gold" disabled={form.processing}>
                        {form.processing ? 'Saving…' : 'Save Settings'}
                    </button>
                </form>
            </div>
        </AdminLayout>
    );
}
