import ItemEntryPrintSlip from '@/components/item-entry-print-slip';
import AppLayout from '@/layouts/app-layout';
import { useScaleReading } from '@/utils/scale';
import { carryTraditional, formatNumber, gramsToTraditional, traditionalToGrams } from '@/utils/weight';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { AlertCircle, ArrowLeft, Info, Printer } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import '../../../css/item-entry-print.css';
import '../../../css/item-entry.css';

interface Metal {
    id: number;
    name: string;
}

interface Purity {
    id: number;
    metal_type_id: number;
    name: string;
    fineness_percent?: number;
    is_active?: number | boolean;
}

interface Party {
    id: number;
    name: string;
}

interface Shop {
    name: string;
    address: string | null;
    phone: string | null;
    logo: string | null;
}

interface PrintDimensions {
    widthMm: number;
    heightMm: number;
    paddingMm: number;
}

interface ItemEditProps {
    item: any;
    metals: Metal[];
    purities: Purity[];
    parties: Party[];
    currentRates?: Record<string, string | number>;
    shop: Shop;
    printerType: 'regular' | 'thermal';
    printDimensions: PrintDimensions;
}

const BULLION_TYPES = ['biscuit', 'nugget', 'bar', 'coin', 'piece'];

function isBullion(type: string): boolean {
    return BULLION_TYPES.includes(type.toLowerCase());
}

function is24KPurity(name?: string): boolean {
    if (!name) return false;
    return /^24\s*k$/i.test(name.trim()) || name.includes('999') || name.toLowerCase().includes('fine silver');
}

function is22KPurity(name?: string): boolean {
    if (!name) return false;
    return /^22\s*k$/i.test(name.trim()) || name.includes('916') || name.includes('925') || name.toLowerCase().includes('sterling');
}

function resolveDefaultPurityId(itemType: string, metalId: number | string, puritiesList: Purity[]): number | string {
    const metalPurities = puritiesList.filter((p) => !metalId || String(p.metal_type_id) === String(metalId));
    if (metalPurities.length === 0) return '';

    if (isBullion(itemType)) {
        const p24 = metalPurities.find((p) => is24KPurity(p.name));
        if (p24) return p24.id;
    } else {
        const p22 = metalPurities.find((p) => is22KPurity(p.name));
        if (p22) return p22.id;
    }

    if (itemType.toLowerCase() === 'ring') {
        const non24 = metalPurities.find((p) => !is24KPurity(p.name));
        if (non24) return non24.id;
    }

    return metalPurities[0]?.id || '';
}

function getUnitsFromGrams(grams: number) {
    const num = Number(grams) || 0;
    if (num <= 0) {
        return { gram: '', tola: '', masha: '', ratti: '', point: '' };
    }
    return { gram: formatNumber(num, 3), ...gramsToTraditional(num) };
}

export default function ItemEdit({
    item,
    metals = [],
    purities = [],
    parties = [],
    currentRates = {},
    shop,
    printerType,
    printDimensions,
}: ItemEditProps) {
    const { flash } = usePage().props as any;
    // On edit we already have a saved rate — don't auto-overwrite it, just offer "use this".
    const rateTouched = useRef(true);
    const initialGrossGrams = parseFloat(item.gross_weight_grams) || 0;
    const initialStoneGrams = parseFloat(item.stone_weight_grams) || 0;
    const initialPolishGrams = parseFloat(item.polish_weight_grams) || 0;
    const initialCuttingGrams = parseFloat(item.cutting_loss_grams) || 0;

    const [gross, setGross] = useState(getUnitsFromGrams(initialGrossGrams));
    const [stone, setStone] = useState({ gram: initialStoneGrams > 0 ? String(initialStoneGrams) : '' });
    const [polish, setPolish] = useState({ gram: initialPolishGrams > 0 ? String(initialPolishGrams) : '' });
    const [cutting, setCutting] = useState(getUnitsFromGrams(initialCuttingGrams));

    const [grossGrams, setGrossGrams] = useState(initialGrossGrams);
    const [stoneGrams, setStoneGrams] = useState(initialStoneGrams);
    const [polishGrams, setPolishGrams] = useState(initialPolishGrams);
    const [cuttingGrams, setCuttingGrams] = useState(initialCuttingGrams);

    const [purityNotice, setPurityNotice] = useState<string>('');
    const [ringValidationError, setRingValidationError] = useState<string>('');
    const [grossWeightError, setGrossWeightError] = useState<string>('');
    const scale = useScaleReading();
    const scaleEverConnected = useRef(false);
    if (scale.ok) scaleEverConnected.current = true;

    const { data, setData, put, processing, errors } = useForm({
        item_code: item.item_code || '',
        item_type: item.item_type || 'ring',
        metal_type_id: item.metal_type_id || '',
        purity_id: item.purity_id || '',
        source_party_id: item.source_party_id || '',
        date_received: item.date_received ? item.date_received.slice(0, 10) : new Date().toISOString().slice(0, 10),
        status: item.status || 'in_stock',
        gross_weight_grams: initialGrossGrams,
        stone_weight_grams: initialStoneGrams,
        cutting_loss_grams: initialCuttingGrams,
        polish_weight_grams: initialPolishGrams,
        net_weight_grams: parseFloat(item.net_weight_grams) || 0,
        labour_cost: parseFloat(item.labour_cost) || 0,
        purchase_rate_per_gram: parseFloat(item.purchase_rate_per_gram) || 0,
        purchase_price: parseFloat(item.purchase_price) || 0,
        edit_reason: '',
        print_after_save: false as boolean,
    });

    const availablePurities = useMemo(() => {
        if (!data.metal_type_id) return purities;
        return purities.filter((p) => String(p.metal_type_id) === String(data.metal_type_id));
    }, [purities, data.metal_type_id]);

    // Today's rate for the chosen purity (from Rate Management), if any.
    const liveRate = data.purity_id != null && data.purity_id !== '' ? Number(currentRates[String(data.purity_id)]) : NaN;
    const hasLiveRate = Number.isFinite(liveRate) && liveRate > 0;

    const handleItemTypeChange = (newType: string) => {
        const isBullionType = isBullion(newType);
        const metalPurities = purities.filter((p) => !data.metal_type_id || String(p.metal_type_id) === String(data.metal_type_id));

        let newPurityId = data.purity_id;
        let notice = '';
        const ringErr = '';

        const currentPurity = purities.find((p) => String(p.id) === String(data.purity_id));

        if (isBullionType) {
            const p24 = metalPurities.find((p) => is24KPurity(p.name));
            if (p24) {
                newPurityId = p24.id;
                notice = `Defaulted purity to ${p24.name} (24K pure) for ${newType}.`;
            }
        } else {
            if (newType === 'ring' && currentPurity && is24KPurity(currentPurity.name)) {
                const p22 = metalPurities.find((p) => is22KPurity(p.name)) || metalPurities.find((p) => !is24KPurity(p.name));
                if (p22) {
                    newPurityId = p22.id;
                    notice = `Rings cannot be 24K gold (pure gold is too soft). Purity automatically set to ${p22.name}.`;
                }
            } else if (currentPurity && is24KPurity(currentPurity.name)) {
                const p22 = metalPurities.find((p) => is22KPurity(p.name));
                if (p22) {
                    newPurityId = p22.id;
                    notice = `Defaulted purity to ${p22.name} for jewelry item.`;
                }
            }
        }

        setPurityNotice(notice);
        setRingValidationError(ringErr);

        setData((prev) => ({
            ...prev,
            item_type: newType,
            purity_id: newPurityId,
        }));
    };

    const handleMetalChange = (newMetalId: string) => {
        const newPurityId = resolveDefaultPurityId(data.item_type, newMetalId, purities);
        setPurityNotice('');
        setRingValidationError('');
        setData((prev) => ({
            ...prev,
            metal_type_id: newMetalId,
            purity_id: newPurityId,
        }));
    };

    const handlePurityChange = (newPurityId: string) => {
        const selectedPurity = purities.find((p) => String(p.id) === String(newPurityId));
        if (data.item_type === 'ring' && selectedPurity && is24KPurity(selectedPurity.name)) {
            setRingValidationError('Ring items cannot be 24K (pure gold is too soft for rings). Please select 22K, 21K, or 18K.');
            return;
        }
        setRingValidationError('');
        setPurityNotice('');
        setData('purity_id', newPurityId);
    };

    // Gram and the tola/masha/ratti/point breakdown stay in sync; both are editable.
    // Editing gram re-derives the carried breakdown; editing any traditional unit
    // sums the breakdown back into gram.
    function handleUnitChange(setObj: any, obj: any, unit: string, value: string, setGrams: (n: number) => void) {
        let newObj: any;
        let grams = 0;

        if (unit === 'gram') {
            grams = parseFloat(value) || 0;
            newObj = { gram: value, ...gramsToTraditional(grams) };
        } else {
            const draft = { ...obj, [unit]: value };
            // Roll a field up once it hits its limit: 100 point -> ratti, 8 ratti -> masha, 12 masha -> tola.
            const needsCarry = (parseFloat(draft.point) || 0) >= 100 || (parseFloat(draft.ratti) || 0) >= 8 || (parseFloat(draft.masha) || 0) >= 12;
            newObj = needsCarry ? { ...draft, ...carryTraditional(draft) } : draft;
            grams = traditionalToGrams(newObj);

            const allEmpty = !newObj.tola && !newObj.masha && !newObj.ratti && !newObj.point;
            newObj.gram = allEmpty ? '' : formatNumber(grams, 3);
        }

        setGrams(Number(grams.toFixed(6)));
        setObj(newObj);
    }

    function handleStoneGramChange(value: string) {
        setStone({ gram: value });
        setStoneGrams(Number((parseFloat(value) || 0).toFixed(6)));
    }

    function handlePolishGramChange(value: string) {
        setPolish({ gram: value });
        setPolishGrams(Number((parseFloat(value) || 0).toFixed(6)));
    }

    // Auto-fills Gross Weight straight from the scale as it updates — paused
    // while the shopkeeper has a weight field focused so typing isn't fought.
    const grossWeightFocused = useRef(false);
    useEffect(() => {
        if (scale.ok && scale.grams !== null && !grossWeightFocused.current) {
            setGrossWeightError('');
            handleUnitChange(setGross, gross, 'gram', String(Math.max(0, scale.grams)), setGrossGrams);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [scale.ok, scale.grams]);

    // Polish is taken as weight added back into net weight (the shop's profit
    // margin lives here, not as a separate Rs charge) — so it flows straight
    // into the metal value below, rather than being added on its own.
    const netGrams = Math.max(0, grossGrams - stoneGrams - cuttingGrams + polishGrams);
    const totalPrice = netGrams * data.purchase_rate_per_gram + data.labour_cost;

    useEffect(() => {
        setData((data) => ({
            ...data,
            gross_weight_grams: Number(grossGrams.toFixed(3)),
            stone_weight_grams: Number(stoneGrams.toFixed(3)),
            cutting_loss_grams: Number(cuttingGrams.toFixed(3)),
            polish_weight_grams: Number(polishGrams.toFixed(3)),
            net_weight_grams: Number(netGrams.toFixed(3)),
            purchase_price: totalPrice,
        }));
    }, [grossGrams, stoneGrams, cuttingGrams, polishGrams, netGrams, data.purchase_rate_per_gram, data.labour_cost]);

    // Updating stays on this same Edit page (preserveState) instead of
    // navigating to Inventory — the shopkeeper asked not to be bounced away.
    // The "reason for change" note clears once the update lands; "Update &
    // Print" additionally waits for the server's flashed item (it carries
    // the current, post-update values) before printing and then clearing.
    const [pendingAction, setPendingAction] = useState<'save' | 'print' | null>(null);
    const [printItem, setPrintItem] = useState<any>(null);

    useEffect(() => {
        if (!pendingAction) return;
        put(route('items.update', item.id), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                // A plain update clears the note right away; "print" clears
                // once the print effect below has actually fired the dialog.
                if (pendingAction === 'save') {
                    setData('edit_reason', '');
                    setPendingAction(null);
                }
            },
            // Validation failed server-side — leave what was typed in place
            // and just re-enable the buttons.
            onError: () => setPendingAction(null),
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [pendingAction]);

    // The server flashes the updated item on every save; only act on it
    // when this visit was actually an "Update & Print".
    useEffect(() => {
        if (pendingAction === 'print' && flash?.createdItem) {
            setPrintItem(flash.createdItem);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [flash?.createdItem]);

    useEffect(() => {
        if (!printItem) return;
        const t = setTimeout(() => {
            window.print();
            setPrintItem(null);
            setData('edit_reason', '');
            setPendingAction(null);
        }, 150);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [printItem]);

    const submit = (action: 'save' | 'print') => {
        if (grossGrams <= 0) {
            setGrossWeightError('Please enter a valid gross weight greater than 0.');
            return;
        }
        setGrossWeightError('');

        const currentPurity = purities.find((p) => String(p.id) === String(data.purity_id));
        if (data.item_type === 'ring' && currentPurity && is24KPurity(currentPurity.name)) {
            setRingValidationError('Ring items cannot be saved with 24K purity. Please select 22K, 21K, or 18K.');
            return;
        }

        setPendingAction(action);
    };

    return (
        <AppLayout>
            <Head title={`Edit Item - ${item.item_code}`} />

            <div className="item-entry-container">
                <div
                    className="item-entry-header"
                    style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '1rem' }}
                >
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', marginBottom: '0.5rem' }}>
                            <Link
                                href="/inventory"
                                className="action-btn"
                                style={{
                                    padding: '0.4rem',
                                    borderRadius: '6px',
                                    border: '1px solid #d1d5db',
                                    textDecoration: 'none',
                                    display: 'inline-flex',
                                    color: '#4b5563',
                                }}
                            >
                                <ArrowLeft size={18} />
                            </Link>
                            <h1 className="item-entry-title" style={{ margin: 0 }}>
                                Edit Item: {item.item_code}
                            </h1>
                        </div>
                        <p className="item-entry-desc">Update the details, weight measurements, pricing, and stock status below.</p>
                    </div>
                    <div style={{ display: 'flex', gap: '0.75rem', alignItems: 'center' }}>
                        <Link
                            href={`/items/${item.id}/tag`}
                            style={{
                                backgroundColor: '#6366f1',
                                color: '#ffffff',
                                padding: '0.5rem 1.25rem',
                                borderRadius: '6px',
                                fontSize: '0.875rem',
                                textDecoration: 'none',
                                fontWeight: 600,
                                display: 'inline-flex',
                                alignItems: 'center',
                                gap: '0.35rem',
                            }}
                        >
                            <Printer size={15} /> Print Tag
                        </Link>
                        <Link href="/inventory" className="filter-clear" style={{ textDecoration: 'none', display: 'inline-block' }}>
                            Back to Inventory
                        </Link>
                    </div>
                </div>

                {flash?.success && (
                    <div
                        className="item-code-preview"
                        style={{ background: '#eff6ff', borderColor: '#bfdbfe', color: '#1e40af', marginBottom: '0.75rem' }}
                    >
                        {flash.success}
                    </div>
                )}

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        submit('save');
                    }}
                    className="item-entry-form"
                >
                    {/* Basic Details Card */}
                    <div className="item-card">
                        <h2 className="card-title">Basic Details</h2>

                        <div className="form-grid">
                            <div className="form-group">
                                <label className="form-label">Item Code</label>
                                <input
                                    type="text"
                                    className="form-input"
                                    value={data.item_code}
                                    onChange={(e) => setData('item_code', e.target.value)}
                                    required
                                />
                                {errors.item_code && <div className="mt-1 text-xs text-red-500">{errors.item_code}</div>}
                            </div>

                            <div className="form-group">
                                <label className="form-label">Status</label>
                                <select className="form-select" value={data.status} onChange={(e) => setData('status', e.target.value)}>
                                    <option value="in_stock">In Stock</option>
                                    <option value="sold">Sold</option>
                                    <option value="bought_back">Bought Back</option>
                                </select>
                                {errors.status && <div className="mt-1 text-xs text-red-500">{errors.status}</div>}
                            </div>

                            <div className="form-group">
                                <label className="form-label">Item Type</label>
                                <select className="form-select" value={data.item_type} onChange={(e) => handleItemTypeChange(e.target.value)}>
                                    <optgroup label="Jewelry Items (Default: 22K)">
                                        <option value="ring">Ring</option>
                                        <option value="bangle">Bangle</option>
                                        <option value="necklace">Necklace</option>
                                        <option value="earring">Earring</option>
                                        <option value="bracelet">Bracelet</option>
                                        <option value="chain">Chain</option>
                                        <option value="pendant">Pendant</option>
                                        <option value="locket">Locket</option>
                                        <option value="nose_pin">Nose Pin</option>
                                        <option value="tops">Tops</option>
                                        <option value="other">Other Jewelry</option>
                                    </optgroup>
                                    <optgroup label="Bullion & Raw Items (Default: 24K)">
                                        <option value="biscuit">Biscuit / Bar</option>
                                        <option value="nugget">Nugget</option>
                                        <option value="coin">Coin</option>
                                        <option value="piece">Raw Gold / Lagdi</option>
                                    </optgroup>
                                </select>
                                {errors.item_type && <div className="mt-1 text-xs text-red-500">{errors.item_type}</div>}
                            </div>

                            <div className="form-group">
                                <label className="form-label">Metal Type</label>
                                <select className="form-select" value={data.metal_type_id} onChange={(e) => handleMetalChange(e.target.value)}>
                                    <option value="">Select Metal</option>
                                    {metals.map((m) => (
                                        <option key={m.id} value={m.id}>
                                            {m.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.metal_type_id && <div className="mt-1 text-xs text-red-500">{errors.metal_type_id}</div>}
                            </div>

                            <div className="form-group">
                                <label className="form-label">Purity</label>
                                <select className="form-select" value={data.purity_id} onChange={(e) => handlePurityChange(e.target.value)}>
                                    <option value="">Select Purity</option>
                                    {availablePurities.map((p) => {
                                        const isRing24K = data.item_type === 'ring' && is24KPurity(p.name);
                                        return (
                                            <option key={p.id} value={p.id} disabled={isRing24K}>
                                                {p.name} {isRing24K ? '— (Not allowed for rings)' : ''}
                                            </option>
                                        );
                                    })}
                                </select>
                                {errors.purity_id && <div className="mt-1 text-xs font-medium text-red-500">{errors.purity_id}</div>}
                                {ringValidationError && (
                                    <div
                                        style={{
                                            color: '#dc2626',
                                            fontSize: '0.8rem',
                                            marginTop: '0.35rem',
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: '0.25rem',
                                        }}
                                    >
                                        <AlertCircle size={14} />
                                        <span>{ringValidationError}</span>
                                    </div>
                                )}
                                {purityNotice && (
                                    <div
                                        style={{
                                            marginTop: '0.5rem',
                                            padding: '0.5rem 0.75rem',
                                            borderRadius: '6px',
                                            backgroundColor: '#eff6ff',
                                            border: '1px solid #bfdbfe',
                                            color: '#1e40af',
                                            fontSize: '0.8rem',
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: '0.4rem',
                                        }}
                                    >
                                        <Info size={15} style={{ flexShrink: 0, color: '#2563eb' }} />
                                        <span>{purityNotice}</span>
                                    </div>
                                )}
                            </div>

                            <div className="form-group">
                                <label className="form-label">Source Party</label>
                                <select
                                    className="form-select"
                                    value={data.source_party_id}
                                    onChange={(e) => setData('source_party_id', e.target.value)}
                                >
                                    <option value="">Select Party (Optional)</option>
                                    {parties.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name}
                                        </option>
                                    ))}
                                </select>
                                {errors.source_party_id && <div className="mt-1 text-xs text-red-500">{errors.source_party_id}</div>}
                            </div>

                            <div className="form-group">
                                <label className="form-label">Date Received</label>
                                <input
                                    type="date"
                                    className="form-input"
                                    value={data.date_received}
                                    onChange={(e) => setData('date_received', e.target.value)}
                                />
                                {errors.date_received && <div className="mt-1 text-xs text-red-500">{errors.date_received}</div>}
                            </div>
                        </div>
                    </div>

                    {/* Weight Management Card */}
                    <div className="item-card item-card-wide">
                        <h2 className="card-title">Weight Management</h2>

                        <div className="weight-section">
                            <div className="weight-title">Gross Weight</div>
                            {(scale.ok || scaleEverConnected.current) && (
                                <div className="scale-hint">
                                    {scale.ok ? (
                                        <>
                                            <span className="scale-dot online" />
                                            Scale: <strong>{formatNumber(scale.grams ?? 0, 2)} g</strong> — auto-filling
                                        </>
                                    ) : (
                                        <>
                                            <span className="scale-dot offline" />
                                            Scale disconnected — enter weight manually
                                        </>
                                    )}
                                </div>
                            )}
                            <div className="weight-grid">
                                {(['gram', 'tola', 'masha', 'ratti', 'point'] as const).map((u) => (
                                    <div key={u} className="form-group">
                                        <label className="form-label" style={{ textTransform: 'capitalize' }}>
                                            {u}
                                        </label>
                                        <input
                                            type="number"
                                            step="any"
                                            min="0"
                                            className="form-input"
                                            value={(gross as any)[u]}
                                            onFocus={() => {
                                                grossWeightFocused.current = true;
                                            }}
                                            onBlur={() => {
                                                grossWeightFocused.current = false;
                                            }}
                                            onChange={(e) => {
                                                setGrossWeightError('');
                                                handleUnitChange(setGross, gross, u, e.target.value, setGrossGrams);
                                            }}
                                        />
                                    </div>
                                ))}
                            </div>
                            {(grossWeightError || errors.gross_weight_grams) && (
                                <div
                                    style={{
                                        color: '#dc2626',
                                        fontSize: '0.825rem',
                                        marginTop: '0.5rem',
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: '0.3rem',
                                    }}
                                >
                                    <AlertCircle size={15} />
                                    <span>{grossWeightError || errors.gross_weight_grams}</span>
                                </div>
                            )}
                        </div>

                        <div className="weight-section">
                            <div className="weight-title">Stone &amp; Polish Weight (Gram)</div>
                            <div className="weight-grid" style={{ gridTemplateColumns: 'repeat(2, 1fr)' }}>
                                <div className="form-group">
                                    <label className="form-label">Stone Weight (−)</label>
                                    <input
                                        type="number"
                                        step="any"
                                        min="0"
                                        className="form-input"
                                        value={stone.gram}
                                        onChange={(e) => handleStoneGramChange(e.target.value)}
                                    />
                                </div>
                                <div className="form-group">
                                    <label className="form-label">Polish Weight (+)</label>
                                    <input
                                        type="number"
                                        step="any"
                                        min="0"
                                        className="form-input"
                                        value={polish.gram}
                                        onChange={(e) => handlePolishGramChange(e.target.value)}
                                    />
                                </div>
                            </div>
                        </div>

                        <div className="weight-section">
                            <div className="weight-title">Cutting Loss (Deduction)</div>
                            <div className="weight-grid">
                                {(['gram', 'tola', 'masha', 'ratti', 'point'] as const).map((u) => (
                                    <div key={u} className="form-group">
                                        <label className="form-label" style={{ textTransform: 'capitalize' }}>
                                            {u}
                                        </label>
                                        <input
                                            type="number"
                                            step="any"
                                            min="0"
                                            className="form-input"
                                            value={(cutting as any)[u]}
                                            onChange={(e) => handleUnitChange(setCutting, cutting, u, e.target.value, setCuttingGrams)}
                                        />
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Costing Card */}
                    <div className="item-card">
                        <h2 className="card-title">Costing & Pricing</h2>

                        <div className="form-grid">
                            <div className="form-group">
                                <label className="form-label">Purchase Rate (per Gram)</label>
                                <input
                                    type="number"
                                    step="any"
                                    min="0"
                                    className="form-input"
                                    value={data.purchase_rate_per_gram || ''}
                                    onChange={(e) => {
                                        rateTouched.current = true;
                                        setData('purchase_rate_per_gram', parseFloat(e.target.value) || 0);
                                    }}
                                />
                                {hasLiveRate && (
                                    <div className="rate-hint">
                                        Today's {availablePurities.find((p) => String(p.id) === String(data.purity_id))?.name} rate:{' '}
                                        <strong>Rs {formatNumber(liveRate, 2)}</strong> / g
                                        {Number(data.purchase_rate_per_gram) !== liveRate && (
                                            <button
                                                type="button"
                                                className="rate-hint-use"
                                                onClick={() => {
                                                    rateTouched.current = true;
                                                    setData('purchase_rate_per_gram', liveRate);
                                                }}
                                            >
                                                use this
                                            </button>
                                        )}
                                    </div>
                                )}
                            </div>
                            <div className="form-group">
                                <label className="form-label">Labour Cost (Total)</label>
                                <input
                                    type="number"
                                    step="any"
                                    min="0"
                                    className="form-input"
                                    value={data.labour_cost || ''}
                                    onChange={(e) => setData('labour_cost', parseFloat(e.target.value) || 0)}
                                />
                            </div>
                        </div>
                    </div>

                    {/* Summary & Actions */}
                    <div className="summary-panel">
                        <h2 className="summary-title">Calculation Summary</h2>

                        <div className="summary-row">
                            <span>Gross Weight</span>
                            <span className="summary-value">{formatNumber(grossGrams, 3)} g</span>
                        </div>
                        <div className="summary-row">
                            <span>- Stone Weight</span>
                            <span className="summary-value">{formatNumber(stoneGrams, 3)} g</span>
                        </div>
                        <div className="summary-row">
                            <span>- Cutting Loss</span>
                            <span className="summary-value">{formatNumber(cuttingGrams, 3)} g</span>
                        </div>
                        <div className="summary-row">
                            <span>+ Polish (Added Weight)</span>
                            <span className="summary-value">{formatNumber(polishGrams, 3)} g</span>
                        </div>

                        <div
                            className="summary-row total"
                            style={{ marginTop: '0.6rem', paddingTop: '0.6rem', borderTop: '1px solid rgba(241,236,224,0.16)' }}
                        >
                            <span>Net Weight</span>
                            <span className="summary-value">{formatNumber(netGrams, 3)} g</span>
                        </div>

                        <div className="summary-row" style={{ marginTop: '1.1rem' }}>
                            <span>Metal Value (Net x Rate)</span>
                            <span className="summary-value">{(netGrams * data.purchase_rate_per_gram).toFixed(2)}</span>
                        </div>
                        <div className="summary-row">
                            <span>+ Labour Cost</span>
                            <span className="summary-value">{data.labour_cost.toFixed(2)}</span>
                        </div>

                        <div className="summary-row total">
                            <span>Total Price</span>
                            <span className="summary-value">{totalPrice.toFixed(2)}</span>
                        </div>

                        <div className="form-group" style={{ marginTop: '1rem' }}>
                            <label className="form-label" style={{ color: '#c7bfa9' }}>
                                Reason for change (optional)
                            </label>
                            <input
                                type="text"
                                className="form-input"
                                value={data.edit_reason}
                                onChange={(e) => setData('edit_reason', e.target.value)}
                                placeholder="e.g. corrected a weighing error"
                                maxLength={255}
                            />
                            {errors.edit_reason && <div className="mt-1 text-xs text-red-500">{errors.edit_reason}</div>}
                            <span style={{ fontSize: '0.78rem', opacity: 0.75, marginTop: '0.35rem', display: 'block' }}>
                                Only matters if you're changing the price or weight — it's saved to the audit trail.
                            </span>
                        </div>

                        <div className="form-actions" style={{ marginTop: '1rem', display: 'flex', flexDirection: 'column', gap: '0.6rem' }}>
                            <div style={{ display: 'flex', flexDirection: 'row', gap: '0.6rem' }}>
                                <button type="submit" className="btn-primary" style={{ flex: 1 }} disabled={processing || pendingAction !== null}>
                                    {pendingAction === 'save' ? 'Updating...' : 'Save'}
                                </button>
                                <button
                                    type="button"
                                    className="btn-secondary"
                                    style={{ flex: 1 }}
                                    disabled={processing || pendingAction !== null}
                                    onClick={() => submit('print')}
                                >
                                    {pendingAction === 'print' ? (printItem ? 'Printing...' : 'Updating...') : 'Print'}
                                </button>
                            </div>
                            <Link
                                href="/inventory"
                                style={{
                                    textAlign: 'center',
                                    color: 'rgba(241,236,224,0.85)',
                                    fontSize: '0.9rem',
                                    textDecoration: 'underline',
                                    padding: '0.5rem',
                                }}
                            >
                                Cancel & Return
                            </Link>
                        </div>
                    </div>
                </form>

                <div className="print-only">
                    {printItem && <ItemEntryPrintSlip item={printItem} shop={shop} printerType={printerType} printDimensions={printDimensions} />}
                </div>
            </div>

            <style>{`
                .print-only { display: none; }
                @media print {
                    .print-only { display: block !important; }
                }
            `}</style>
        </AppLayout>
    );
}
