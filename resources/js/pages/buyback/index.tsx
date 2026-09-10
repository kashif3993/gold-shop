import { Head, Link } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import axios from 'axios';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { AlertTriangle, Search, CheckCircle2, Info } from 'lucide-react';
import { gramsToTraditional, traditionalToGrams, carryTraditional, formatNumber } from '@/utils/weight';
import '../../../css/buyback.css';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Buy-Back', href: '/buyback' }];

const fmt = (n: number | string) =>
    'Rs ' + new Intl.NumberFormat('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n) || 0);

interface Metal { id: number; name: string; }
interface Purity { id: number; name: string; metal_type_id: number; }

const EMPTY_WEIGHT = { gram: '', tola: '', masha: '', ratti: '', point: '' };

export default function BuyBackIndex({ metals = [], purities = [], currentRates = {} }: {
    metals: Metal[]; purities: Purity[]; currentRates: Record<string, string | number>;
}) {
    // ── original sale lookup (reference only) ────────────────────────────
    const [lookupQuery, setLookupQuery] = useState('');
    const [lookup, setLookup] = useState<any>(null);
    const [lookupLoading, setLookupLoading] = useState(false);
    const [lookupMsg, setLookupMsg] = useState('');
    const [originalSaleTxnId, setOriginalSaleTxnId] = useState<number | null>(null);
    const [itemId, setItemId] = useState<number | null>(null);

    // ── gold being bought back ──────────────────────────────────────────
    const [metalTypeId, setMetalTypeId] = useState<string>(metals[0] ? String(metals[0].id) : '');
    const [purityId, setPurityId] = useState<string>('');
    const [weight, setWeight] = useState({ ...EMPTY_WEIGHT });
    const [ratePerGram, setRatePerGram] = useState('');
    const rateTouched = useRef(false);
    // Deduction for wear / melting loss: a fixed weight cut ("3 ratti") or a percentage.
    const [deductionMode, setDeductionMode] = useState<'weight' | 'percent'>('weight');
    const [cut, setCut] = useState({ ...EMPTY_WEIGHT });
    const [deductionPercent, setDeductionPercent] = useState<number>(0);
    const [deductionOverride, setDeductionOverride] = useState(false);

    // ── customer + payment ─────────────────────────────────────────────
    const [customerName, setCustomerName] = useState('');
    const [customerPhone, setCustomerPhone] = useState('');
    const [paymentMethod, setPaymentMethod] = useState('cash');
    const [notes, setNotes] = useState('');

    // ── submission ─────────────────────────────────────────────────────
    const [submitting, setSubmitting] = useState(false);
    const [formError, setFormError] = useState('');
    const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
    const [result, setResult] = useState<any>(null);

    const availablePurities = useMemo(
        () => purities.filter(p => !metalTypeId || String(p.metal_type_id) === String(metalTypeId)),
        [purities, metalTypeId],
    );

    const grams = parseFloat(weight.gram) || 0;
    const rate = parseFloat(ratePerGram) || 0;
    const suggestedCut = grams > 0 && deductionPercent > 0 ? +((grams * deductionPercent) / 100).toFixed(3) : 0;
    const cutGrams = deductionMode === 'weight'
        ? parseFloat(cut.gram) || 0
        : +((grams * deductionPercent) / 100).toFixed(3);
    const effectiveWeight = Math.max(0, +(grams - cutGrams).toFixed(3));
    const effectivePercent = grams > 0 ? (cutGrams / grams) * 100 : 0;
    const payout = +(effectiveWeight * rate).toFixed(2);
    const canSubmit =
        !!metalTypeId && !!purityId && grams > 0 && rate > 0 && cutGrams < grams && !submitting;

    // keep purity valid for the chosen metal
    useEffect(() => {
        if (purityId && !availablePurities.some(p => String(p.id) === purityId)) {
            setPurityId('');
        }
    }, [availablePurities, purityId]);

    // prefill today's rate for the chosen purity (until the user types their own)
    useEffect(() => {
        if (rateTouched.current) return;
        const r = purityId ? currentRates[purityId] : undefined;
        setRatePerGram(r != null ? String(r) : '');
    }, [purityId, currentRates]);

    // resolve the shop deduction % + authoritative valuation, debounced
    useEffect(() => {
        if (!metalTypeId || !purityId) return;
        const t = setTimeout(async () => {
            try {
                const { data } = await axios.get('/api/v1/buyback/deduction', {
                    params: {
                        metal_type_id: metalTypeId,
                        purity_id: purityId,
                        weight_grams: grams || undefined,
                        rate_per_gram: rate || undefined,
                    },
                });
                if (!deductionOverride && typeof data.deduction_percent === 'number') {
                    setDeductionPercent(data.deduction_percent);
                }
            } catch {
                /* keep whatever we have; server resolves again on submit */
            }
        }, 300);
        return () => clearTimeout(t);
    }, [metalTypeId, purityId, grams, rate, deductionOverride]);

    /** Keep a gram + tola/masha/ratti/point group in sync as any field is edited. */
    function makeUnitSetter(current: typeof EMPTY_WEIGHT, apply: (next: typeof EMPTY_WEIGHT) => void) {
        return (unit: keyof typeof EMPTY_WEIGHT, value: string) => {
            if (unit === 'gram') {
                apply({ gram: value, ...gramsToTraditional(parseFloat(value) || 0) });
                return;
            }
            const draft = { ...current, [unit]: value };
            const needsCarry =
                (parseFloat(draft.point) || 0) >= 100 ||
                (parseFloat(draft.ratti) || 0) >= 8 ||
                (parseFloat(draft.masha) || 0) >= 12;
            const next: any = needsCarry ? { ...draft, ...carryTraditional(draft) } : draft;
            const g = traditionalToGrams(next);
            const allEmpty = !next.tola && !next.masha && !next.ratti && !next.point;
            next.gram = allEmpty ? '' : formatNumber(g, 3);
            apply(next);
        };
    }

    const setWeightUnit = makeUnitSetter(weight, setWeight);
    const setCutUnit = makeUnitSetter(cut, setCut);

    function useSuggestedCut() {
        if (suggestedCut <= 0) return;
        setCut({ gram: String(suggestedCut), ...gramsToTraditional(suggestedCut) });
    }

    async function doLookup(e: React.FormEvent) {
        e.preventDefault();
        const q = lookupQuery.trim();
        if (!q) return;
        setLookupLoading(true);
        setLookupMsg('');
        setLookup(null);
        try {
            const { data } = await axios.get('/api/v1/buyback/lookup-sale', { params: { query: q } });
            setLookup(data);
            const it = data.item;
            const sale = data.sale_transaction;
            setOriginalSaleTxnId(sale?.id ?? null);
            setItemId(it?.id ?? null);
            const mId = it?.metal_type_id ?? sale?.metal_type_id;
            const pId = it?.purity_id ?? sale?.purity_id;
            if (mId) setMetalTypeId(String(mId));
            if (pId) setPurityId(String(pId));
            if (sale?.party?.name && sale.party.name !== 'Walk-in Customer') setCustomerName(sale.party.name);
            if (sale?.party?.phone && sale.party.phone !== '0000000000') setCustomerPhone(sale.party.phone);
        } catch (err: any) {
            setOriginalSaleTxnId(null);
            setItemId(null);
            setLookupMsg(
                err?.response?.status === 404
                    ? 'No matching sale found. You can still record this as a walk-in buy-back below.'
                    : 'Lookup failed. Please try again.',
            );
        } finally {
            setLookupLoading(false);
        }
    }

    function clearReference() {
        setLookup(null);
        setLookupMsg('');
        setLookupQuery('');
        setOriginalSaleTxnId(null);
        setItemId(null);
    }

    async function submit() {
        if (!canSubmit) return;
        setSubmitting(true);
        setFormError('');
        setFieldErrors({});
        try {
            const { data } = await axios.post('/api/v1/buyback', {
                item_id: itemId,
                original_sale_transaction_id: originalSaleTxnId,
                customer_name: customerName || null,
                customer_phone: customerPhone || null,
                metal_type_id: Number(metalTypeId),
                purity_id: Number(purityId),
                weight_grams: grams,
                rate_per_gram: rate,
                // weight-cut mode sends the exact grams; percentage mode sends an
                // explicit % only when overridden, else the server resolves it.
                deduction_weight_grams: deductionMode === 'weight' ? cutGrams : undefined,
                deduction_percent: deductionMode === 'percent' && deductionOverride ? deductionPercent : undefined,
                payment_method: paymentMethod,
                notes: notes || null,
            });
            setResult(data);
        } catch (err: any) {
            if (err?.response?.status === 422) {
                setFieldErrors(err.response.data.errors || {});
                setFormError('Please fix the highlighted fields.');
            } else {
                setFormError(err?.response?.data?.message || 'Could not process the buy-back. Please try again.');
            }
        } finally {
            setSubmitting(false);
        }
    }

    function startNew() {
        setResult(null);
        clearReference();
        setWeight({ ...EMPTY_WEIGHT });
        setCut({ ...EMPTY_WEIGHT });
        setDeductionMode('weight');
        rateTouched.current = false;
        setRatePerGram('');
        setDeductionOverride(false);
        setDeductionPercent(0);
        setCustomerName('');
        setCustomerPhone('');
        setNotes('');
        setPaymentMethod('cash');
        setFormError('');
        setFieldErrors({});
    }

    const err = (k: string) => fieldErrors[k]?.[0];
    const purityName = availablePurities.find(p => String(p.id) === purityId)?.name;

    // ── success screen ─────────────────────────────────────────────────
    if (result) {
        const v = result.valuation ?? {};
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Buy-Back complete" />
                <div className="bb-page">
                    <div className="bb-done">
                        <div className="bb-done-icon"><CheckCircle2 size={30} /></div>
                        <h2>Buy-back recorded</h2>
                        <p>{result.message || 'The gold has been bought back and added to stock.'}</p>
                        <div className="bb-done-amount">{fmt(v.total_amount ?? payout)}</div>
                        <div className="bb-sum-row"><span>Re-weighed</span><span>{formatNumber(v.gross_weight_grams ?? grams, 3)} g</span></div>
                        <div className="bb-sum-row"><span>Cut</span><span>{formatNumber(v.deduction_weight_grams ?? cutGrams, 3)} g ({Number(v.deduction_percent ?? effectivePercent).toFixed(2)}%)</span></div>
                        <div className="bb-sum-row"><span>Effective weight</span><span>{formatNumber(v.effective_weight_grams ?? effectiveWeight, 3)} g</span></div>
                        <div className="bb-sum-row"><span>Rate applied</span><span>{fmt(v.rate_per_gram ?? rate)}</span></div>
                        <div className="bb-done-actions" style={{ marginTop: '1.25rem' }}>
                            <button type="button" className="bb-btn" style={{ maxWidth: 220 }} onClick={startNew}>New buy-back</button>
                            <Link href="/inventory" className="bb-btn-ghost">View inventory</Link>
                        </div>
                    </div>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Buy-Back" />
            <div className="bb-page">
                <div className="bb-header">
                    <h1 className="bb-title">Buy-Back</h1>
                    <p className="bb-desc">Purchase gold back from a customer, priced on a fresh weigh-in at today's rate.</p>
                </div>

                <div className="bb-notice">
                    <AlertTriangle size={18} />
                    <div>
                        <strong>This is not a refund.</strong>
                        Buy-back pays the customer for the gold they bring in, valued only from the re-weighed weight and the
                        current rate. It does not reverse a past sale, and the original sale record — if linked below — is shown
                        for reference only and is never changed.
                    </div>
                </div>

                <div className="bb-grid">
                    {/* ── left: form ─────────────────────────────────────────── */}
                    <div>
                        <div className="bb-card">
                            <h2 className="bb-card-title">Original sale (optional — reference only)</h2>
                            <form className="bb-lookup-row" onSubmit={doLookup}>
                                <input
                                    className="bb-input"
                                    placeholder="Item code or invoice number"
                                    value={lookupQuery}
                                    onChange={e => setLookupQuery(e.target.value)}
                                />
                                <button type="submit" className="bb-btn-ghost" disabled={lookupLoading || !lookupQuery.trim()}>
                                    <Search size={14} style={{ verticalAlign: '-2px', marginRight: 4 }} />
                                    {lookupLoading ? 'Searching…' : 'Find'}
                                </button>
                            </form>

                            {lookupMsg && <div className="bb-lookup-miss">{lookupMsg}</div>}

                            {lookup && (
                                <div className="bb-ref">
                                    <div className="bb-ref-tag"><Info size={12} /> Reference only — not used for valuation</div>
                                    <div className="bb-ref-grid">
                                        <div>
                                            <div className="bb-ref-label">Item</div>
                                            <div className="bb-ref-value">{lookup.item?.item_code || '—'}</div>
                                        </div>
                                        <div>
                                            <div className="bb-ref-label">Type / Metal</div>
                                            <div className="bb-ref-value" style={{ textTransform: 'capitalize' }}>
                                                {(lookup.item?.item_type || lookup.sale_transaction?.item?.item_type || '—')}
                                                {' · '}
                                                {lookup.item?.metal_type?.name || lookup.sale_transaction?.metal_type?.name || ''}
                                                {' '}
                                                {lookup.item?.purity?.name || lookup.sale_transaction?.purity?.name || ''}
                                            </div>
                                        </div>
                                        <div>
                                            <div className="bb-ref-label">Original sale weight</div>
                                            <div className="bb-ref-value">{formatNumber(lookup.sale_transaction?.weight_grams ?? 0, 3)} g</div>
                                        </div>
                                        <div>
                                            <div className="bb-ref-label">Original sale rate</div>
                                            <div className="bb-ref-value">{fmt(lookup.sale_transaction?.rate_per_gram ?? 0)}</div>
                                        </div>
                                        <div>
                                            <div className="bb-ref-label">Original sale total</div>
                                            <div className="bb-ref-value">{fmt(lookup.sale_transaction?.total_amount ?? 0)}</div>
                                        </div>
                                        <div>
                                            <div className="bb-ref-label">Invoice</div>
                                            <div className="bb-ref-value">{lookup.sale_transaction?.invoice?.invoice_number || '—'}</div>
                                        </div>
                                    </div>
                                    <div className="bb-ref-note">{lookup.disclaimer}</div>
                                    <button type="button" className="bb-btn-ghost" style={{ marginTop: '0.7rem' }} onClick={clearReference}>
                                        Remove reference
                                    </button>
                                </div>
                            )}
                        </div>

                        <div className="bb-card">
                            <h2 className="bb-card-title">Gold being bought back</h2>
                            <div className="bb-field-grid">
                                <div className="bb-field">
                                    <label className="bb-label">Metal</label>
                                    <select className="bb-select" value={metalTypeId} onChange={e => { setMetalTypeId(e.target.value); setPurityId(''); }}>
                                        <option value="">Select metal</option>
                                        {metals.map(m => <option key={m.id} value={m.id}>{m.name}</option>)}
                                    </select>
                                    {err('metal_type_id') && <div className="bb-error">{err('metal_type_id')}</div>}
                                </div>
                                <div className="bb-field">
                                    <label className="bb-label">Purity</label>
                                    <select className="bb-select" value={purityId} onChange={e => setPurityId(e.target.value)} disabled={!metalTypeId}>
                                        <option value="">Select purity</option>
                                        {availablePurities.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                                    </select>
                                    {err('purity_id') && <div className="bb-error">{err('purity_id')}</div>}
                                </div>

                                <div className="bb-field bb-col-2">
                                    <label className="bb-label">Re-weighed weight</label>
                                    <div className="bb-weight-grid">
                                        {(['gram', 'tola', 'masha', 'ratti', 'point'] as const).map(u => (
                                            <div key={u} className="bb-field">
                                                <label className="bb-label" style={{ textTransform: 'capitalize', fontWeight: 500, fontSize: '0.72rem' }}>{u}</label>
                                                <input
                                                    type="number" step="any" min="0" className="bb-input"
                                                    value={(weight as any)[u]}
                                                    onChange={e => setWeightUnit(u, e.target.value)}
                                                />
                                            </div>
                                        ))}
                                    </div>
                                    <div className="bb-hint">Enter a fresh weigh-in — the original sale weight is not used.</div>
                                    {err('weight_grams') && <div className="bb-error">{err('weight_grams')}</div>}
                                </div>

                                <div className="bb-field">
                                    <label className="bb-label">Rate per gram (today)</label>
                                    <input
                                        type="number" step="any" min="0" className="bb-input"
                                        value={ratePerGram}
                                        onChange={e => { rateTouched.current = true; setRatePerGram(e.target.value); }}
                                    />
                                    {purityId && currentRates[purityId] != null && (
                                        <div className="bb-hint">Today's {purityName} rate: {fmt(currentRates[purityId])}</div>
                                    )}
                                    {err('rate_per_gram') && <div className="bb-error">{err('rate_per_gram')}</div>}
                                </div>

                                <div className="bb-field bb-col-2">
                                    <label className="bb-label">Deduction for wear / melting loss</label>
                                    <div className="bb-mode-tabs">
                                        <button
                                            type="button"
                                            className={`bb-mode-tab ${deductionMode === 'weight' ? 'active' : ''}`}
                                            onClick={() => setDeductionMode('weight')}
                                        >
                                            Weight cut
                                        </button>
                                        <button
                                            type="button"
                                            className={`bb-mode-tab ${deductionMode === 'percent' ? 'active' : ''}`}
                                            onClick={() => setDeductionMode('percent')}
                                        >
                                            Percentage
                                        </button>
                                    </div>

                                    {deductionMode === 'weight' ? (
                                        <>
                                            <div className="bb-weight-grid" style={{ marginTop: '0.6rem' }}>
                                                {(['gram', 'tola', 'masha', 'ratti', 'point'] as const).map(u => (
                                                    <div key={u} className="bb-field">
                                                        <label className="bb-label" style={{ textTransform: 'capitalize', fontWeight: 500, fontSize: '0.72rem' }}>{u}</label>
                                                        <input
                                                            type="number" step="any" min="0" className="bb-input"
                                                            value={(cut as any)[u]}
                                                            onChange={e => setCutUnit(u, e.target.value)}
                                                        />
                                                    </div>
                                                ))}
                                            </div>
                                            <div className="bb-hint">
                                                Cut {formatNumber(cutGrams, 3)} g{grams > 0 ? ` (${effectivePercent.toFixed(2)}% of ${formatNumber(grams, 3)} g)` : ''}.
                                                {suggestedCut > 0 && (
                                                    <> Shop default ≈ {deductionPercent.toFixed(2)}% ≈ {formatNumber(suggestedCut, 3)} g —{' '}
                                                        <button type="button" className="bb-linkbtn" onClick={useSuggestedCut}>use this</button>.
                                                    </>
                                                )}
                                            </div>
                                            {err('deduction_weight_grams') && <div className="bb-error">{err('deduction_weight_grams')}</div>}
                                        </>
                                    ) : (
                                        <>
                                            <div className="bb-deduction-line" style={{ marginTop: '0.6rem' }}>
                                                <input
                                                    type="number" step="any" min="0" max="100"
                                                    className="bb-input" style={{ maxWidth: 120 }}
                                                    value={deductionPercent}
                                                    disabled={!deductionOverride}
                                                    onChange={e => setDeductionPercent(parseFloat(e.target.value) || 0)}
                                                />
                                                <span className="bb-hint" style={{ margin: 0 }}>%</span>
                                                <label className="bb-toggle">
                                                    <input
                                                        type="checkbox"
                                                        checked={deductionOverride}
                                                        onChange={e => setDeductionOverride(e.target.checked)}
                                                    />
                                                    override
                                                </label>
                                            </div>
                                            <div className="bb-hint">
                                                {deductionOverride
                                                    ? 'Manual override for this buy-back only.'
                                                    : 'Resolved from shop settings (purity → metal → default).'}
                                                {grams > 0 && ` ≈ ${formatNumber(cutGrams, 3)} g on this weight.`}
                                            </div>
                                            {err('deduction_percent') && <div className="bb-error">{err('deduction_percent')}</div>}
                                        </>
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="bb-card">
                            <h2 className="bb-card-title">Customer &amp; payment</h2>
                            <div className="bb-field-grid">
                                <div className="bb-field">
                                    <label className="bb-label">Customer name</label>
                                    <input className="bb-input" value={customerName} onChange={e => setCustomerName(e.target.value)} placeholder="Optional" />
                                    {err('customer_name') && <div className="bb-error">{err('customer_name')}</div>}
                                </div>
                                <div className="bb-field">
                                    <label className="bb-label">Phone</label>
                                    <input className="bb-input" value={customerPhone} onChange={e => setCustomerPhone(e.target.value)} placeholder="Optional" />
                                    {err('customer_phone') && <div className="bb-error">{err('customer_phone')}</div>}
                                </div>
                                <div className="bb-field">
                                    <label className="bb-label">Payment method</label>
                                    <select className="bb-select" value={paymentMethod} onChange={e => setPaymentMethod(e.target.value)}>
                                        <option value="cash">Cash</option>
                                        <option value="card">Card</option>
                                        <option value="bank_transfer">Bank transfer</option>
                                        <option value="credit">Credit</option>
                                    </select>
                                </div>
                                <div className="bb-field bb-col-2">
                                    <label className="bb-label">Notes</label>
                                    <textarea
                                        className="bb-input" rows={2} style={{ resize: 'vertical' }}
                                        value={notes} onChange={e => setNotes(e.target.value)}
                                        placeholder="e.g. condition of the piece, who handled it"
                                    />
                                    {err('notes') && <div className="bb-error">{err('notes')}</div>}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* ── right: valuation ───────────────────────────────────── */}
                    <div>
                        <div className="bb-summary">
                            <div className="bb-summary-head">Buy-Back Valuation</div>
                            <div className="bb-summary-body">
                                <div className="bb-sum-row"><span>Re-weighed weight</span><span>{formatNumber(grams, 3)} g</span></div>
                                <div className="bb-sum-row bb-sum-minus">
                                    <span>{deductionMode === 'weight' ? 'Cut' : 'Deduction'} ({effectivePercent.toFixed(2)}%)</span>
                                    <span>− {formatNumber(cutGrams, 3)} g</span>
                                </div>
                                <div className="bb-sum-row"><span>Effective weight</span><span>{formatNumber(effectiveWeight, 3)} g</span></div>
                                <div className="bb-sum-row"><span>Rate applied</span><span>{fmt(rate)}</span></div>
                                <hr className="bb-sum-divider" />
                                <div className="bb-payout">
                                    <div className="bb-payout-label">Pay the customer</div>
                                    <div className="bb-payout-value">{fmt(payout)}</div>
                                </div>

                                {lookup?.sale_transaction && (
                                    <div className="bb-compare">
                                        Original sale was {fmt(lookup.sale_transaction.total_amount)}. This buy-back differs
                                        because it is priced on the re-weighed weight and today's rate — not the old price.
                                    </div>
                                )}

                                {formError && <div className="bb-form-error">{formError}</div>}

                                <button type="button" className="bb-btn" disabled={!canSubmit} onClick={submit}>
                                    {submitting ? 'Processing…' : `Confirm buy-back — pay ${fmt(payout)}`}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
