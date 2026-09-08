import { Head, useForm, Link } from '@inertiajs/react';
import { useState, useEffect, useMemo } from 'react';
import AppLayout from '@/layouts/app-layout';
import { gramsToTraditional, traditionalToGrams, carryTraditional, formatNumber } from '@/utils/weight';
import { Info, AlertCircle, ArrowLeft, Printer } from 'lucide-react';
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

interface ItemEditProps {
    item: any;
    metals: Metal[];
    purities: Purity[];
    parties: Party[];
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
    const metalPurities = puritiesList.filter(p => !metalId || String(p.metal_type_id) === String(metalId));
    if (metalPurities.length === 0) return '';

    if (isBullion(itemType)) {
        const p24 = metalPurities.find(p => is24KPurity(p.name));
        if (p24) return p24.id;
    } else {
        const p22 = metalPurities.find(p => is22KPurity(p.name));
        if (p22) return p22.id;
    }

    if (itemType.toLowerCase() === 'ring') {
        const non24 = metalPurities.find(p => !is24KPurity(p.name));
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

export default function ItemEdit({ item, metals = [], purities = [], parties = [] }: ItemEditProps) {
    const initialGrossGrams = parseFloat(item.gross_weight_grams) || 0;
    const initialStoneGrams = parseFloat(item.stone_weight_grams) || 0;
    const initialCuttingGrams = parseFloat(item.cutting_loss_grams) || 0;

    const [gross, setGross] = useState(getUnitsFromGrams(initialGrossGrams));
    const [stone, setStone] = useState({ gram: initialStoneGrams > 0 ? String(initialStoneGrams) : '' });
    const [cutting, setCutting] = useState(getUnitsFromGrams(initialCuttingGrams));

    const [grossGrams, setGrossGrams] = useState(initialGrossGrams);
    const [stoneGrams, setStoneGrams] = useState(initialStoneGrams);
    const [cuttingGrams, setCuttingGrams] = useState(initialCuttingGrams);

    const [purityNotice, setPurityNotice] = useState<string>('');
    const [ringValidationError, setRingValidationError] = useState<string>('');
    const [grossWeightError, setGrossWeightError] = useState<string>('');

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
        net_weight_grams: parseFloat(item.net_weight_grams) || 0,
        labour_cost: parseFloat(item.labour_cost) || 0,
        polish_cost: parseFloat(item.polish_cost) || 0,
        purchase_rate_per_gram: parseFloat(item.purchase_rate_per_gram) || 0,
        purchase_price: parseFloat(item.purchase_price) || 0,
    });

    const availablePurities = useMemo(() => {
        if (!data.metal_type_id) return purities;
        return purities.filter(p => String(p.metal_type_id) === String(data.metal_type_id));
    }, [purities, data.metal_type_id]);

    const handleItemTypeChange = (newType: string) => {
        const isBullionType = isBullion(newType);
        const metalPurities = purities.filter(p => !data.metal_type_id || String(p.metal_type_id) === String(data.metal_type_id));
        
        let newPurityId = data.purity_id;
        let notice = '';
        let ringErr = '';

        const currentPurity = purities.find(p => String(p.id) === String(data.purity_id));

        if (isBullionType) {
            const p24 = metalPurities.find(p => is24KPurity(p.name));
            if (p24) {
                newPurityId = p24.id;
                notice = `Defaulted purity to ${p24.name} (24K pure) for ${newType}.`;
            }
        } else {
            if (newType === 'ring' && currentPurity && is24KPurity(currentPurity.name)) {
                const p22 = metalPurities.find(p => is22KPurity(p.name)) || metalPurities.find(p => !is24KPurity(p.name));
                if (p22) {
                    newPurityId = p22.id;
                    notice = `Rings cannot be 24K gold (pure gold is too soft). Purity automatically set to ${p22.name}.`;
                }
            } else if (currentPurity && is24KPurity(currentPurity.name)) {
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

    const netGrams = Math.max(0, grossGrams - stoneGrams - cuttingGrams);
    const totalPrice = (netGrams * data.purchase_rate_per_gram) + data.labour_cost + data.polish_cost;

    useEffect(() => {
        setData(data => ({
            ...data,
            gross_weight_grams: Number(grossGrams.toFixed(3)),
            stone_weight_grams: Number(stoneGrams.toFixed(3)),
            cutting_loss_grams: Number(cuttingGrams.toFixed(3)),
            net_weight_grams: Number(netGrams.toFixed(3)),
            purchase_price: totalPrice,
        }));
    }, [grossGrams, stoneGrams, cuttingGrams, netGrams, data.purchase_rate_per_gram, data.labour_cost, data.polish_cost]);

    const submit = (e: any) => {
        e.preventDefault();

        if (grossGrams <= 0) {
            setGrossWeightError('Please enter a valid gross weight greater than 0.');
            return;
        }
        setGrossWeightError('');

        const currentPurity = purities.find(p => String(p.id) === String(data.purity_id));
        if (data.item_type === 'ring' && currentPurity && is24KPurity(currentPurity.name)) {
            setRingValidationError('Ring items cannot be saved with 24K purity. Please select 22K, 21K, or 18K.');
            return;
        }

        put(route('items.update', item.id));
    };

    return (
        <AppLayout>
            <Head title={`Edit Item - ${item.item_code}`} />

            <div className="item-entry-container">
                <div className="item-entry-header" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '1rem' }}>
                    <div>
                        <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', marginBottom: '0.5rem' }}>
                            <Link href="/inventory" className="action-btn" style={{ padding: '0.4rem', borderRadius: '6px', border: '1px solid #d1d5db', textDecoration: 'none', display: 'inline-flex', color: '#4b5563' }}>
                                <ArrowLeft size={18} />
                            </Link>
                            <h1 className="item-entry-title" style={{ margin: 0 }}>Edit Item: {item.item_code}</h1>
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
                                gap: '0.35rem'
                            }}
                        >
                            <Printer size={15} /> Print Tag
                        </Link>
                        <Link href="/inventory" className="filter-clear" style={{ textDecoration: 'none', display: 'inline-block' }}>
                            Back to Inventory
                        </Link>
                    </div>
                </div>

                <form onSubmit={submit} className="item-entry-form">
                    
                    {/* Left Column (Forms) */}
                    <div>
                        {/* Basic Details Card */}
                        <div className="item-card" style={{ marginBottom: '2rem' }}>
                            <h2 className="card-title">Basic Details</h2>
                            
                            <div className="form-grid">
                                <div className="form-group">
                                    <label className="form-label">Item Code</label>
                                    <input
                                        type="text"
                                        className="form-input"
                                        value={data.item_code}
                                        onChange={e => setData('item_code', e.target.value)}
                                        required
                                    />
                                    {errors.item_code && <div className="text-red-500 text-xs mt-1">{errors.item_code}</div>}
                                </div>

                                <div className="form-group">
                                    <label className="form-label">Status</label>
                                    <select
                                        className="form-select"
                                        value={data.status}
                                        onChange={e => setData('status', e.target.value)}
                                    >
                                        <option value="in_stock">In Stock</option>
                                        <option value="sold">Sold</option>
                                        <option value="bought_back">Bought Back</option>
                                    </select>
                                    {errors.status && <div className="text-red-500 text-xs mt-1">{errors.status}</div>}
                                </div>

                                <div className="form-group">
                                    <label className="form-label">Item Type</label>
                                    <select
                                        className="form-select"
                                        value={data.item_type}
                                        onChange={e => handleItemTypeChange(e.target.value)}
                                    >
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
                                    {errors.item_type && <div className="text-red-500 text-xs mt-1">{errors.item_type}</div>}
                                </div>

                                <div className="form-group">
                                    <label className="form-label">Metal Type</label>
                                    <select
                                        className="form-select"
                                        value={data.metal_type_id}
                                        onChange={e => handleMetalChange(e.target.value)}
                                    >
                                        <option value="">Select Metal</option>
                                        {metals.map(m => (
                                            <option key={m.id} value={m.id}>{m.name}</option>
                                        ))}
                                    </select>
                                    {errors.metal_type_id && <div className="text-red-500 text-xs mt-1">{errors.metal_type_id}</div>}
                                </div>

                                <div className="form-group">
                                    <label className="form-label">Purity</label>
                                    <select
                                        className="form-select"
                                        value={data.purity_id}
                                        onChange={e => handlePurityChange(e.target.value)}
                                    >
                                        <option value="">Select Purity</option>
                                        {availablePurities.map(p => {
                                            const isRing24K = data.item_type === 'ring' && is24KPurity(p.name);
                                            return (
                                                <option
                                                    key={p.id}
                                                    value={p.id}
                                                    disabled={isRing24K}
                                                >
                                                    {p.name} {isRing24K ? '— (Not allowed for rings)' : ''}
                                                </option>
                                            );
                                        })}
                                    </select>
                                    {errors.purity_id && <div className="text-red-500 text-xs mt-1 font-medium">{errors.purity_id}</div>}
                                    {ringValidationError && (
                                        <div style={{ color: '#dc2626', fontSize: '0.8rem', marginTop: '0.35rem', display: 'flex', alignItems: 'center', gap: '0.25rem' }}>
                                            <AlertCircle size={14} />
                                            <span>{ringValidationError}</span>
                                        </div>
                                    )}
                                    {purityNotice && (
                                        <div style={{
                                            marginTop: '0.5rem',
                                            padding: '0.5rem 0.75rem',
                                            borderRadius: '6px',
                                            backgroundColor: '#eff6ff',
                                            border: '1px solid #bfdbfe',
                                            color: '#1e40af',
                                            fontSize: '0.8rem',
                                            display: 'flex',
                                            alignItems: 'center',
                                            gap: '0.4rem'
                                        }}>
                                            <Info size={15} style={{ flexShrink: 0, color: '#2563eb' }} />
                                            <span>{purityNotice}</span>
                                        </div>
                                    )}
                                </div>

                                <div className="form-group">
                                    <label className="form-label">Source Party</label>
                                    <select className="form-select" value={data.source_party_id} onChange={e => setData('source_party_id', e.target.value)}>
                                        <option value="">Select Party (Optional)</option>
                                        {parties.map(p => (
                                            <option key={p.id} value={p.id}>{p.name}</option>
                                        ))}
                                    </select>
                                    {errors.source_party_id && <div className="text-red-500 text-xs mt-1">{errors.source_party_id}</div>}
                                </div>

                                <div className="form-group">
                                    <label className="form-label">Date Received</label>
                                    <input type="date" className="form-input" value={data.date_received} onChange={e => setData('date_received', e.target.value)} />
                                    {errors.date_received && <div className="text-red-500 text-xs mt-1">{errors.date_received}</div>}
                                </div>
                            </div>
                        </div>

                        {/* Weight Management Card */}
                        <div className="item-card" style={{ marginBottom: '2rem' }}>
                            <h2 className="card-title">Weight Management</h2>
                            
                            <div className="weight-section">
                                <div className="weight-title">Gross Weight</div>
                                <div className="weight-grid">
                                    {(['gram', 'tola', 'masha', 'ratti', 'point'] as const).map((u) => (
                                        <div key={u} className="form-group">
                                            <label className="form-label" style={{textTransform: 'capitalize'}}>{u}</label>
                                            <input
                                                type="number" step="any" min="0" className="form-input"
                                                value={(gross as any)[u]}
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
                                <div className="weight-title">Stone Weight (Deduction)</div>
                                <div className="weight-grid" style={{ gridTemplateColumns: '1fr' }}>
                                    <div className="form-group">
                                        <label className="form-label">Gram</label>
                                        <input
                                            type="number" step="any" min="0" className="form-input"
                                            value={stone.gram}
                                            onChange={(e) => handleStoneGramChange(e.target.value)}
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
                                    <input type="number" step="any" min="0" className="form-input" value={data.purchase_rate_per_gram || ''} onChange={e => setData('purchase_rate_per_gram', parseFloat(e.target.value) || 0)} />
                                </div>
                                <div className="form-group">
                                    <label className="form-label">Labour Cost (Total)</label>
                                    <input type="number" step="any" min="0" className="form-input" value={data.labour_cost || ''} onChange={e => setData('labour_cost', parseFloat(e.target.value) || 0)} />
                                </div>
                                <div className="form-group">
                                    <label className="form-label">Polish Cost (Total)</label>
                                    <input type="number" step="any" min="0" className="form-input" value={data.polish_cost || ''} onChange={e => setData('polish_cost', parseFloat(e.target.value) || 0)} />
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Right Column (Summary & Actions) */}
                    <div>
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
                            
                            <div className="summary-row total" style={{marginTop: '1rem', paddingTop: '1rem', borderTop: '1px solid rgba(255,255,255,0.2)'}}>
                                <span>Net Weight</span>
                                <span className="summary-value">{formatNumber(netGrams, 3)} g</span>
                            </div>

                            <div className="summary-row" style={{marginTop: '2rem'}}>
                                <span>Metal Value (Net x Rate)</span>
                                <span className="summary-value">{(netGrams * data.purchase_rate_per_gram).toFixed(2)}</span>
                            </div>
                            <div className="summary-row">
                                <span>+ Labour Cost</span>
                                <span className="summary-value">{data.labour_cost.toFixed(2)}</span>
                            </div>
                            <div className="summary-row">
                                <span>+ Polish Cost</span>
                                <span className="summary-value">{data.polish_cost.toFixed(2)}</span>
                            </div>

                            <div className="summary-row total">
                                <span>Total Price</span>
                                <span className="summary-value">{totalPrice.toFixed(2)}</span>
                            </div>

                            <div className="form-actions" style={{marginTop: '2rem', display: 'flex', flexDirection: 'column', gap: '0.75rem'}}>
                                <button type="submit" className="btn-primary" style={{width: '100%'}} disabled={processing}>
                                    {processing ? 'Updating...' : 'Update Item'}
                                </button>
                                <Link
                                    href="/inventory"
                                    style={{
                                        textAlign: 'center',
                                        color: 'rgba(255,255,255,0.85)',
                                        fontSize: '0.9rem',
                                        textDecoration: 'underline',
                                        padding: '0.5rem'
                                    }}
                                >
                                    Cancel & Return
                                </Link>
                            </div>
                        </div>
                    </div>

                </form>
            </div>
        </AppLayout>
    );
}
