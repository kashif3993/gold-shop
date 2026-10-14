import { Head, useForm, usePage } from '@inertiajs/react';
import { useState, useEffect, useMemo, useRef } from 'react';
import AppLayout from '@/layouts/app-layout';
import { gramsToTraditional, traditionalToGrams, carryTraditional, formatNumber } from '@/utils/weight';
import { useScaleReading } from '@/utils/scale';
import { Info, AlertCircle, ChevronDown, Check } from 'lucide-react';
import ItemEntryPrintSlip from '@/components/item-entry-print-slip';
import '../../../css/item-entry.css';
import '../../../css/item-entry-print.css';

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

interface ItemCreateProps {
    metals: Metal[];
    purities: Purity[];
    parties: Party[];
    currentRates?: Record<string, string | number>;
    shop: Shop;
    printerType: 'regular' | 'thermal';
    printDimensions: PrintDimensions;
}

const BULLION_TYPES = ['biscuit', 'nugget', 'bar', 'coin', 'piece'];

const TYPE_ABBREV: Record<string, string> = {
    ring: 'RNG', bangle: 'BNG', necklace: 'NCK', earring: 'ERN',
    bracelet: 'BRC', chain: 'CHN', pendant: 'PND', locket: 'LCK',
    nose_pin: 'NSP', tops: 'TPS', biscuit: 'BSC', nugget: 'NGT',
    bar: 'BAR', coin: 'CON', piece: 'PCE', other: 'OTH',
};

const METAL_ABBREV: Record<string, string> = {
    gold: 'GLD', silver: 'SLV', platinum: 'PLT',
};

function previewItemCode(metalName: string, itemType: string): string {
    const metalKey = metalName.toLowerCase().trim();
    const metalAbbr = METAL_ABBREV[metalKey] ?? metalName.slice(0, 3).toUpperCase();
    const typeKey = itemType.toLowerCase().trim();
    const typeAbbr = TYPE_ABBREV[typeKey] ?? itemType.replace(/_/g, '').slice(0, 3).toUpperCase();
    const now = new Date();
    const yy = String(now.getFullYear()).slice(2);
    const mm = String(now.getMonth() + 1).padStart(2, '0');
    const dd = String(now.getDate()).padStart(2, '0');
    return `${metalAbbr}-${typeAbbr}-${yy}${mm}${dd}-XXXX`;
}

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
    const metalPurities = puritiesList.filter(p => !metalId || String(p.metal_type_id) === String(metalId));
    if (metalPurities.length === 0) return '';

    if (isBullion(itemType)) {
        // Default to 24K for bullion/biscuit/nugget
        const p24 = metalPurities.find(p => is24KPurity(p.name));
        if (p24) return p24.id;
    } else {
        // Default to 22K for jewelry
        const p22 = metalPurities.find(p => is22KPurity(p.name));
        if (p22) return p22.id;
    }

    // Fallback: If ring, pick first non-24K purity
    if (itemType.toLowerCase() === 'ring') {
        const non24 = metalPurities.find(p => !is24KPurity(p.name));
        if (non24) return non24.id;
    }

    return metalPurities[0]?.id || '';
}

/* ─── Inline Custom Select ─────────────────────────────────────────────── */
interface CsOption { value: string; label: string; disabled?: boolean; }
interface CsGroup  { label: string; options: CsOption[]; }
interface CustomSelectProps {
    value: string;
    onValueChange: (v: string) => void;
    placeholder?: string;
    options?: CsOption[];
    groups?: CsGroup[];
}
function CustomSelect({ value, onValueChange, placeholder = 'Select...', options, groups }: CustomSelectProps) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const wrapRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const allOpts = groups ? groups.flatMap(g => g.options) : (options ?? []);
    const selectedLabel = allOpts.find(o => o.value === value)?.label || '';

    useEffect(() => {
        const h = (e: MouseEvent) => {
            if (wrapRef.current && !wrapRef.current.contains(e.target as Node)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', h);
        return () => document.removeEventListener('mousedown', h);
    }, []);

    useEffect(() => {
        if (!open) setSearch('');
    }, [open]);

    const pick = (v: string) => { onValueChange(v); setOpen(false); };

    const filterOptions = (opts: CsOption[]) => {
        if (!search) return opts;
        const q = search.toLowerCase();
        return opts.filter(o => o.label.toLowerCase().includes(q));
    };

    const renderOpts = (opts: CsOption[]) => {
        const filtered = filterOptions(opts);
        if (filtered.length === 0) return null;
        return filtered.map(opt => (
            <div
                key={opt.value}
                className={`csel-option${opt.value === value ? ' selected' : ''}${opt.disabled ? ' disabled' : ''}`}
                onMouseDown={e => { e.preventDefault(); if (!opt.disabled) pick(opt.value); }}
            >
                {opt.value === value && <Check size={11} className="csel-check" />}
                {opt.label}
            </div>
        ));
    };

    const hasResults = groups 
        ? groups.some(g => filterOptions(g.options).length > 0)
        : filterOptions(allOpts).length > 0;

    return (
        <div ref={wrapRef} className="csel-wrapper">
            <div className={`form-select csel-combobox${open ? ' open' : ''}`} onClick={() => { setOpen(true); inputRef.current?.focus(); }}>
                <input
                    ref={inputRef}
                    type="text"
                    className="csel-combo-input"
                    placeholder={open ? 'Search...' : (selectedLabel || placeholder)}
                    value={open ? search : (selectedLabel || '')}
                    onChange={(e) => {
                        setSearch(e.target.value);
                        if (!open) setOpen(true);
                    }}
                    onFocus={() => setOpen(true)}
                    readOnly={!open}
                />
                <ChevronDown size={14} className={`csel-arrow${open ? ' up' : ''}`} onClick={(e) => { e.stopPropagation(); setOpen(o => !o); }} style={{cursor: 'pointer'}} />
            </div>
            {open && (
                <div className="csel-dropdown" style={{marginTop: '4px'}}>
                    <div className="csel-options-container" style={{maxHeight: '170px'}}>
                        {!hasResults && <div className="p-3 text-sm text-gray-500 text-center">No results found</div>}
                        {groups
                            ? groups.map(g => {
                                const rendered = renderOpts(g.options);
                                if (!rendered) return null;
                                return (
                                    <div key={g.label}>
                                        <div className="csel-group-label">{g.label}</div>
                                        {rendered}
                                    </div>
                                );
                              })
                            : renderOpts(allOpts)
                        }
                    </div>
                </div>
            )}
        </div>
    );
}
/* ─────────────────────────────────────────────────────────────────────── */

export default function ItemCreate({ metals = [], purities = [], parties = [], currentRates = {}, shop, printerType, printDimensions }: ItemCreateProps) {
    const { flash } = usePage().props as any;
    const [codePreview, setCodePreview] = useState('');
    // Once the shopkeeper types their own purchase rate, stop auto-filling it.
    const rateTouched = useRef(false);
    const initialMetalId = metals.length > 0 ? metals[0].id : '';
    const initialItemType = 'ring';
    const initialPurityId = resolveDefaultPurityId(initialItemType, initialMetalId, purities);

    const [gross, setGross] = useState({ gram: '', tola: '', masha: '', ratti: '', point: '' });
    const [stone, setStone] = useState({ gram: '' });
    const [polish, setPolish] = useState({ gram: '' });
    const [cutting, setCutting] = useState({ gram: '', tola: '', masha: '', ratti: '', point: '' });

    const [grossGrams, setGrossGrams] = useState(0);
    const [stoneGrams, setStoneGrams] = useState(0);
    const [polishGrams, setPolishGrams] = useState(0);
    const [cuttingGrams, setCuttingGrams] = useState(0);
    const [purityNotice, setPurityNotice] = useState<string>('');
    const [ringValidationError, setRingValidationError] = useState<string>('');
    const scale = useScaleReading();
    const scaleEverConnected = useRef(false);
    if (scale.ok) scaleEverConnected.current = true;

    const { data, setData, post, reset, processing, errors } = useForm({
        item_code: '',
        item_type: initialItemType,
        metal_type_id: initialMetalId,
        purity_id: initialPurityId,
        source_party_id: '',
        date_received: new Date().toISOString().slice(0, 10),
        gross_weight_grams: 0,
        stone_weight_grams: 0,
        cutting_loss_grams: 0,
        polish_weight_grams: 0,
        net_weight_grams: 0,
        labour_cost: 0,
        purchase_rate_per_gram: 0,
        purchase_price: 0,
    });

    // Filter available purities based on selected metal
    const availablePurities = useMemo(() => {
        if (!data.metal_type_id) return purities;
        return purities.filter(p => String(p.metal_type_id) === String(data.metal_type_id));
    }, [purities, data.metal_type_id]);

    // Update live code preview whenever metal or type changes
    useEffect(() => {
        const metal = metals.find(m => String(m.id) === String(data.metal_type_id));
        if (metal && data.item_type) {
            setCodePreview(previewItemCode(metal.name, data.item_type));
        }
    }, [data.metal_type_id, data.item_type, metals]);

    // Today's rate for the chosen purity (from Rate Management), if any.
    const liveRate = data.purity_id != null && data.purity_id !== ''
        ? Number(currentRates[String(data.purity_id)])
        : NaN;
    const hasLiveRate = Number.isFinite(liveRate) && liveRate > 0;

    // Pre-fill the purchase rate with today's rate for the selected purity, until
    // the shopkeeper overrides it. This keeps the summary price live.
    useEffect(() => {
        if (!rateTouched.current && hasLiveRate) {
            setData('purchase_rate_per_gram', liveRate);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data.purity_id, hasLiveRate, liveRate]);

    const handleItemTypeChange = (newType: string) => {
        const isBullionType = isBullion(newType);
        const metalPurities = purities.filter(p => !data.metal_type_id || String(p.metal_type_id) === String(data.metal_type_id));
        
        let newPurityId = data.purity_id;
        let notice = '';
        const ringErr = '';

        const currentPurity = purities.find(p => String(p.id) === String(data.purity_id));

        if (isBullionType) {
            // Default to 24K purity for biscuit, nugget, bar, coin, piece
            const p24 = metalPurities.find(p => is24KPurity(p.name));
            if (p24) {
                newPurityId = p24.id;
                notice = `Defaulted purity to ${p24.name} (24K pure) for ${newType}.`;
            }
        } else {
            // Jewelry item
            if (newType === 'ring' && currentPurity && is24KPurity(currentPurity.name)) {
                // Ring cannot be 24K - switch to 22K
                const p22 = metalPurities.find(p => is22KPurity(p.name)) || metalPurities.find(p => !is24KPurity(p.name));
                if (p22) {
                    newPurityId = p22.id;
                    notice = `Rings cannot be made in 24K gold (pure gold is too soft for rings). Purity automatically set to ${p22.name}.`;
                }
            } else if (currentPurity && is24KPurity(currentPurity.name)) {
                // Switched from bullion (24K) to standard jewelry item, default to 22K
                const p22 = metalPurities.find(p => is22KPurity(p.name));
                if (p22) {
                    newPurityId = p22.id;
                    notice = `Defaulted purity to ${p22.name} for jewelry item.`;
                }
            }
        }

        setPurityNotice(notice);
        setRingValidationError(ringErr);

        setData(prev => ({
            ...prev,
            item_type: newType,
            purity_id: newPurityId,
        }));
    };

    const handleMetalChange = (newMetalId: string) => {
        const newPurityId = resolveDefaultPurityId(data.item_type, newMetalId, purities);
        setPurityNotice('');
        setRingValidationError('');
        setData(prev => ({
            ...prev,
            metal_type_id: newMetalId,
            purity_id: newPurityId,
        }));
    };

    const handlePurityChange = (newPurityId: string) => {
        const selectedPurity = purities.find(p => String(p.id) === String(newPurityId));
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
            const needsCarry =
                (parseFloat(draft.point) || 0) >= 100 ||
                (parseFloat(draft.ratti) || 0) >= 8 ||
                (parseFloat(draft.masha) || 0) >= 12;
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

    // Polish is taken as weight added back into net weight (the shop's profit
    // margin lives here, not as a separate Rs charge) — so it flows straight
    // into the metal value below, rather than being added on its own.
    const netGrams = Math.max(0, grossGrams - stoneGrams - cuttingGrams + polishGrams);
    const totalPrice = (netGrams * data.purchase_rate_per_gram) + data.labour_cost;

    useEffect(() => {
        setData(prev => ({
            ...prev,
            gross_weight_grams: Number(grossGrams.toFixed(3)),
            stone_weight_grams: Number(stoneGrams.toFixed(3)),
            cutting_loss_grams: Number(cuttingGrams.toFixed(3)),
            polish_weight_grams: Number(polishGrams.toFixed(3)),
            net_weight_grams: Number(netGrams.toFixed(3)),
            purchase_price: totalPrice,
        }));
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [grossGrams, stoneGrams, cuttingGrams, polishGrams, data.purchase_rate_per_gram, data.labour_cost]);

    const [grossWeightError, setGrossWeightError] = useState<string>('');

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

    // Saving stays on this same page (preserveState) instead of navigating
    // away — a shopkeeper entering a batch of items shouldn't be bounced to
    // a different screen after every single one. The form clears itself
    // once the save lands; "Save & Print" additionally waits for the
    // server's flashed `createdItem` (it carries the real, server-assigned
    // item_code) before printing and then clearing.
    const [pendingAction, setPendingAction] = useState<'save' | 'print' | null>(null);
    const [printItem, setPrintItem] = useState<any>(null);

    function resetForm() {
        reset();
        setGross({ gram: '', tola: '', masha: '', ratti: '', point: '' });
        setStone({ gram: '' });
        setPolish({ gram: '' });
        setCutting({ gram: '', tola: '', masha: '', ratti: '', point: '' });
        setGrossGrams(0);
        setStoneGrams(0);
        setPolishGrams(0);
        setCuttingGrams(0);
        setPurityNotice('');
        setRingValidationError('');
        setGrossWeightError('');
        rateTouched.current = false;
    }

    useEffect(() => {
        if (!pendingAction) return;
        post(route('items.store'), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                // A plain save clears right away; "print" clears once the
                // print effect below has actually fired the print dialog
                // (triggered separately, by the flashed createdItem arriving).
                if (pendingAction === 'save') {
                    resetForm();
                    setPendingAction(null);
                }
            },
            // Validation failed server-side — leave what was typed in place
            // (don't wipe it) and just re-enable the buttons.
            onError: () => setPendingAction(null),
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [pendingAction]);

    // The server flashes `createdItem` on every save; only act on it when
    // this visit was actually a "Save & Print" (a plain save ignores it).
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
            resetForm();
            setPendingAction(null);
        }, 150);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [printItem]);

    const submit = (action: 'save' | 'print') => {
        // Client-side guard: verify gross weight is entered
        if (grossGrams <= 0) {
            setGrossWeightError('Please enter a valid gross weight greater than 0.');
            return;
        }
        setGrossWeightError('');

        // Client-side guard: verify ring is not 24K
        const currentPurity = purities.find(p => String(p.id) === String(data.purity_id));
        if (data.item_type === 'ring' && currentPurity && is24KPurity(currentPurity.name)) {
            setRingValidationError('Ring items cannot be created with 24K purity. Please select 22K, 21K, or 18K.');
            return;
        }

        setPendingAction(action);
    };

    return (
        <AppLayout>
            <Head title="Create item" />

            <div className="item-entry-container">
                <div className="item-entry-header">
                    <h1 className="item-entry-title">Create New Item</h1>
                </div>

                {flash?.success && (
                    <div className="item-code-preview" style={{ background: '#eff6ff', borderColor: '#bfdbfe', color: '#1e40af', marginBottom: '0.75rem' }}>
                        {flash.success} Ready for the next one.
                    </div>
                )}

                <form onSubmit={e => { e.preventDefault(); submit('save'); }} className="item-entry-form">

                        {/* Basic Details Card */}
                        <div className="item-card">
                            <h2 className="card-title">Basic Details</h2>
                            
                            <div className="form-grid">
                                {/* Item Type */}
                                <div className="form-group">
                                    <label className="form-label">Item Type</label>
                                    <CustomSelect
                                        value={data.item_type}
                                        onValueChange={handleItemTypeChange}
                                        groups={[
                                            { label: 'Jewelry Items (Default: 22K)', options: [
                                                { value: 'ring', label: 'Ring' },
                                                { value: 'bangle', label: 'Bangle' },
                                                { value: 'necklace', label: 'Necklace' },
                                                { value: 'earring', label: 'Earring' },
                                                { value: 'bracelet', label: 'Bracelet' },
                                                { value: 'chain', label: 'Chain' },
                                                { value: 'pendant', label: 'Pendant' },
                                                { value: 'locket', label: 'Locket' },
                                                { value: 'nose_pin', label: 'Nose Pin' },
                                                { value: 'tops', label: 'Tops' },
                                                { value: 'other', label: 'Other Jewelry' },
                                            ]},
                                            { label: 'Bullion & Raw Items (Default: 24K)', options: [
                                                { value: 'biscuit', label: 'Biscuit' },
                                                { value: 'bar', label: 'Bar' },
                                                { value: 'nugget', label: 'Nugget' },
                                                { value: 'coin', label: 'Coin' },
                                                { value: 'piece', label: 'Raw Gold / Lagdi' },
                                            ]},
                                        ]}
                                    />
                                    {errors.item_type && <div className="text-red-500 text-xs mt-1">{errors.item_type}</div>}
                                </div>

                                {/* Metal Type */}
                                <div className="form-group">
                                    <label className="form-label">Metal Type</label>
                                    <CustomSelect
                                        value={String(data.metal_type_id)}
                                        onValueChange={handleMetalChange}
                                        placeholder="Select Metal"
                                        options={metals.map(m => ({ value: String(m.id), label: m.name }))}
                                    />
                                    {errors.metal_type_id && <div className="text-red-500 text-xs mt-1">{errors.metal_type_id}</div>}
                                </div>

                                {/* Purity */}
                                <div className="form-group">
                                    <label className="form-label">Purity</label>
                                    <CustomSelect
                                        value={String(data.purity_id)}
                                        onValueChange={handlePurityChange}
                                        placeholder="Select Purity"
                                        options={availablePurities.map(p => {
                                            const isRing24K = data.item_type === 'ring' && is24KPurity(p.name);
                                            return { value: String(p.id), label: `${p.name}${isRing24K ? ' — (Not allowed for rings)' : ''}`, disabled: isRing24K };
                                        })}
                                    />
                                    {errors.purity_id && <div className="text-red-500 text-xs mt-1 font-medium">{errors.purity_id}</div>}
                                    {ringValidationError && (
                                        <div style={{ color: '#dc2626', fontSize: '0.8rem', marginTop: '0.35rem', display: 'flex', alignItems: 'center', gap: '0.25rem' }}>
                                            <AlertCircle size={14} />
                                            <span>{ringValidationError}</span>
                                        </div>
                                    )}
                                    {purityNotice && (
                                        <div style={{
                                            marginTop: '0.5rem', padding: '0.5rem 0.75rem', borderRadius: '6px',
                                            backgroundColor: '#eff6ff', border: '1px solid #bfdbfe',
                                            color: '#1e40af', fontSize: '0.8rem', display: 'flex', alignItems: 'center', gap: '0.4rem'
                                        }}>
                                            <Info size={15} style={{ flexShrink: 0, color: '#2563eb' }} />
                                            <span>{purityNotice}</span>
                                        </div>
                                    )}
                                </div>

                                {/* Source Party */}
                                <div className="form-group">
                                    <label className="form-label">Source Party</label>
                                    <CustomSelect
                                        value={data.source_party_id ? String(data.source_party_id) : '__none__'}
                                        onValueChange={val => setData('source_party_id', val === '__none__' ? '' : val)}
                                        placeholder="Select Party (Optional)"
                                        options={[
                                            { value: '__none__', label: 'Select Party (Optional)' },
                                            ...parties.map(p => ({ value: String(p.id), label: p.name })),
                                        ]}
                                    />
                                    {errors.source_party_id && <div className="text-red-500 text-xs mt-1">{errors.source_party_id}</div>}
                                </div>

                                {/* Date Received */}
                                <div className="form-group">
                                    <label className="form-label">Date Received</label>
                                    <input type="date" className="form-input" value={data.date_received} onChange={e => setData('date_received', e.target.value)} />
                                    {errors.date_received && <div className="text-red-500 text-xs mt-1">{errors.date_received}</div>}
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
                                            <label className="form-label" style={{textTransform: 'capitalize'}}>{u}</label>
                                            <input
                                                type="number" step="any" min="0" className="form-input"
                                                value={(gross as any)[u]}
                                                onFocus={() => { grossWeightFocused.current = true; }}
                                                onBlur={() => { grossWeightFocused.current = false; }}
                                                onChange={(e) => {
                                                    setGrossWeightError('');
                                                    handleUnitChange(setGross, gross, u, e.target.value, setGrossGrams);
                                                }}
                                            />
                                        </div>
                                    ))}
                                </div>
                                {(grossWeightError || errors.gross_weight_grams) && (
                                    <div style={{ color: '#dc2626', fontSize: '0.825rem', marginTop: '0.5rem', display: 'flex', alignItems: 'center', gap: '0.3rem' }}>
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
                                            type="number" step="any" min="0" className="form-input"
                                            value={stone.gram}
                                            onChange={(e) => handleStoneGramChange(e.target.value)}
                                        />
                                    </div>
                                    <div className="form-group">
                                        <label className="form-label">Polish Weight (+)</label>
                                        <input
                                            type="number" step="any" min="0" className="form-input"
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
                                            <label className="form-label" style={{textTransform: 'capitalize'}}>{u}</label>
                                            <input
                                                type="number" step="any" min="0" className="form-input"
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
                                        type="number" step="any" min="0" className="form-input"
                                        value={data.purchase_rate_per_gram || ''}
                                        onChange={e => { rateTouched.current = true; setData('purchase_rate_per_gram', parseFloat(e.target.value) || 0); }}
                                    />
                                    {hasLiveRate && (
                                        <div className="rate-hint">
                                            Today's {availablePurities.find(p => String(p.id) === String(data.purity_id))?.name} rate:{' '}
                                            <strong>Rs {formatNumber(liveRate, 2)}</strong> / g
                                            {Number(data.purchase_rate_per_gram) !== liveRate && (
                                                <button
                                                    type="button"
                                                    className="rate-hint-use"
                                                    onClick={() => { rateTouched.current = true; setData('purchase_rate_per_gram', liveRate); }}
                                                >
                                                    use this
                                                </button>
                                            )}
                                        </div>
                                    )}
                                </div>
                                <div className="form-group">
                                    <label className="form-label">Labour Cost (Total)</label>
                                    <input type="number" step="any" min="0" className="form-input" value={data.labour_cost || ''} onChange={e => setData('labour_cost', parseFloat(e.target.value) || 0)} />
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

                            <div className="summary-row total" style={{marginTop: '0.6rem', paddingTop: '0.6rem', borderTop: '1px solid rgba(241,236,224,0.16)'}}>
                                <span>Net Weight</span>
                                <span className="summary-value">{formatNumber(netGrams, 3)} g</span>
                            </div>

                            <div className="summary-row" style={{marginTop: '1.1rem'}}>
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

                            <div className="form-actions" style={{marginTop: '1.1rem', flexDirection: 'row', gap: '0.6rem'}}>
                                <button type="submit" className="btn-primary" style={{flex: 1}} disabled={processing || pendingAction !== null}>
                                    {pendingAction === 'save' ? 'Saving...' : 'Save'}
                                </button>
                                <button
                                    type="button"
                                    className="btn-secondary"
                                    style={{flex: 1}}
                                    disabled={processing || pendingAction !== null}
                                    onClick={() => submit('print')}
                                >
                                    {pendingAction === 'print' ? (printItem ? 'Printing...' : 'Saving...') : 'Print'}
                                </button>
                            </div>
                        </div>

                </form>

                <div className="print-only">
                    {printItem && (
                        <ItemEntryPrintSlip item={printItem} shop={shop} printerType={printerType} printDimensions={printDimensions} />
                    )}
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
