const fmt = (n: number | string) =>
    'Rs ' + new Intl.NumberFormat('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n) || 0);

const wt = (n: number | string) => Number(n || 0).toFixed(3);
const prettyMethod = (m: string) => (m || '').replace('_', ' ');

interface InvoiceSheetProps {
    invoice: any;
    subtotal: number;
    exchangeLines?: any[];
    shop: any;
}

/** The printable invoice document. Rendered both on the standalone page and inside the quick-view modal. */
export default function InvoiceSheet({ invoice, subtotal, exchangeLines = [], shop }: InvoiceSheetProps) {
    const lines = invoice.line_items ?? [];

    return (
        <div className="inv-sheet">
            <div className="inv-sheet-top">
                <div>
                    <h1 className="inv-shop-name">{shop.name}</h1>
                    {shop.tagline && <div className="inv-shop-line">{shop.tagline}</div>}
                    {shop.address && <div className="inv-shop-line">{shop.address}</div>}
                    {shop.phone && <div className="inv-shop-line">{shop.phone}</div>}
                    {shop.ntn && <div className="inv-shop-line">NTN: {shop.ntn}</div>}
                </div>
                <div className="inv-doc-label">
                    <h2>INVOICE</h2>
                    <div className="inv-doc-num">{invoice.invoice_number}</div>
                </div>
            </div>

            <div className="inv-meta-grid">
                <div>
                    <div className="inv-meta-label">Bill To</div>
                    <div className="inv-meta-value">{invoice.party?.name || 'Walk-in Customer'}</div>
                    {invoice.party?.phone && <div className="inv-shop-line">{invoice.party.phone}</div>}
                    {invoice.party?.party_type?.name && <div className="inv-shop-line">{invoice.party.party_type.name}</div>}
                </div>
                <div>
                    <div className="inv-meta-label">Invoice Date</div>
                    <div className="inv-meta-value">{new Date(invoice.invoice_date).toLocaleDateString()}</div>
                </div>
                <div>
                    <div className="inv-meta-label">Payment Method</div>
                    <div className="inv-meta-value" style={{ textTransform: 'capitalize' }}>{prettyMethod(invoice.payment_method)}</div>
                </div>
                <div>
                    <div className="inv-meta-label">Issued By</div>
                    <div className="inv-meta-value">{invoice.created_by?.full_name || '—'}</div>
                </div>
            </div>

            <table className="inv-line-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Description</th>
                        <th>Wt (g)</th>
                        <th>Rate/g</th>
                        <th>Metal</th>
                        <th>Labour</th>
                        <th>Polish</th>
                        <th>Line Total</th>
                    </tr>
                </thead>
                <tbody>
                    {lines.map((l: any) => (
                        <tr key={l.id}>
                            <td className="inv-cell-text">
                                <span className="inv-line-code">{l.item?.item_code || '—'}</span>
                            </td>
                            <td className="inv-cell-text">
                                <span style={{ textTransform: 'capitalize' }}>{l.item?.item_type?.replace('_', ' ') || 'Item'}</span>
                                <span className="inv-line-sub">
                                    {l.item?.metal_type?.name} {l.purity?.name}
                                </span>
                            </td>
                            <td>{wt(l.weight_grams)}</td>
                            <td>{fmt(l.rate_per_gram)}</td>
                            <td>{fmt(l.metal_cost)}</td>
                            <td>{fmt(l.labour_cost)}</td>
                            <td>{fmt(l.polish_cost)}</td>
                            <td>{fmt(l.line_total)}</td>
                        </tr>
                    ))}
                </tbody>
                <tfoot>
                    <tr>
                        <td className="inv-foot-label" colSpan={4} style={{ textAlign: 'right' }}>Subtotal</td>
                        <td>{fmt(invoice.total_metal_cost)}</td>
                        <td>{fmt(invoice.total_labour_cost)}</td>
                        <td>{fmt(invoice.total_polish_cost)}</td>
                        <td>{fmt(subtotal)}</td>
                    </tr>

                    {Number(invoice.total_discount) > 0 && (
                        <>
                            <tr>
                                <td className="inv-foot-label" colSpan={7} style={{ textAlign: 'right' }}>Discount</td>
                                <td className="inv-negative-amt">− {fmt(invoice.total_discount)}</td>
                            </tr>
                            {invoice.discount_reason && (
                                <tr>
                                    <td className="inv-foot-reason" colSpan={8}>Reason: {invoice.discount_reason}</td>
                                </tr>
                            )}
                        </>
                    )}

                    {exchangeLines.map((ex: any) => (
                        <tr key={ex.id}>
                            <td className="inv-foot-label" colSpan={7} style={{ textAlign: 'right' }}>
                                Old-gold exchange — {ex.description} ({wt(ex.weight_grams)} g @ {fmt(ex.rate_per_gram)}, less {Number(ex.deduction_percent).toFixed(2)}%)
                            </td>
                            <td className="inv-negative-amt">− {fmt(ex.valuation)}</td>
                        </tr>
                    ))}

                    <tr className="inv-grand">
                        <td className="inv-foot-label" colSpan={7} style={{ textAlign: 'right', fontFamily: 'inherit', fontWeight: 800 }}>Grand Total</td>
                        <td>{fmt(invoice.grand_total)}</td>
                    </tr>
                </tfoot>
            </table>

            <div className="inv-sheet-note">
                Goods once sold are subject to the shop's exchange policy. This is a computer-generated invoice.
            </div>
        </div>
    );
}
