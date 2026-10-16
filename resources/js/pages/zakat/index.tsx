import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { carryTraditional, formatNumber, gramsToTraditional, traditionalToGrams } from '@/utils/weight';
import { Head } from '@inertiajs/react';
import { Info } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import '../../../css/zakat.css';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Zakat', href: '/zakat' }];

const fmt = (n: number | string) =>
    'Rs ' + new Intl.NumberFormat('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n) || 0);

interface Metal {
    id: number;
    name: string;
}
interface Purity {
    id: number;
    name: string;
    metal_type_id: number;
}
interface NisabSide {
    grams: number;
    rate_per_gram: number;
    value: number;
}
interface Nisab {
    gold: NisabSide | null;
    silver: NisabSide | null;
    threshold: number;
}
interface ByMetal {
    metal: string;
    item_count: number;
    weight_grams: number;
    value: number;
}
interface ShopStock {
    stock_value: number;
    by_metal: ByMetal[];
    nisab: Nisab;
    meets_nisab: boolean;
    zakat_due: number;
}

const EMPTY_WEIGHT = { gram: '', tola: '', masha: '', ratti: '', point: '' };

export default function ZakatIndex({
    metals = [],
    purities = [],
    currentRates = {},
    ratePercent,
    nisab,
    shopStock,
}: {
    metals: Metal[];
    purities: Purity[];
    currentRates: Record<string, number>;
    ratePercent: number;
    nisab: Nisab;
    shopStock: ShopStock;
}) {
    const [metalTypeId, setMetalTypeId] = useState<string>(metals[0] ? String(metals[0].id) : '');
    const [purityId, setPurityId] = useState<string>('');
    const [weight, setWeight] = useState({ ...EMPTY_WEIGHT });
    const [heldOneYear, setHeldOneYear] = useState(false);

    const availablePurities = useMemo(
        () => purities.filter((p) => !metalTypeId || String(p.metal_type_id) === String(metalTypeId)),
        [purities, metalTypeId],
    );

    useEffect(() => {
        if (purityId && !availablePurities.some((p) => String(p.id) === purityId)) {
            setPurityId('');
        } else if (!purityId && availablePurities[0]) {
            setPurityId(String(availablePurities[0].id));
        }
    }, [availablePurities, purityId]);

    function setWeightUnit(unit: keyof typeof EMPTY_WEIGHT, value: string) {
        if (unit === 'gram') {
            setWeight({ gram: value, ...gramsToTraditional(parseFloat(value) || 0) });
            return;
        }
        const draft = { ...weight, [unit]: value };
        const needsCarry = (parseFloat(draft.point) || 0) >= 100 || (parseFloat(draft.ratti) || 0) >= 8 || (parseFloat(draft.masha) || 0) >= 12;
        const next: any = needsCarry ? { ...draft, ...carryTraditional(draft) } : draft;
        const g = traditionalToGrams(next);
        const allEmpty = !next.tola && !next.masha && !next.ratti && !next.point;
        next.gram = allEmpty ? '' : formatNumber(g, 3);
        setWeight(next);
    }

    const grams = parseFloat(weight.gram) || 0;
    // A purity can exist without ever having had a rate set (new purity, or
    // Rate Management never touched) — that's a real error, not a Rs 0 value,
    // so it's tracked separately instead of quietly defaulting the rate to 0.
    const rateMissing = !!purityId && currentRates[purityId] == null;
    const rate = rateMissing ? 0 : (currentRates[purityId] ?? 0);
    const value = +(grams * rate).toFixed(2);
    const meetsNisab = value >= nisab.threshold && nisab.threshold > 0;
    const zakatDue = meetsNisab ? +((value * ratePercent) / 100).toFixed(2) : 0;
    const purityName = availablePurities.find((p) => String(p.id) === purityId)?.name;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Zakat" />
            <div className="zk-page">
                <div className="zk-header">
                    <h1 className="zk-title">Zakat Calculator</h1>
                    <p className="zk-desc">
                        Live Nisab, the shop's own stock, and a quick calculator for anyone's gold or silver — all priced at today's rate.
                    </p>
                </div>

                <div className="zk-notice">
                    <Info size={18} />
                    <div>
                        <strong>Zakat applies only once wealth has been held for a full Islamic (lunar) year.</strong>
                        The figures below show what's due if that condition is already met — they don't track dates for you. Rate ({ratePercent}%) and
                        Nisab thresholds are set by an admin in Settings.
                    </div>
                </div>

                <div className="zk-grid">
                    <div>
                        {/* ── Nisab ─────────────────────────────────────── */}
                        <div className="zk-card">
                            <h2 className="zk-card-title">Nisab (today)</h2>
                            <p className="zk-card-sub">The minimum holding before Zakat is due at all — whichever is lower of the two.</p>
                            <div className="zk-nisab-grid">
                                <div className="zk-nisab-tile">
                                    <div className="zk-nisab-label">Gold Nisab</div>
                                    {nisab.gold ? (
                                        <>
                                            <div className="zk-nisab-value">{fmt(nisab.gold.value)}</div>
                                            <div className="zk-nisab-sub">
                                                {nisab.gold.grams}g at {fmt(nisab.gold.rate_per_gram)}/g
                                            </div>
                                        </>
                                    ) : (
                                        <div className="zk-nisab-sub">No live gold rate available</div>
                                    )}
                                </div>
                                <div className="zk-nisab-tile">
                                    <div className="zk-nisab-label">Silver Nisab</div>
                                    {nisab.silver ? (
                                        <>
                                            <div className="zk-nisab-value">{fmt(nisab.silver.value)}</div>
                                            <div className="zk-nisab-sub">
                                                {nisab.silver.grams}g at {fmt(nisab.silver.rate_per_gram)}/g
                                            </div>
                                        </>
                                    ) : (
                                        <div className="zk-nisab-sub">No live silver rate available</div>
                                    )}
                                </div>
                                <div className="zk-nisab-tile zk-threshold">
                                    <div className="zk-nisab-label">Threshold used</div>
                                    <div className="zk-nisab-value">{fmt(nisab.threshold)}</div>
                                    <div className="zk-nisab-sub">Zakat is due above this value</div>
                                </div>
                            </div>
                        </div>

                        {/* ── Shop stock ────────────────────────────────── */}
                        <div className="zk-card">
                            <h2 className="zk-card-title">Shop's Own Stock</h2>
                            <p className="zk-card-sub">Every item currently in stock, valued live — same numbers as the stock valuation report.</p>

                            <div className="zk-sum-row">
                                <span>Total stock value</span>
                                <span>{fmt(shopStock.stock_value)}</span>
                            </div>
                            <div className="zk-sum-row">
                                <span>Meets Nisab?</span>
                                <span>
                                    <span className={`zk-badge ${shopStock.meets_nisab ? 'zk-badge-due' : 'zk-badge-clear'}`}>
                                        {shopStock.meets_nisab ? 'Yes' : 'No'}
                                    </span>
                                </span>
                            </div>
                            <div className="zk-sum-row">
                                <span>Zakat due (if held one year)</span>
                                <span>{fmt(shopStock.zakat_due)}</span>
                            </div>

                            {shopStock.by_metal.length > 0 && (
                                <div className="zk-table-wrap">
                                    <table className="zk-table">
                                        <thead>
                                            <tr>
                                                <th>Metal</th>
                                                <th>Items</th>
                                                <th>Weight</th>
                                                <th>Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {shopStock.by_metal.map((row) => (
                                                <tr key={row.metal}>
                                                    <td>{row.metal}</td>
                                                    <td>{row.item_count}</td>
                                                    <td>{formatNumber(row.weight_grams, 3)} g</td>
                                                    <td>{fmt(row.value)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>

                        {/* ── Manual calculator ─────────────────────────── */}
                        <div className="zk-card">
                            <h2 className="zk-card-title">Calculate for Any Gold/Silver</h2>
                            <p className="zk-card-sub">For a customer's own holdings, or a quick personal check — not tied to shop stock.</p>

                            <div className="zk-field-grid">
                                <div className="zk-field">
                                    <label className="zk-label">Metal</label>
                                    <select
                                        className="zk-select"
                                        value={metalTypeId}
                                        onChange={(e) => {
                                            setMetalTypeId(e.target.value);
                                            setPurityId('');
                                        }}
                                    >
                                        {metals.map((m) => (
                                            <option key={m.id} value={m.id}>
                                                {m.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="zk-field">
                                    <label className="zk-label">Purity</label>
                                    <select className="zk-select" value={purityId} onChange={(e) => setPurityId(e.target.value)}>
                                        {availablePurities.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="zk-field zk-col-2">
                                    <label className="zk-label">Weight</label>
                                    <div className="zk-weight-grid">
                                        {(['gram', 'tola', 'masha', 'ratti', 'point'] as const).map((u) => (
                                            <div key={u} className="zk-field">
                                                <label
                                                    className="zk-label"
                                                    style={{ textTransform: 'capitalize', fontWeight: 500, fontSize: '0.72rem' }}
                                                >
                                                    {u}
                                                </label>
                                                <input
                                                    type="number"
                                                    step="any"
                                                    min="0"
                                                    className="zk-input"
                                                    value={(weight as any)[u]}
                                                    onChange={(e) => setWeightUnit(u, e.target.value)}
                                                />
                                            </div>
                                        ))}
                                    </div>
                                    {purityId && currentRates[purityId] != null && (
                                        <div className="zk-hint">
                                            Today's {purityName} rate: {fmt(currentRates[purityId])}/g
                                        </div>
                                    )}
                                </div>
                            </div>

                            <label className="zk-checkbox-row">
                                <input type="checkbox" checked={heldOneYear} onChange={(e) => setHeldOneYear(e.target.checked)} />
                                I've owned this for a full Islamic (lunar) year
                            </label>
                        </div>
                    </div>

                    {/* ── result ────────────────────────────────────────── */}
                    <div>
                        <div className="zk-summary">
                            <div className="zk-summary-head">Zakat Result</div>
                            <div className="zk-summary-body">
                                <div className="zk-sum-row">
                                    <span>Weight</span>
                                    <span>{formatNumber(grams, 3)} g</span>
                                </div>

                                {rateMissing ? (
                                    <div className="zk-error-box">
                                        No live rate is set for {purityName ?? 'this purity'} yet, so Zakat can't be calculated for it. An admin needs
                                        to set a rate in Rate Management first.
                                    </div>
                                ) : (
                                    <>
                                        <div className="zk-sum-row">
                                            <span>Rate applied</span>
                                            <span>{fmt(rate)}</span>
                                        </div>
                                        <div className="zk-sum-row">
                                            <span>Value today</span>
                                            <span>{fmt(value)}</span>
                                        </div>
                                        <hr className="zk-sum-divider" />
                                        <div className={`zk-payout ${meetsNisab && heldOneYear ? '' : 'zk-payout-clear'}`}>
                                            <div className="zk-payout-label">
                                                {!meetsNisab
                                                    ? 'Below Nisab — no Zakat due'
                                                    : !heldOneYear
                                                      ? 'Zakat due once one year has passed'
                                                      : 'Zakat due now'}
                                            </div>
                                            <div className="zk-payout-value">{fmt(meetsNisab && heldOneYear ? zakatDue : 0)}</div>
                                        </div>

                                        {meetsNisab && !heldOneYear && (
                                            <div className="zk-note">
                                                This value clears Nisab ({fmt(nisab.threshold)}), so {fmt(zakatDue)} will be due once you've held it
                                                for a full lunar year — check the box above once that's true.
                                            </div>
                                        )}
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
