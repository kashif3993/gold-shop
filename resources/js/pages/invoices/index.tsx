import InvoiceSheet from '@/components/invoice-sheet';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Eye, Printer, Receipt, Wallet, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import '../../../css/invoices.css';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Invoices', href: '/invoices' }];

const fmt = (n: number | string) =>
    'Rs ' + new Intl.NumberFormat('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n) || 0);

const prettyMethod = (m: string) => (m || '').replace('_', ' ');

const statusLabel: Record<string, string> = { paid: 'Paid', partial: 'Partial', due: 'Due' };

interface Detail {
    invoice: any;
    subtotal: number;
    exchangeLines: any[];
    shop: any;
    bankQrSlipUrl: string | null;
    amountPaid: number;
    amountDue: number;
    paymentStatus: 'paid' | 'partial' | 'due';
}

export default function InvoicesIndex({ invoices, filters, paymentMethods, summary }: any) {
    const [search, setSearch] = useState(filters.search || '');
    const [method, setMethod] = useState(filters.payment_method || '');
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');

    const [openId, setOpenId] = useState<number | null>(null);
    const [detail, setDetail] = useState<Detail | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    const hasFilters = search || method || dateFrom || dateTo;

    const applyFilters = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/invoices',
            {
                search,
                payment_method: method,
                date_from: dateFrom,
                date_to: dateTo,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        setMethod('');
        setDateFrom('');
        setDateTo('');
        router.get('/invoices');
    };

    const openInvoice = useCallback((id: number) => {
        setOpenId(id);
        setDetail(null);
        setError('');
        setLoading(true);
        fetch(`/invoices/${id}/data`, { headers: { Accept: 'application/json' } })
            .then((r) => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then((data: Detail) => setDetail(data))
            .catch(() => setError('Could not load this invoice. Please try again.'))
            .finally(() => setLoading(false));
    }, []);

    const closeModal = useCallback(() => {
        setOpenId(null);
        setDetail(null);
        setError('');
    }, []);

    // ── Record Payment modal — separate from the view/print modal above,
    // opened only for an invoice that isn't fully paid yet. ──
    const [payOpenId, setPayOpenId] = useState<number | null>(null);
    const [payDetail, setPayDetail] = useState<Detail | null>(null);
    const [payLoading, setPayLoading] = useState(false);
    const [payError, setPayError] = useState('');
    const [payAmount, setPayAmount] = useState('');
    const [payMethod, setPayMethod] = useState('cash');
    const [payNotes, setPayNotes] = useState('');
    const [paySubmitting, setPaySubmitting] = useState(false);

    const loadPayDetail = useCallback((id: number) => {
        setPayDetail(null);
        setPayError('');
        setPayLoading(true);
        fetch(`/invoices/${id}/data`, { headers: { Accept: 'application/json' } })
            .then((r) => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then((data: Detail) => {
                setPayDetail(data);
                setPayAmount(String(data.amountDue));
            })
            .catch(() => setPayError('Could not load this invoice. Please try again.'))
            .finally(() => setPayLoading(false));
    }, []);

    const openPayment = useCallback(
        (id: number) => {
            setPayOpenId(id);
            setPayMethod('cash');
            setPayNotes('');
            loadPayDetail(id);
        },
        [loadPayDetail],
    );

    const closePayModal = useCallback(() => {
        setPayOpenId(null);
        setPayDetail(null);
        setPayError('');
        setPaySubmitting(false);
    }, []);

    const submitPayment = (e: React.FormEvent) => {
        e.preventDefault();
        if (payOpenId === null) return;

        const amount = parseFloat(payAmount);
        if (!amount || amount <= 0) {
            setPayError('Enter an amount greater than 0.');
            return;
        }

        setPaySubmitting(true);
        setPayError('');
        router.post(
            `/invoices/${payOpenId}/payments`,
            { amount, payment_method: payMethod, notes: payNotes || undefined },
            {
                preserveScroll: true,
                onSuccess: () => loadPayDetail(payOpenId),
                onError: (errors) => setPayError(Object.values(errors).flat().join(' ') || 'Could not record this payment.'),
                onFinish: () => setPaySubmitting(false),
            },
        );
    };

    useEffect(() => {
        if (openId === null) return;
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') closeModal();
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [openId, closeModal]);

    useEffect(() => {
        if (payOpenId === null) return;
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') closePayModal();
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [payOpenId, closePayModal]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Invoices" />

            <div className="inv-page">
                <div className="inv-head">
                    <div>
                        <h1 className="inv-title">Invoices</h1>
                        <p className="inv-desc">Every sale billed through the POS, with its metal / labour / polish breakdown.</p>
                    </div>
                </div>

                <div className="inv-summary-grid">
                    <div className="inv-stat">
                        <div className="inv-stat-label">Invoices</div>
                        <div className="inv-stat-value">{summary.count}</div>
                    </div>
                    <div className="inv-stat">
                        <div className="inv-stat-label">Total billed</div>
                        <div className="inv-stat-value gold">{fmt(summary.grand_total)}</div>
                    </div>
                    <div className="inv-stat">
                        <div className="inv-stat-label">Total discount</div>
                        <div className="inv-stat-value">{fmt(summary.total_discount)}</div>
                    </div>
                    <div className="inv-stat">
                        <div className="inv-stat-label">Old-gold exchange</div>
                        <div className="inv-stat-value">{fmt(summary.total_exchange)}</div>
                    </div>
                </div>

                <form className="inv-filters" onSubmit={applyFilters}>
                    <div className="inv-filter-group">
                        <label className="inv-filter-label">Search (invoice # / customer)</label>
                        <input
                            className="inv-filter-input"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="INV-2026... or name"
                        />
                    </div>
                    <div className="inv-filter-group">
                        <label className="inv-filter-label">Payment Method</label>
                        <select className="inv-filter-input" value={method} onChange={(e) => setMethod(e.target.value)}>
                            <option value="">All</option>
                            {paymentMethods.map((m: string) => (
                                <option key={m} value={m} style={{ textTransform: 'capitalize' }}>
                                    {prettyMethod(m)}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="inv-filter-group">
                        <label className="inv-filter-label">From</label>
                        <input type="date" className="inv-filter-input" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} />
                    </div>
                    <div className="inv-filter-group">
                        <label className="inv-filter-label">To</label>
                        <input type="date" className="inv-filter-input" value={dateTo} onChange={(e) => setDateTo(e.target.value)} />
                    </div>
                    <button type="submit" className="inv-filter-btn">
                        Apply
                    </button>
                    {hasFilters && (
                        <button type="button" className="inv-filter-clear" onClick={clearFilters}>
                            Clear
                        </button>
                    )}
                </form>

                <div className="inv-table-wrap">
                    <div className="inv-table-scroll">
                        <table className="inv-table">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Items</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th style={{ textAlign: 'right' }}>Discount</th>
                                    <th style={{ textAlign: 'right' }}>Grand Total</th>
                                    <th style={{ textAlign: 'right' }}>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {invoices.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={9} className="inv-empty">
                                            No invoices match your filters.
                                        </td>
                                    </tr>
                                ) : (
                                    invoices.data.map((inv: any) => (
                                        <tr key={inv.id}>
                                            <td>
                                                <button type="button" className="inv-num" onClick={() => openInvoice(inv.id)}>
                                                    {inv.invoice_number}
                                                </button>
                                            </td>
                                            <td>{new Date(inv.invoice_date).toLocaleDateString()}</td>
                                            <td>{inv.party?.name || <span className="inv-muted">-</span>}</td>
                                            <td>{inv.line_items_count}</td>
                                            <td>
                                                <span className={`inv-pay-badge ${inv.payment_method}`}>{prettyMethod(inv.payment_method)}</span>
                                                {inv.bank_txn_id && (
                                                    <div
                                                        className="inv-muted"
                                                        style={{ fontSize: '0.72rem', marginTop: '0.2rem', fontFamily: 'monospace' }}
                                                    >
                                                        Txn: {inv.bank_txn_id}
                                                    </div>
                                                )}
                                            </td>
                                            <td>
                                                <span className={`inv-status-badge ${inv.payment_status}`}>{statusLabel[inv.payment_status]}</span>
                                                {inv.payment_status !== 'paid' && (
                                                    <div className="inv-muted" style={{ fontSize: '0.72rem', marginTop: '0.2rem' }}>
                                                        {fmt(inv.amount_due)} due
                                                    </div>
                                                )}
                                            </td>
                                            <td style={{ textAlign: 'right' }} className="inv-money">
                                                {Number(inv.total_discount) > 0 ? fmt(inv.total_discount) : <span className="inv-muted">-</span>}
                                            </td>
                                            <td style={{ textAlign: 'right' }} className="inv-money">
                                                {fmt(inv.grand_total)}
                                            </td>
                                            <td style={{ textAlign: 'right' }}>
                                                <div style={{ display: 'inline-flex', gap: '0.4rem' }}>
                                                    {inv.bank_qr_slip_url && (
                                                        <a
                                                            href={inv.bank_qr_slip_url}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="inv-view-btn"
                                                            title="View payment slip"
                                                        >
                                                            <Receipt size={14} />
                                                        </a>
                                                    )}
                                                    {inv.payment_status !== 'paid' && (
                                                        <button
                                                            type="button"
                                                            className="inv-view-btn inv-pay-btn"
                                                            title="Record a payment"
                                                            onClick={() => openPayment(inv.id)}
                                                        >
                                                            <Wallet size={14} />
                                                        </button>
                                                    )}
                                                    <button type="button" className="inv-view-btn" onClick={() => openInvoice(inv.id)}>
                                                        <Eye size={14} /> View
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {invoices.total > 0 && (
                        <div className="inv-pagination">
                            <div>
                                Showing {invoices.from || 0} to {invoices.to || 0} of {invoices.total} invoices
                            </div>
                            {invoices.links && invoices.links.length > 3 && (
                                <div className="inv-pagination-links">
                                    {invoices.links.map((link: any, i: number) => (
                                        <button
                                            key={i}
                                            type="button"
                                            disabled={!link.url}
                                            onClick={() => link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                                            className={`inv-page-link ${link.active ? 'active' : ''} ${!link.url ? 'disabled' : ''}`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                </div>

                {openId !== null && (
                    <div className="inv-modal-overlay" onMouseDown={closeModal}>
                        <div className="inv-modal" onMouseDown={(e) => e.stopPropagation()}>
                            <div className="inv-modal-head">
                                <h3>Invoice</h3>
                                <div className="inv-modal-head-actions">
                                    {detail?.bankQrSlipUrl && (
                                        <a href={detail.bankQrSlipUrl} target="_blank" rel="noopener noreferrer" className="inv-btn inv-btn-ghost">
                                            <Receipt size={14} /> Slip
                                        </a>
                                    )}
                                    {detail && (
                                        <button type="button" className="inv-btn inv-btn-gold" onClick={() => window.print()}>
                                            <Printer size={14} /> Print
                                        </button>
                                    )}
                                    <button type="button" className="inv-modal-close" onClick={closeModal} aria-label="Close">
                                        <X size={18} />
                                    </button>
                                </div>
                            </div>
                            <div className="inv-modal-body">
                                {loading && <div className="inv-modal-status">Loading invoice…</div>}
                                {error && <div className="inv-modal-status inv-modal-error">{error}</div>}
                                {detail && (
                                    <InvoiceSheet
                                        invoice={detail.invoice}
                                        subtotal={detail.subtotal}
                                        exchangeLines={detail.exchangeLines}
                                        shop={detail.shop}
                                    />
                                )}
                            </div>
                        </div>
                    </div>
                )}

                {payOpenId !== null && (
                    <div className="inv-modal-overlay" onMouseDown={closePayModal}>
                        <div className="inv-modal inv-pay-modal" onMouseDown={(e) => e.stopPropagation()}>
                            <div className="inv-modal-head">
                                <h3>Record Payment{payDetail ? ` — ${payDetail.invoice.invoice_number}` : ''}</h3>
                                <div className="inv-modal-head-actions">
                                    <button type="button" className="inv-modal-close" onClick={closePayModal} aria-label="Close">
                                        <X size={18} />
                                    </button>
                                </div>
                            </div>
                            <div className="inv-modal-body">
                                {payLoading && <div className="inv-modal-status">Loading invoice…</div>}
                                {payError && <div className="inv-modal-status inv-modal-error">{payError}</div>}
                                {payDetail && (
                                    <>
                                        <div className="inv-pay-summary">
                                            <div>
                                                <div className="inv-pay-summary-label">Grand Total</div>
                                                <div className="inv-pay-summary-value">{fmt(payDetail.invoice.grand_total)}</div>
                                            </div>
                                            <div>
                                                <div className="inv-pay-summary-label">Paid So Far</div>
                                                <div className="inv-pay-summary-value">{fmt(payDetail.amountPaid)}</div>
                                            </div>
                                            <div>
                                                <div className="inv-pay-summary-label">Remaining Due</div>
                                                <div className="inv-pay-summary-value due">{fmt(payDetail.amountDue)}</div>
                                            </div>
                                        </div>

                                        <h4 className="inv-pay-section-title">Payments made before</h4>
                                        {payDetail.invoice.payments?.length ? (
                                            <table className="inv-pay-history">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Method</th>
                                                        <th style={{ textAlign: 'right' }}>Amount</th>
                                                        <th>Recorded by</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {payDetail.invoice.payments.map((p: any) => (
                                                        <tr key={p.id}>
                                                            <td>{new Date(p.paid_at).toLocaleDateString()}</td>
                                                            <td style={{ textTransform: 'capitalize' }}>{prettyMethod(p.payment_method)}</td>
                                                            <td style={{ textAlign: 'right' }}>{fmt(p.amount)}</td>
                                                            <td className="inv-muted">{p.recordedBy?.username ?? '—'}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        ) : (
                                            <p className="inv-muted" style={{ fontSize: '0.8rem' }}>
                                                No payments recorded yet — this invoice is entirely due.
                                            </p>
                                        )}

                                        {payDetail.amountDue > 0 ? (
                                            <>
                                                <h4 className="inv-pay-section-title">Record a new payment</h4>
                                                <form className="inv-pay-form" onSubmit={submitPayment}>
                                                    <div className="inv-pay-form-row">
                                                        <div>
                                                            <label className="inv-filter-label">Amount (Rs)</label>
                                                            <input
                                                                type="number"
                                                                step="0.01"
                                                                min="0.01"
                                                                max={payDetail.amountDue}
                                                                className="inv-filter-input"
                                                                value={payAmount}
                                                                onChange={(e) => setPayAmount(e.target.value)}
                                                            />
                                                        </div>
                                                        <div>
                                                            <label className="inv-filter-label">Method</label>
                                                            <select
                                                                className="inv-filter-input"
                                                                value={payMethod}
                                                                onChange={(e) => setPayMethod(e.target.value)}
                                                            >
                                                                <option value="cash">Cash</option>
                                                                <option value="card">Card</option>
                                                                <option value="bank_transfer">Bank Transfer</option>
                                                                <option value="bank_qr">Bank QR</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div style={{ marginTop: '0.6rem' }}>
                                                        <label className="inv-filter-label">Notes (optional)</label>
                                                        <input
                                                            type="text"
                                                            className="inv-filter-input"
                                                            style={{ width: '100%' }}
                                                            value={payNotes}
                                                            onChange={(e) => setPayNotes(e.target.value)}
                                                            placeholder="e.g. paid in person at the counter"
                                                        />
                                                    </div>
                                                    <button type="submit" className="inv-btn inv-btn-gold" disabled={paySubmitting} style={{ marginTop: '0.75rem' }}>
                                                        {paySubmitting ? 'Recording…' : `Record ${payAmount ? fmt(payAmount) : 'Payment'}`}
                                                    </button>
                                                </form>
                                            </>
                                        ) : (
                                            <p className="inv-pay-done">This invoice is now fully paid.</p>
                                        )}
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
