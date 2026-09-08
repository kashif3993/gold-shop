import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { ArrowLeft, Edit, Trash2, Phone, MapPin } from 'lucide-react';
import { partyTypeClass } from './index';
import '../../../css/parties.css';

const fmt = (n: number | string) =>
    'Rs ' + new Intl.NumberFormat('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n) || 0);

function balancePillClass(balance: number): string {
    if (balance > 0) return 'pty-balance-pill pty-balance-receivable';
    if (balance < 0) return 'pty-balance-pill pty-balance-payable';
    return 'pty-balance-pill pty-balance-settled';
}

export default function PartyShow({ party, ledger, sourcedItems = [] }: any) {
    const { flash } = usePage().props as any;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Parties', href: '/parties' },
        { title: party.name, href: `/parties/${party.id}` },
    ];

    const handleDelete = () => {
        if (confirm(`Delete party "${party.name}"? This cannot be undone.`)) {
            router.delete(`/parties/${party.id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={party.name} />

            <div className="pty-page">
                <Link href="/parties" className="pty-back">
                    <ArrowLeft size={15} /> Back to Parties
                </Link>

                {flash?.error && <div className="pty-flash pty-flash-error">{flash.error}</div>}
                {flash?.success && <div className="pty-flash pty-flash-success">{flash.success}</div>}

                <div className="pty-detail-head">
                    <div>
                        <h1 className="pty-detail-name">{party.name}</h1>
                        <div className="pty-detail-meta">
                            <span className={partyTypeClass(party.party_type?.name)}>{party.party_type?.name}</span>
                            {party.is_active ? <span className="pty-active">Active</span> : <span className="pty-inactive-tag">Inactive</span>}
                            {party.phone && <span className="meta-chip"><Phone size={13} /> {party.phone}</span>}
                            {party.address && <span className="meta-chip"><MapPin size={13} /> {party.address}</span>}
                        </div>
                    </div>
                    <div className="pty-detail-actions">
                        <Link href={`/parties/${party.id}/edit`} className="pty-btn pty-btn-ghost"><Edit size={15} /> Edit</Link>
                        <button type="button" className="pty-btn pty-btn-danger" onClick={handleDelete}><Trash2 size={15} /> Delete</button>
                    </div>
                </div>

                {party.notes && (
                    <div className="pty-note">
                        {party.notes}
                    </div>
                )}

                {/* Ledger summary */}
                <div className="pty-summary-grid">
                    <div className="pty-stat">
                        <div className="pty-stat-label">Billed to party (OUT)</div>
                        <div className="pty-stat-value out">{fmt(ledger.total_out)}</div>
                    </div>
                    <div className="pty-stat">
                        <div className="pty-stat-label">Received from party (IN)</div>
                        <div className="pty-stat-value in">{fmt(ledger.total_in)}</div>
                    </div>
                    <div className={balancePillClass(ledger.balance)}>
                        <span className="amt">{fmt(Math.abs(ledger.balance))}</span>
                        <span className="lbl">{ledger.balance_label}</span>
                    </div>
                </div>

                {/* Ledger table */}
                <h2 className="pty-section-title">Ledger — {ledger.entry_count} entr{ledger.entry_count === 1 ? 'y' : 'ies'}</h2>
                <div className="pty-table-wrap">
                    <div className="pty-table-scroll">
                    <table className="pty-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Reference</th>
                                <th style={{ textAlign: 'right' }}>Debit (OUT)</th>
                                <th style={{ textAlign: 'right' }}>Credit (IN)</th>
                                <th style={{ textAlign: 'right' }}>Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            {ledger.entries.length === 0 ? (
                                <tr>
                                    <td colSpan={6} style={{ padding: 0 }}>
                                        <div className="pty-ledger-empty">No dealings recorded for this party yet.</div>
                                    </td>
                                </tr>
                            ) : (
                                ledger.entries.map((row: any, i: number) => (
                                    <tr key={i}>
                                        <td>{row.date ? new Date(row.date).toLocaleDateString() : '-'}</td>
                                        <td style={{ textTransform: 'capitalize' }}>{row.description}</td>
                                        <td>{row.reference}</td>
                                        <td style={{ textAlign: 'right' }}>
                                            {row.debit ? <span className="led-debit">{fmt(row.debit)}</span> : <span className="led-muted">-</span>}
                                        </td>
                                        <td style={{ textAlign: 'right' }}>
                                            {row.credit ? <span className="led-credit">{fmt(row.credit)}</span> : <span className="led-muted">-</span>}
                                        </td>
                                        <td style={{ textAlign: 'right' }} className="led-balance">{fmt(row.balance)}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                    </div>
                </div>

                {/* Items sourced from this party */}
                {sourcedItems.length > 0 && (
                    <>
                        <h2 className="pty-section-title">Items sourced from this party (latest 10)</h2>
                        <div className="pty-table-wrap">
                            <div className="pty-table-scroll">
                            <table className="pty-table">
                                <thead>
                                    <tr>
                                        <th>Item Code</th>
                                        <th>Type & Metal</th>
                                        <th style={{ textAlign: 'right' }}>Net Weight</th>
                                        <th style={{ textAlign: 'right' }}>Purchase Price</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {sourcedItems.map((item: any) => (
                                        <tr key={item.id}>
                                            <td>
                                                <Link href={`/items/${item.id}/edit`} className="pty-link">
                                                    {item.item_code}
                                                </Link>
                                            </td>
                                            <td style={{ textTransform: 'capitalize' }}>{item.item_type} · {item.metal_type?.name} {item.purity?.name}</td>
                                            <td style={{ textAlign: 'right' }} className="pty-weight-text">{Number(item.net_weight_grams).toFixed(3)} g</td>
                                            <td style={{ textAlign: 'right' }} className="pty-price-text">{fmt(item.purchase_price)}</td>
                                            <td style={{ textTransform: 'capitalize' }}>{String(item.status).replace('_', ' ')}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
