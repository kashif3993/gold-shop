import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { RefreshCw, AlertTriangle, Trash2 } from 'lucide-react';
import RateTicker from '@/components/rate-ticker';
import '../../../css/rate-management.css';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Rate Management', href: '/rate-management' }];

const fmt = (n: number | string | null | undefined) =>
    n == null ? '—' : 'Rs ' + new Intl.NumberFormat('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n));

function ago(iso?: string | null): string {
    if (!iso) return 'never';
    const mins = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins} min ago`;
    const hrs = Math.round(mins / 60);
    if (hrs < 24) return `${hrs} h ago`;
    return `${Math.round(hrs / 24)} d ago`;
}

const adjLabel = (type?: string | null, value?: number | null) => {
    if (value == null || value === 0) return <span className="rm-muted">none</span>;
    const sign = value > 0 ? '+' : '';
    const cls = value >= 0 ? 'rm-adj-pos' : 'rm-adj-neg';
    return <span className={cls}>{type === 'percent' ? `${sign}${value}%` : `${sign}${fmt(value)}`}</span>;
};

export default function RateManagement({ rates, metals, shopDefault, overrides, fetchLog, feedStale, staleAfterHours, fx }: any) {
    const { flash } = usePage().props as any;

    const refresh = useForm({});
    const fxForm = useForm({ usd_pkr: String(fx?.value ?? ''), auto: Boolean(fx?.auto) });
    const manual = useForm({ gold_spot: '', silver_spot: '' });
    const def = useForm({
        scope: 'shop',
        adjustment_type: shopDefault?.adjustment_type || 'amount',
        adjustment_value: String(shopDefault?.adjustment_value ?? 0),
        reason: '',
    });
    const ovr = useForm({
        scope: 'metal',
        metal_type_id: '',
        purity_id: '',
        adjustment_type: 'amount',
        adjustment_value: '0',
        reason: '',
    });

    const ovrPurities = useMemo(
        () => metals.find((m: any) => String(m.id) === String(ovr.data.metal_type_id))?.purities ?? [],
        [metals, ovr.data.metal_type_id],
    );

    const submitRefresh = () => refresh.post('/rate-management/refresh', { preserveScroll: true });
    const submitFx = (e: React.FormEvent) => {
        e.preventDefault();
        fxForm.post('/rate-management/fx', { preserveScroll: true });
    };
    const submitManual = (e: React.FormEvent) => {
        e.preventDefault();
        manual.post('/rate-management/manual', { preserveScroll: true, onSuccess: () => manual.reset() });
    };
    const submitDefault = (e: React.FormEvent) => {
        e.preventDefault();
        def.post('/rate-management/adjustment', { preserveScroll: true });
    };
    const submitOverride = (e: React.FormEvent) => {
        e.preventDefault();
        ovr.post('/rate-management/adjustment', {
            preserveScroll: true,
            onSuccess: () => ovr.reset('adjustment_value', 'reason', 'purity_id'),
        });
    };
    const removeOverride = (id: number) => {
        if (confirm('Remove this override?')) {
            router.delete(`/rate-management/adjustment/${id}`, { preserveScroll: true });
        }
    };

    // live preview for the shop default on a sample official rate
    const sample = rates.find((r: any) => r.raw)?.raw ?? 24000;
    const previewDefault = useMemo(() => {
        const v = Number(def.data.adjustment_value) || 0;
        return def.data.adjustment_type === 'percent' ? sample * (1 + v / 100) : sample + v;
    }, [def.data.adjustment_type, def.data.adjustment_value, sample]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Rate Management" />

            <div className="rm-page">
                <div className="rm-head">
                    <div>
                        <h1 className="rm-title">Rate Management</h1>
                        <p className="rm-desc">Today's gold &amp; silver rate — fetched, adjusted for your counter margin, and used across POS and buy-back.</p>
                    </div>
                    <button type="button" className="rm-btn rm-btn-gold" onClick={submitRefresh} disabled={refresh.processing}>
                        <RefreshCw size={15} /> {refresh.processing ? 'Fetching…' : 'Refresh now'}
                    </button>
                </div>

                <RateTicker className="rm-ticker" />

                {flash?.success && <div className="rm-flash rm-flash-success">{flash.success}</div>}
                {flash?.error && <div className="rm-flash rm-flash-error">{flash.error}</div>}

                {feedStale && (
                    <div className="rm-stale-banner">
                        <AlertTriangle size={17} />
                        <span>
                            The rate feed hasn't updated in over {staleAfterHours} h. Billing keeps working on the last cached rate —
                            use <strong>Refresh now</strong> or enter a manual rate below.
                        </span>
                    </div>
                )}

                {/* ── current rates ─────────────────────────────────────── */}
                <div className="rm-card">
                    <h2 className="rm-card-title">Current rates</h2>
                    <p className="rm-card-sub">Official = converted spot. Effective = what the shop bills at, after your adjustment.</p>
                    <div className="rm-table-wrap">
                        <table className="rm-table">
                            <thead>
                                <tr>
                                    <th>Metal</th>
                                    <th>Purity</th>
                                    <th>Fineness</th>
                                    <th>Official / g</th>
                                    <th>Adjustment</th>
                                    <th>Effective / g</th>
                                    <th>Source</th>
                                    <th>Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                {rates.map((r: any) => (
                                    <tr key={r.purity_id}>
                                        <td>{r.metal_name}</td>
                                        <td>{r.purity_name}</td>
                                        <td className="rm-num">{r.fineness_percent}%</td>
                                        <td className="rm-num">{fmt(r.raw)}</td>
                                        <td>{adjLabel(r.adjustment_type, r.adjustment_value)}</td>
                                        <td className="rm-effective">{fmt(r.effective)}</td>
                                        <td>
                                            {r.source
                                                ? <span className={`rm-badge rm-badge-${r.source}`}>{r.source}</span>
                                                : <span className="rm-muted">—</span>}
                                        </td>
                                        <td>
                                            {r.is_stale && <span className="rm-badge rm-badge-stale" style={{ marginRight: 6 }}>stale</span>}
                                            <span className="rm-muted">{ago(r.fetched_at)}</span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* ── USD → PKR ────────────────────────────────────────── */}
                <div className="rm-card">
                    <h2 className="rm-card-title">USD → PKR exchange rate</h2>
                    <p className="rm-card-sub">
                        gold-api.com gives prices in USD only. This is the rate used to convert to PKR — set it to the
                        current interbank rate, or let it auto-fetch with each rate update.
                    </p>
                    <form className="rm-form-row" onSubmit={submitFx}>
                        <div className="rm-field">
                            <label className="rm-label">1 USD = ? PKR</label>
                            <input
                                type="number" step="any" min="1" className="rm-input"
                                value={fxForm.data.usd_pkr}
                                onChange={e => fxForm.setData('usd_pkr', e.target.value)}
                                disabled={fxForm.data.auto}
                            />
                            {fxForm.errors.usd_pkr && <span className="rm-error">{fxForm.errors.usd_pkr}</span>}
                        </div>
                        <label className="rm-field" style={{ flexDirection: 'row', alignItems: 'center', gap: '0.5rem', paddingBottom: '0.55rem' }}>
                            <input
                                type="checkbox"
                                checked={fxForm.data.auto}
                                onChange={e => fxForm.setData('auto', e.target.checked)}
                            />
                            <span className="rm-label" style={{ textTransform: 'none', letterSpacing: 0 }}>Auto-fetch with each rate update</span>
                        </label>
                        <button type="submit" className="rm-btn rm-btn-gold" disabled={fxForm.processing}>
                            {fxForm.processing ? 'Saving…' : 'Save & refresh'}
                        </button>
                    </form>
                    <div className="rm-hint">
                        In use: <strong>1 USD = {Number(fx?.value ?? 0).toFixed(2)} PKR</strong>
                        {fx?.auto && fx?.fetched_at && <> · auto-fetched {ago(fx.fetched_at)}</>}
                        {!fx?.auto && <> · set manually</>}
                        {' · '}config default {Number(fx?.default ?? 0).toFixed(0)}
                    </div>
                </div>

                {/* ── manual entry ─────────────────────────────────────── */}
                <div className="rm-card">
                    <h2 className="rm-card-title">Manual rate entry</h2>
                    <p className="rm-card-sub">Type today's pure-metal spot (PKR per gram). Every purity is derived from its fineness, then your adjustment is applied.</p>
                    <form className="rm-form-row" onSubmit={submitManual}>
                        <div className="rm-field">
                            <label className="rm-label">Pure gold spot / g</label>
                            <input type="number" step="any" min="0" className="rm-input" placeholder="e.g. 24500"
                                value={manual.data.gold_spot} onChange={e => manual.setData('gold_spot', e.target.value)} />
                            {manual.errors.gold_spot && <span className="rm-error">{manual.errors.gold_spot}</span>}
                        </div>
                        <div className="rm-field">
                            <label className="rm-label">Pure silver spot / g</label>
                            <input type="number" step="any" min="0" className="rm-input" placeholder="e.g. 312"
                                value={manual.data.silver_spot} onChange={e => manual.setData('silver_spot', e.target.value)} />
                            {manual.errors.silver_spot && <span className="rm-error">{manual.errors.silver_spot}</span>}
                        </div>
                        <button type="submit" className="rm-btn rm-btn-gold" disabled={manual.processing}>Save manual rate</button>
                    </form>
                </div>

                {/* ── shop default adjustment ──────────────────────────── */}
                <div className="rm-card">
                    <h2 className="rm-card-title">Counter margin (shop default)</h2>
                    <p className="rm-card-sub">Applied to every metal / purity unless a more specific override exists. Use 0 to bill exactly at the official rate.</p>
                    <form className="rm-form-row" onSubmit={submitDefault}>
                        <input type="hidden" value="shop" />
                        <div className="rm-field">
                            <label className="rm-label">Type</label>
                            <select className="rm-select" value={def.data.adjustment_type} onChange={e => def.setData('adjustment_type', e.target.value)}>
                                <option value="amount">Amount (Rs / g)</option>
                                <option value="percent">Percent</option>
                            </select>
                        </div>
                        <div className="rm-field">
                            <label className="rm-label">Value</label>
                            <input type="number" step="any" className="rm-input"
                                value={def.data.adjustment_value} onChange={e => def.setData('adjustment_value', e.target.value)} />
                            {def.errors.adjustment_value && <span className="rm-error">{def.errors.adjustment_value}</span>}
                        </div>
                        <div className="rm-field" style={{ flex: 1 }}>
                            <label className="rm-label">Reason (optional)</label>
                            <input type="text" className="rm-input" style={{ width: '100%' }}
                                value={def.data.reason} onChange={e => def.setData('reason', e.target.value)} />
                        </div>
                        <button type="submit" className="rm-btn rm-btn-gold" disabled={def.processing}>Save default</button>
                    </form>
                    <div className="rm-preview">
                        On an official <strong>{fmt(sample)}</strong>/g this bills at <strong>{fmt(previewDefault)}</strong>/g.
                    </div>
                </div>

                {/* ── overrides ────────────────────────────────────────── */}
                <div className="rm-card">
                    <h2 className="rm-card-title">Overrides</h2>
                    <p className="rm-card-sub">A per-metal or per-purity adjustment that beats the shop default.</p>

                    {overrides.length > 0 && (
                        <div className="rm-table-wrap" style={{ marginBottom: '1.25rem' }}>
                            <table className="rm-table">
                                <thead>
                                    <tr><th>Scope</th><th>Metal</th><th>Purity</th><th>Adjustment</th><th></th></tr>
                                </thead>
                                <tbody>
                                    {overrides.map((o: any) => (
                                        <tr key={o.id}>
                                            <td style={{ textTransform: 'capitalize' }}>{o.scope}</td>
                                            <td>{o.metal_name || '—'}</td>
                                            <td>{o.purity_name || '—'}</td>
                                            <td>{adjLabel(o.adjustment_type, o.adjustment_value)}</td>
                                            <td style={{ textAlign: 'right' }}>
                                                <button type="button" className="rm-btn rm-btn-danger" onClick={() => removeOverride(o.id)}>
                                                    <Trash2 size={12} /> Remove
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    <form className="rm-form-row" onSubmit={submitOverride}>
                        <div className="rm-field">
                            <label className="rm-label">Scope</label>
                            <select className="rm-select" value={ovr.data.scope} onChange={e => ovr.setData('scope', e.target.value)}>
                                <option value="metal">Per metal</option>
                                <option value="purity">Per purity</option>
                            </select>
                        </div>
                        <div className="rm-field">
                            <label className="rm-label">Metal</label>
                            <select className="rm-select" value={ovr.data.metal_type_id}
                                onChange={e => { ovr.setData('metal_type_id', e.target.value); ovr.setData('purity_id', ''); }}>
                                <option value="">Select…</option>
                                {metals.map((m: any) => <option key={m.id} value={m.id}>{m.name}</option>)}
                            </select>
                            {ovr.errors.metal_type_id && <span className="rm-error">{ovr.errors.metal_type_id}</span>}
                        </div>
                        {ovr.data.scope === 'purity' && (
                            <div className="rm-field">
                                <label className="rm-label">Purity</label>
                                <select className="rm-select" value={ovr.data.purity_id} onChange={e => ovr.setData('purity_id', e.target.value)}>
                                    <option value="">Select…</option>
                                    {ovrPurities.map((p: any) => <option key={p.id} value={p.id}>{p.name}</option>)}
                                </select>
                                {ovr.errors.purity_id && <span className="rm-error">{ovr.errors.purity_id}</span>}
                            </div>
                        )}
                        <div className="rm-field">
                            <label className="rm-label">Type</label>
                            <select className="rm-select" value={ovr.data.adjustment_type} onChange={e => ovr.setData('adjustment_type', e.target.value)}>
                                <option value="amount">Amount</option>
                                <option value="percent">Percent</option>
                            </select>
                        </div>
                        <div className="rm-field">
                            <label className="rm-label">Value</label>
                            <input type="number" step="any" className="rm-input"
                                value={ovr.data.adjustment_value} onChange={e => ovr.setData('adjustment_value', e.target.value)} />
                        </div>
                        <button type="submit" className="rm-btn rm-btn-gold" disabled={ovr.processing}>Add override</button>
                    </form>
                </div>

                {/* ── fetch history ────────────────────────────────────── */}
                <div className="rm-card">
                    <h2 className="rm-card-title">Fetch history</h2>
                    <p className="rm-card-sub">Every scheduled and manual fetch attempt, so an outage is visible.</p>
                    <div className="rm-table-wrap">
                        <table className="rm-table">
                            <thead>
                                <tr><th>When</th><th>Result</th><th>Source</th><th>Detail</th></tr>
                            </thead>
                            <tbody>
                                {fetchLog.length === 0 ? (
                                    <tr><td colSpan={4} className="rm-muted" style={{ textAlign: 'center', padding: '1.5rem' }}>No fetches yet.</td></tr>
                                ) : fetchLog.map((l: any) => (
                                    <tr key={l.id}>
                                        <td className="rm-muted">{new Date(l.attempted_at).toLocaleString()}</td>
                                        <td>
                                            {l.success
                                                ? <span className="rm-badge rm-badge-ok">ok</span>
                                                : <span className="rm-badge rm-badge-fail">failed</span>}
                                            {l.fallback_used ? <span className="rm-badge rm-badge-manual" style={{ marginLeft: 6 }}>fallback</span> : null}
                                        </td>
                                        <td>{l.source_api}</td>
                                        <td className="rm-muted">{l.response_summary}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
