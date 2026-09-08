import { Head, router } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Eye, Printer, X } from 'lucide-react';
import InvoiceSheet from '@/components/invoice-sheet';
import '../../../css/invoices.css';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Invoices', href: '/invoices' }];

const fmt = (n: number | string) =>
    'Rs ' + new Intl.NumberFormat('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n) || 0);

const prettyMethod = (m: string) => (m || '').replace('_', ' ');

interface Detail {
    invoice: any;
    subtotal: number;
    exchangeLines: any[];
    shop: any;
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
        router.get('/invoices', {
            search, payment_method: method, date_from: dateFrom, date_to: dateTo,
        }, { preserveState: true, preserveScroll: true });
    };

    const clearFilters = () => {
        setSearch(''); setMethod(''); setDateFrom(''); setDateTo('');
        router.get('/invoices');
    };

    const openInvoice = useCallback((id: number) => {
        setOpenId(id);
        setDetail(null);
        setError('');
        setLoading(true);
        fetch(`/invoices/${id}/data`, { headers: { Accept: 'application/json' } })
            .then(r => {
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

    useEffect(() => {
        if (openId === null) return;
        const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') closeModal(); };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [openId, closeModal]);

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
                        <input className="inv-filter-input" value={search} onChange={e => setSearch(e.target.value)} placeholder="INV-2026... or name" />
                    </div>
                    <div className="inv-filter-group">
                        <label className="inv-filter-label">Payment Method</label>
                        <select className="inv-filter-input" value={method} onChange={e => setMethod(e.target.value)}>
                            <option value="">All</option>
                            {paymentMethods.map((m: string) => (
                                <option key={m} value={m} style={{ textTransform: 'capitalize' }}>{prettyMethod(m)}</option>
                            ))}
                        </select>
                    </div>
                    <div className="inv-filter-group">
                        <label className="inv-filter-label">From</label>
                        <input type="date" className="inv-filter-input" value={dateFrom} onChange={e => setDateFrom(e.target.value)} />
                    </div>
                    <div className="inv-filter-group">
                        <label className="inv-filter-label">To</label>
                        <input type="date" className="inv-filter-input" value={dateTo} onChange={e => setDateTo(e.target.value)} />
                    </div>
                    <button type="submit" className="inv-filter-btn">Apply</button>
                    {hasFilters && <button type="button" className="inv-filter-clear" onClick={clearFilters}>Clear</button>}
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
                                    <th style={{ textAlign: 'right' }}>Discount</th>
                                    <th style={{ textAlign: 'right' }}>Grand Total</th>
                                    <th style={{ textAlign: 'right' }}>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {invoices.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="inv-empty">No invoices match your filters.</td>
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
                                            <td><span className={`inv-pay-badge ${inv.payment_method}`}>{prettyMethod(inv.payment_method)}</span></td>
                                            <td style={{ textAlign: 'right' }} className="inv-money">
                                                {Number(inv.total_discount) > 0 ? fmt(inv.total_discount) : <span className="inv-muted">-</span>}
                                            </td>
                                            <td style={{ textAlign: 'right' }} className="inv-money">{fmt(inv.grand_total)}</td>
                                            <td style={{ textAlign: 'right' }}>
                                                <button type="button" className="inv-view-btn" onClick={() => openInvoice(inv.id)}>
                                                    <Eye size={14} /> View
                                                </button>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {invoices.total > 0 && (
                        <div className="inv-pagination">
                            <div>Showing {invoices.from || 0} to {invoices.to || 0} of {invoices.total} invoices</div>
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
                        <div className="inv-modal" onMouseDown={e => e.stopPropagation()}>
                            <div className="inv-modal-head">
                                <h3>Invoice</h3>
                                <div className="inv-modal-head-actions">
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
            </div>
        </AppLayout>
    );
}
