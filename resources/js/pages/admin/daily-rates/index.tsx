import { LineChart } from '@/components/simple-charts';
import AdminLayout from '@/layouts/admin-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import '../../../../css/admin.css';
import '../../../../css/simple-charts.css';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin' },
    { title: 'Daily Rates', href: '/admin/daily-rates' },
];

const fmt = (n: number | string | null) =>
    n == null ? '—' : 'Rs ' + new Intl.NumberFormat('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n));

const metalColor = (metal: string) => {
    const key = metal.toLowerCase();
    if (key === 'gold') return '#d4af37';
    if (key === 'silver') return '#9ca3af';
    return '#6366f1';
};

interface Metal {
    id: number;
    name: string;
    purities: { id: number; name: string; metal_type_id: number }[];
}

interface ChartSeries {
    metal: string;
    purity: string;
    points: { date: string; rate: number }[];
}

export default function DailyRatesIndex({
    rates,
    filters,
    metals,
    chartSeries,
}: {
    rates: any;
    filters: any;
    metals: Metal[];
    chartSeries: ChartSeries[];
}) {
    const { flash } = usePage().props as any;
    const [metalId, setMetalId] = useState(filters.metal_type_id || '');
    const [purityId, setPurityId] = useState(filters.purity_id || '');
    const [source, setSource] = useState(filters.source || '');
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');

    const availablePurities = metals.find((m) => String(m.id) === String(metalId))?.purities ?? [];

    const applyFilters = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/admin/daily-rates',
            {
                metal_type_id: metalId,
                purity_id: purityId,
                source,
                date_from: dateFrom,
                date_to: dateTo,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const clearFilters = () => {
        setMetalId('');
        setPurityId('');
        setSource('');
        setDateFrom('');
        setDateTo('');
        router.get('/admin/daily-rates');
    };

    const hasFilters = metalId || purityId || source || dateFrom || dateTo;

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title="Daily Rate History" />

            <div className="adm-page">
                <div className="adm-head">
                    <h1 className="adm-title">Daily rate history</h1>
                    <p className="adm-desc">Every rate ever stored — API fetches, manual entries, and admin overrides — not just today's.</p>
                </div>

                {flash?.success && <div className="adm-flash adm-flash-success">{flash.success}</div>}
                {flash?.error && <div className="adm-flash adm-flash-error">{flash.error}</div>}

                <form className="adm-card adm-form-row" onSubmit={applyFilters}>
                    <div className="adm-field">
                        <label className="adm-label">Metal</label>
                        <select
                            className="adm-select"
                            value={metalId}
                            onChange={(e) => {
                                setMetalId(e.target.value);
                                setPurityId('');
                            }}
                        >
                            <option value="">All</option>
                            {metals.map((m) => (
                                <option key={m.id} value={m.id}>
                                    {m.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="adm-field">
                        <label className="adm-label">Purity</label>
                        <select className="adm-select" value={purityId} onChange={(e) => setPurityId(e.target.value)}>
                            <option value="">All</option>
                            {availablePurities.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="adm-field">
                        <label className="adm-label">Source</label>
                        <select className="adm-select" value={source} onChange={(e) => setSource(e.target.value)}>
                            <option value="">All</option>
                            <option value="api">API</option>
                            <option value="manual">Manual / Override</option>
                        </select>
                    </div>
                    <div className="adm-field">
                        <label className="adm-label">From</label>
                        <input type="date" className="adm-input" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} />
                    </div>
                    <div className="adm-field">
                        <label className="adm-label">To</label>
                        <input type="date" className="adm-input" value={dateTo} onChange={(e) => setDateTo(e.target.value)} />
                    </div>
                    <button type="submit" className="adm-btn adm-btn-gold">
                        Apply
                    </button>
                    {hasFilters && (
                        <button type="button" className="adm-btn adm-btn-ghost" onClick={clearFilters}>
                            Clear
                        </button>
                    )}
                </form>

                <div className="adm-card">
                    <h2 className="adm-card-title">Rate trend</h2>
                    <p className="adm-card-sub">One line per purity currently in view, matching the filters above.</p>
                    <LineChart
                        series={chartSeries.map((s) => ({
                            name: `${s.metal} (${s.purity})`,
                            color: metalColor(s.metal),
                            points: s.points.map((p) => ({ x: p.date, y: p.rate })),
                        }))}
                    />
                </div>

                <div className="adm-card">
                    <div className="adm-table-wrap">
                        <table className="adm-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Metal</th>
                                    <th>Purity</th>
                                    <th style={{ textAlign: 'right' }}>Rate / g</th>
                                    <th>Source</th>
                                    <th>By</th>
                                    <th>Fetched</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rates.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={7} className="adm-empty">
                                            No rates match your filters.
                                        </td>
                                    </tr>
                                ) : (
                                    rates.data.map((r: any) => (
                                        <tr key={r.id}>
                                            <td>{r.rate_date}</td>
                                            <td>{r.metal_type?.name ?? '—'}</td>
                                            <td>{r.purity?.name ?? '—'}</td>
                                            <td style={{ textAlign: 'right' }} className="adm-num">
                                                {fmt(r.rate_per_gram)}
                                            </td>
                                            <td>
                                                <span className={`adm-badge ${r.source === 'api' ? 'adm-badge-api' : 'adm-badge-manual'}`}>
                                                    {r.source}
                                                </span>
                                            </td>
                                            <td>{r.entered_by?.username ?? <span className="adm-muted">system</span>}</td>
                                            <td className="adm-muted">{r.fetched_at ? new Date(r.fetched_at).toLocaleString() : '—'}</td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>

                    {rates.total > 0 && (
                        <div className="adm-pagination">
                            <div>
                                Showing {rates.from} to {rates.to} of {rates.total}
                            </div>
                            {rates.links && rates.links.length > 3 && (
                                <div className="adm-page-links">
                                    {rates.links.map((link: any, i: number) => (
                                        <button
                                            key={i}
                                            type="button"
                                            disabled={!link.url}
                                            onClick={() => link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                                            className={`adm-page-link ${link.active ? 'active' : ''}`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </AdminLayout>
    );
}
