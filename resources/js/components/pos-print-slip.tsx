import { usePOS } from '@/context/POSContext';

interface Shop {
    name: string;
    address: string | null;
    phone: string | null;
    logo: string | null;
}

const fmt = (n: number) =>
    new Intl.NumberFormat('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n) || 0);

/**
 * The POS "Print" button's output — always rendered from live cart state,
 * never a saved Invoice, so it's always labeled a quotation/estimate rather
 * than a tax receipt (see pos/index.tsx for why: Print works before a sale
 * is ever completed, so there's nothing official to print a receipt for).
 * Hidden on screen, shown only by @media print (see pos-print.css).
 */
export default function PosPrintSlip({ shop, printerType }: { shop: Shop; printerType: 'regular' | 'thermal' }) {
    const { state } = usePOS();
    const now = new Date();
    const isThermal = printerType === 'thermal';

    return (
        <div className={`pos-print-area ${isThermal ? 'pos-print-thermal' : 'pos-print-regular'}`}>
            <div className="pps-header">
                {shop.logo && <img src={shop.logo} alt="" className="pps-logo" />}
                <div className="pps-shop-name">{shop.name}</div>
                {shop.address && <div className="pps-shop-line">{shop.address}</div>}
                {shop.phone && <div className="pps-shop-line">{shop.phone}</div>}
            </div>

            <div className="pps-badge">Quotation / Estimate — Not a Tax Receipt</div>
            <div className="pps-meta">{now.toLocaleDateString()} {now.toLocaleTimeString()}</div>

            {(state.customer_name || state.customer_phone) && (
                <div className="pps-customer">
                    {state.customer_name && <div>{state.customer_name}</div>}
                    {state.customer_phone && <div>{state.customer_phone}</div>}
                </div>
            )}

            {/* No multi-column table anywhere — a real rupee amount next to a
                rate next to a weight next to a name never reliably fits
                side-by-side at any paper width a printer might actually use.
                Every field gets its own row instead: label left, value
                right, one line each. Can't overlap regardless of width. */}
            <div className="pps-list">
                {state.items.map(item => {
                    const lineTotal = item.net_weight_grams * item.rate_per_gram + item.labour_cost + item.polish_cost;
                    return (
                        <div className="pps-item" key={item.id}>
                            <div className="pps-item-name">{item.item_type} · {item.metal_name} {item.purity_name}</div>
                            <div className="pps-field"><span>Weight</span><span>{item.net_weight_grams.toFixed(3)} g</span></div>
                            <div className="pps-field"><span>Rate/g</span><span>{fmt(item.rate_per_gram)}</span></div>
                            <div className="pps-field pps-field-total"><span>Total</span><span>{fmt(lineTotal)}</span></div>
                        </div>
                    );
                })}
            </div>

            {state.exchanges.length > 0 && (
                <>
                    <div className="pps-section-label">Old Gold/Silver Exchange</div>
                    <div className="pps-list">
                        {state.exchanges.map(exc => (
                            <div className="pps-item" key={exc.id}>
                                <div className="pps-item-name">{exc.metal_name} {exc.purity_name}</div>
                                <div className="pps-field"><span>Weight</span><span>{exc.net_weight_grams.toFixed(3)} g</span></div>
                                <div className="pps-field"><span>Rate/g</span><span>{fmt(exc.rate_per_gram)}</span></div>
                                <div className="pps-field pps-field-total"><span>Deducted</span><span>−{fmt(exc.valuation)}</span></div>
                            </div>
                        ))}
                    </div>
                </>
            )}

            <div className="pps-totals">
                <div className="pps-row"><span>Subtotal</span><span>{fmt(state.subtotal)}</span></div>
                {state.discount > 0 && (
                    <div className="pps-row"><span>Discount{state.discount_reason ? ` (${state.discount_reason})` : ''}</span>
                        <span>−{state.discountType === 'percentage' ? `${state.discount}%` : fmt(state.discount)}</span></div>
                )}
                {state.taxEnabled && state.taxAmount > 0 && (
                    <div className="pps-row"><span>Tax ({state.taxRatePercent}%)</span><span>{fmt(state.taxAmount)}</span></div>
                )}
                <div className="pps-row pps-grand"><span>Total</span><span>Rs {fmt(state.total)}</span></div>
            </div>

            <div className="pps-footer">
                This is a price quotation only — not proof of payment or a finalized sale.
            </div>
        </div>
    );
}
