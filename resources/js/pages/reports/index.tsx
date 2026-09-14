import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import '../../../css/reports.css';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Reports', href: '/reports' }];

const fmt = (n: number | string) =>
    'Rs ' + new Intl.NumberFormat('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n) || 0);

const fmtWeight = (n: number | string) => `${new Intl.NumberFormat('en-PK', { maximumFractionDigits: 3 }).format(Number(n) || 0)} g`;

interface MetalRow {
    metal: string;
    [key: string]: any;
}

interface Filters {
    date_from: string;
    date_to: string;
}

interface Sales {
    count: number;
    revenue: number;
    by_day: { date: string; count: number; revenue: number }[];
    by_metal: MetalRow[];
}

interface Profit {
    revenue: number;
    cost: number;
    profit: number;
    by_metal: MetalRow[];
}

interface Stock {
    item_count: number;
    weight_grams: number;
    value: number;
    by_metal: MetalRow[];
}

export default function ReportsIndex({ filters, sales, profit, stock }: { filters: Filters; sales: Sales; profit: Profit; stock: Stock }) {
    const [dateFrom, setDateFrom] = useState(filters.date_from);
    const [dateTo, setDateTo] = useState(filters.date_to);

    const applyFilters = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/reports', { date_from: dateFrom, date_to: dateTo }, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Reports" />

            <div className="rpt-page">
                <div className="rpt-head">
                    <div>
                        <h1 className="rpt-title">Reports</h1>
                        <p className="rpt-desc">Sales, profit, and stock valuation — every number here is computed on the server from your actual transactions and current rates.</p>
                    </div>
                </div>

                <form className="rpt-filters" onSubmit={applyFilters}>
                    <div className="rpt-filter-group">
                        <label className="rpt-filter-label">From</label>
                        <input type="date" className="rpt-filter-input" value={dateFrom} onChange={e => setDateFrom(e.target.value)} />
                    </div>
                    <div className="rpt-filter-group">
                        <label className="rpt-filter-label">To</label>
                        <input type="date" className="rpt-filter-input" value={dateTo} onChange={e => setDateTo(e.target.value)} />
                    </div>
                    <button type="submit" className="rpt-filter-btn">Apply</button>
                    <span className="rpt-filter-hint">Applies to Sales and Profit below. Stock valuation is always as of right now.</span>
                </form>

                {/* ── Sales ─────────────────────────────────────────── */}
                <section className="rpt-card">
                    <h2 className="rpt-card-title">Sales</h2>
                    <p className="rpt-card-sub">Every &ldquo;sale&rdquo; transaction billed through POS in the selected range.</p>

                    <div className="rpt-stat-grid">
                        <div className="rpt-stat">
                            <div className="rpt-stat-label">Invoiced items sold</div>
                            <div className="rpt-stat-value">{sales.count}</div>
                        </div>
                        <div className="rpt-stat">
                            <div className="rpt-stat-label">Revenue</div>
                            <div className="rpt-stat-value gold">{fmt(sales.revenue)}</div>
                        </div>
                    </div>

                    <MetalTable
                        rows={sales.by_metal}
                        columns={[
                            { key: 'count', label: 'Items' },
                            { key: 'weight_grams', label: 'Weight', format: fmtWeight },
                            { key: 'revenue', label: 'Revenue', format: fmt },
                        ]}
                    />

                    {sales.by_day.length > 0 && (
                        <div className="rpt-table-wrap" style={{ marginTop: '1rem' }}>
                            <table className="rpt-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th style={{ textAlign: 'right' }}>Items</th>
                                        <th style={{ textAlign: 'right' }}>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {sales.by_day.map(row => (
                                        <tr key={row.date}>
                                            <td>{new Date(row.date).toLocaleDateString()}</td>
                                            <td style={{ textAlign: 'right' }} className="rpt-num">{row.count}</td>
                                            <td style={{ textAlign: 'right' }} className="rpt-num">{fmt(row.revenue)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                {/* ── Profit ────────────────────────────────────────── */}
                <section className="rpt-card">
                    <h2 className="rpt-card-title">Profit</h2>
                    <p className="rpt-card-sub">Sale price minus each item&rsquo;s own locked purchase cost — not an estimate, the exact cost recorded at Item Entry.</p>

                    <div className="rpt-stat-grid">
                        <div className="rpt-stat">
                            <div className="rpt-stat-label">Revenue</div>
                            <div className="rpt-stat-value">{fmt(profit.revenue)}</div>
                        </div>
                        <div className="rpt-stat">
                            <div className="rpt-stat-label">Cost</div>
                            <div className="rpt-stat-value">{fmt(profit.cost)}</div>
                        </div>
                        <div className="rpt-stat">
                            <div className="rpt-stat-label">Profit</div>
                            <div className={`rpt-stat-value ${profit.profit >= 0 ? 'gold' : 'negative'}`}>{fmt(profit.profit)}</div>
                        </div>
                    </div>

                    <MetalTable
                        rows={profit.by_metal}
                        columns={[
                            { key: 'count', label: 'Items' },
                            { key: 'revenue', label: 'Revenue', format: fmt },
                            { key: 'cost', label: 'Cost', format: fmt },
                            { key: 'profit', label: 'Profit', format: fmt },
                        ]}
                    />
                </section>

                {/* ── Stock valuation ──────────────────────────────── */}
                <section className="rpt-card">
                    <h2 className="rpt-card-title">Stock valuation</h2>
                    <p className="rpt-card-sub">Everything currently in stock, valued at today&rsquo;s live rate — not what it cost when purchased.</p>

                    <div className="rpt-stat-grid">
                        <div className="rpt-stat">
                            <div className="rpt-stat-label">Items in stock</div>
                            <div className="rpt-stat-value">{stock.item_count}</div>
                        </div>
                        <div className="rpt-stat">
                            <div className="rpt-stat-label">Total weight</div>
                            <div className="rpt-stat-value">{fmtWeight(stock.weight_grams)}</div>
                        </div>
                        <div className="rpt-stat">
                            <div className="rpt-stat-label">Value at today&rsquo;s rate</div>
                            <div className="rpt-stat-value gold">{fmt(stock.value)}</div>
                        </div>
                    </div>

                    <MetalTable
                        rows={stock.by_metal}
                        columns={[
                            { key: 'item_count', label: 'Items' },
                            { key: 'weight_grams', label: 'Weight', format: fmtWeight },
                            { key: 'value', label: 'Value', format: fmt },
                        ]}
                    />
                </section>
            </div>
        </AppLayout>
    );
}

function MetalTable({ rows, columns }: { rows: MetalRow[]; columns: { key: string; label: string; format?: (n: number) => string }[] }) {
    if (rows.length === 0) {
        return <div className="rpt-empty">No data for this range.</div>;
    }

    return (
        <div className="rpt-table-wrap">
            <table className="rpt-table">
                <thead>
                    <tr>
                        <th>Metal</th>
                        {columns.map(c => <th key={c.key} style={{ textAlign: 'right' }}>{c.label}</th>)}
                    </tr>
                </thead>
                <tbody>
                    {rows.map(row => (
                        <tr key={row.metal}>
                            <td>{row.metal}</td>
                            {columns.map(c => (
                                <td key={c.key} style={{ textAlign: 'right' }} className="rpt-num">
                                    {c.format ? c.format(row[c.key]) : row[c.key]}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
