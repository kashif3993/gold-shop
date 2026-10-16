import BankQrPaymentModal from '@/components/bank-qr-payment-modal';
import PosPrintSlip from '@/components/pos-print-slip';
import RateTicker from '@/components/rate-ticker';
import { POSProvider, usePOS } from '@/context/POSContext';
import AppLayout from '@/layouts/app-layout';
import { cn, genId } from '@/lib/utils';
import { Head, usePage } from '@inertiajs/react';
import axios from 'axios';
import { Plus, QrCode, Search, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import '../../../css/pos-print.css';
import '../../../css/pos.css';

// --- Utility to format currency as PKR ---
const formatPKR = (amount: number) => {
    return new Intl.NumberFormat('en-PK', {
        style: 'currency',
        currency: 'PKR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    })
        .format(amount)
        .replace('PKR', 'Rs.');
};

// Removed POSHeader to prevent double-header with AppLayout

function POSCart() {
    const { state, dispatch } = usePOS();
    const { metals, purities, currentRates, features } = usePage().props as any;
    const exchangeEnabled = features?.exchange_enabled ?? true;
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState<any[]>([]);
    const [isSearching, setIsSearching] = useState(false);

    useEffect(() => {
        if (!searchQuery.trim()) {
            setSearchResults([]);
            return;
        }

        const delayDebounceFn = setTimeout(async () => {
            setIsSearching(true);
            try {
                const response = await axios.get(`/api/v1/items/search?q=${encodeURIComponent(searchQuery)}`);
                setSearchResults(response.data.data || []);
            } catch (error) {
                console.error('Search error', error);
            } finally {
                setIsSearching(false);
            }
        }, 300);

        return () => clearTimeout(delayDebounceFn);
    }, [searchQuery]);

    const handleAddItem = (item: any) => {
        if (state.items.some((cartItem) => cartItem.id === item.id)) {
            alert('Item is already in the cart.');
            return;
        }

        // Detect this item's own live rate from its own purity — never a
        // shared number typed for the whole cart. A 22K ring and a 24K bar
        // each get priced from their own purity's current rate.
        const detectedRate = currentRates?.[item.purity_id];
        if (!detectedRate) {
            alert(`No current rate is set for ${item.purity_name || "this item's"} purity. Set one in Rate Management first.`);
            return;
        }

        dispatch({
            type: 'ADD_ITEM',
            payload: {
                id: item.id,
                item_code: item.item_code,
                item_type: item.item_type,
                metal_name: item.metal_name || 'Unknown',
                purity_name: item.purity_name || 'Unknown',
                gross_weight_grams: parseFloat(item.gross_weight_grams) || 0,
                stone_weight_grams: parseFloat(item.stone_weight_grams) || 0,
                cutting_loss_grams: parseFloat(item.cutting_loss_grams) || 0,
                net_weight_grams: parseFloat(item.net_weight_grams) || 0,
                labour_cost: parseFloat(item.labour_cost) || 0,
                polish_cost: parseFloat(item.polish_cost) || 0,
                detected_rate_per_gram: detectedRate.rate_per_gram,
                rate_per_gram: detectedRate.rate_per_gram,
            },
        });
        setSearchQuery('');
        setSearchResults([]);
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            // Barcode scanners usually send Enter after scanning
            if (searchResults.length === 1) {
                handleAddItem(searchResults[0]);
            } else if (searchResults.length > 1) {
                const exactMatch = searchResults.find((r) => r.item_code === searchQuery || r.qr_payload === searchQuery);
                if (exactMatch) {
                    handleAddItem(exactMatch);
                }
            }
        }
    };

    return (
        <div className="pos-cart-container">
            {/* Search Bar */}
            <div className="relative flex gap-4">
                <div className="relative flex-grow">
                    <div className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                        <QrCode className="h-5 w-5 text-gray-400" />
                    </div>
                    <input
                        type="text"
                        className="block w-full rounded-lg border border-gray-200 py-2 pr-12 pl-11 text-gray-900 placeholder-gray-400 shadow-sm focus:border-[#b38a36] focus:ring-[#b38a36]"
                        placeholder="Scan barcode or search item code (e.g. ITM-1042)..."
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        onKeyDown={handleKeyDown}
                        autoFocus
                    />
                    <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-4">
                        {isSearching ? (
                            <div className="h-4 w-4 animate-spin rounded-full border-2 border-gray-400 border-t-transparent" />
                        ) : (
                            <Search className="h-5 w-5 text-gray-400" />
                        )}
                    </div>

                    {/* Search Results Dropdown */}
                    {searchResults.length > 0 && (
                        <div className="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg">
                            {searchResults.map((item) => (
                                <button
                                    key={item.id}
                                    onClick={() => handleAddItem(item)}
                                    className="flex w-full items-center justify-between border-b border-gray-100 px-4 py-3 text-left transition last:border-0 hover:bg-gray-50"
                                >
                                    <div>
                                        <div className="font-bold text-gray-900">{item.item_code}</div>
                                        <div className="text-xs text-gray-500">
                                            {item.item_type} - {item.metal_name} {item.purity_name}
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <div className="font-semibold text-gray-900">{item.net_weight_grams}g</div>
                                        <div className="text-xs text-[#c39b66]">Add to cart</div>
                                    </div>
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {/* Table */}
            <div className="flex min-h-[400px] flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[650px] divide-y divide-gray-200">
                        <thead className="bg-gray-50/50">
                            <tr>
                                <th scope="col" className="w-12 px-6 py-4 text-left text-[11px] font-bold tracking-wider text-gray-500 uppercase">
                                    #
                                </th>
                                <th scope="col" className="px-6 py-4 text-left text-[11px] font-bold tracking-wider text-gray-500 uppercase">
                                    Item Details
                                </th>
                                <th scope="col" className="px-6 py-4 text-right text-[11px] font-bold tracking-wider text-gray-500 uppercase">
                                    Net Weight
                                </th>
                                <th scope="col" className="px-6 py-4 text-right text-[11px] font-bold tracking-wider text-gray-500 uppercase">
                                    Labour
                                </th>
                                <th scope="col" className="px-6 py-4 text-right text-[11px] font-bold tracking-wider text-gray-500 uppercase">
                                    Polish
                                </th>
                                <th scope="col" className="px-6 py-4 text-right text-[11px] font-bold tracking-wider text-gray-500 uppercase">
                                    Calculated Price
                                    <div className="mt-0.5 text-[9px] tracking-normal text-[#c39b66] normal-case">Based on today's rate</div>
                                </th>
                                <th scope="col" className="relative w-12 px-6 py-4">
                                    <span className="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 bg-white">
                            {state.items.map((item, index) => {
                                const itemGoldValue = item.net_weight_grams * item.rate_per_gram;
                                const lineTotal = itemGoldValue + item.labour_cost + item.polish_cost;

                                return (
                                    <tr key={item.id} className="transition hover:bg-gray-50/50">
                                        <td className="px-6 py-5 text-sm font-medium whitespace-nowrap text-gray-400">{index + 1}</td>
                                        <td className="px-6 py-5">
                                            <div className="mb-1.5 text-sm font-bold text-gray-900">
                                                {item.purity_name} {item.metal_name} {item.item_type}
                                            </div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="inline-flex items-center rounded bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-800">
                                                    {item.item_code}
                                                </span>
                                                <span className="inline-flex items-center rounded border border-amber-100 bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-800">
                                                    {item.purity_name} Hallmark
                                                </span>
                                                <EditableRate
                                                    detectedRate={item.detected_rate_per_gram}
                                                    rate={item.rate_per_gram}
                                                    onChange={(rate) =>
                                                        dispatch({ type: 'UPDATE_ITEM_RATE', payload: { id: item.id, rate_per_gram: rate } })
                                                    }
                                                />
                                            </div>
                                        </td>
                                        <td className="px-6 py-5 text-right text-sm font-bold whitespace-nowrap text-gray-900 tabular-nums">
                                            {item.net_weight_grams.toFixed(3)} g
                                        </td>
                                        <td className="px-6 py-5 text-right text-sm whitespace-nowrap text-gray-600 tabular-nums">
                                            {formatPKR(item.labour_cost)}
                                        </td>
                                        <td className="px-6 py-5 text-right text-sm whitespace-nowrap text-gray-600 tabular-nums">
                                            {formatPKR(item.polish_cost)}
                                        </td>
                                        <td className="px-6 py-5 text-right text-sm font-bold whitespace-nowrap text-gray-900 tabular-nums">
                                            {formatPKR(lineTotal)}
                                        </td>
                                        <td className="px-6 py-5 text-right text-sm font-medium whitespace-nowrap">
                                            <button
                                                onClick={() => dispatch({ type: 'REMOVE_ITEM', payload: { id: item.id } })}
                                                className="text-gray-400 transition hover:text-red-600"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </td>
                                    </tr>
                                );
                            })}

                            {state.items.length === 0 && (
                                <tr>
                                    <td colSpan={7} className="px-6 py-20 text-center text-gray-400">
                                        <div className="flex flex-col items-center justify-center">
                                            <QrCode className="mb-3 h-10 w-10 opacity-20" />
                                            <p>Cart is empty. Scan an item to begin.</p>
                                        </div>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Exchanges (Old Gold) Section — hidden entirely when the module is off (Admin → Settings) */}
            {exchangeEnabled && (
                <div className="mt-4 flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div className="flex items-center justify-between border-b border-gray-200 bg-gray-50/50 px-6 py-3">
                        <h3 className="text-xs font-bold tracking-wider text-gray-700 uppercase">Old Gold / Silver Exchange</h3>
                    </div>
                    <div className="p-4">
                        <ExchangeForm metals={metals} purities={purities} />

                        {state.exchanges.length > 0 && (
                            <div className="mt-4">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th scope="col" className="px-4 py-2 text-left text-[10px] font-bold text-gray-500 uppercase">
                                                Item
                                            </th>
                                            <th scope="col" className="px-4 py-2 text-right text-[10px] font-bold text-gray-500 uppercase">
                                                Gross Wt.
                                            </th>
                                            <th scope="col" className="px-4 py-2 text-right text-[10px] font-bold text-gray-500 uppercase">
                                                Deduction
                                            </th>
                                            <th scope="col" className="px-4 py-2 text-right text-[10px] font-bold text-gray-500 uppercase">
                                                Net Wt.
                                            </th>
                                            <th scope="col" className="px-4 py-2 text-right text-[10px] font-bold text-gray-500 uppercase">
                                                Valuation
                                            </th>
                                            <th scope="col" className="relative w-12 px-4 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {state.exchanges.map((exc) => (
                                            <tr key={exc.id}>
                                                <td className="px-4 py-2 text-sm font-medium text-gray-900">
                                                    {exc.metal_name} {exc.purity_name}
                                                </td>
                                                <td className="px-4 py-2 text-right text-sm tabular-nums">{exc.weight_grams} g</td>
                                                <td className="px-4 py-2 text-right text-sm text-red-500 tabular-nums">{exc.deduction_percent}%</td>
                                                <td className="px-4 py-2 text-right text-sm font-bold tabular-nums">
                                                    {exc.net_weight_grams.toFixed(3)} g
                                                </td>
                                                <td className="px-4 py-2 text-right text-sm font-bold text-[#b38a36] tabular-nums">
                                                    -{formatPKR(exc.valuation)}
                                                </td>
                                                <td className="px-4 py-2 text-right">
                                                    <button
                                                        onClick={() => dispatch({ type: 'REMOVE_EXCHANGE', payload: { id: exc.id } })}
                                                        className="text-gray-400 transition hover:text-red-600"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* Bottom Actions */}
            <div className="flex items-center justify-between text-sm font-medium text-gray-500">
                <div className="flex items-center gap-4">
                    <button className="flex items-center gap-1 text-[#b38a36] hover:underline">
                        <Plus className="h-4 w-4" /> Add custom item
                    </button>
                    <span className="text-gray-300">|</span>
                    <button className="transition hover:text-gray-900">View Silver & Platinum rates</button>
                </div>
                <div>Showing {state.items.length} items in cart</div>
            </div>
        </div>
    );
}

/**
 * The rate detected for this cart line, editable inline for a negotiated
 * price. Starts equal to the live rate; any edit away from it is flagged
 * ("edited") and audited server-side when the sale goes through. "Reset"
 * puts it back to the system-detected rate.
 */
function EditableRate({ detectedRate, rate, onChange }: { detectedRate: number; rate: number; onChange: (rate: number) => void }) {
    const [editing, setEditing] = useState(false);
    const [draft, setDraft] = useState(String(rate));
    const isOverridden = rate !== detectedRate;

    const commit = () => {
        const parsed = parseFloat(draft);
        onChange(Number.isFinite(parsed) && parsed > 0 ? parsed : detectedRate);
        setEditing(false);
    };

    if (editing) {
        return (
            <span className="inline-flex items-center gap-1">
                <input
                    autoFocus
                    type="number"
                    step="0.01"
                    value={draft}
                    onChange={(e) => setDraft(e.target.value)}
                    onBlur={commit}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') commit();
                        if (e.key === 'Escape') setEditing(false);
                    }}
                    className="w-20 rounded border border-[#b38a36] px-1 py-0.5 text-[10px] tabular-nums"
                />
                <span className="text-[10px] text-gray-400">/g</span>
            </span>
        );
    }

    return (
        <span className="inline-flex items-center gap-1">
            <button
                type="button"
                onClick={() => {
                    setDraft(String(rate));
                    setEditing(true);
                }}
                className={cn(
                    'text-[10px] tabular-nums underline decoration-dotted underline-offset-2',
                    isOverridden ? 'font-semibold text-[#b38a36]' : 'text-gray-400 hover:text-gray-600',
                )}
                title="Click to edit this item's rate"
            >
                Rate: {formatPKR(rate)}/g
            </button>
            {isOverridden && (
                <>
                    <span className="rounded border border-amber-200 bg-amber-50 px-1 py-0.5 text-[9px] font-medium text-amber-700">edited</span>
                    <button type="button" onClick={() => onChange(detectedRate)} className="text-[9px] text-gray-400 underline hover:text-gray-600">
                        reset
                    </button>
                </>
            )}
        </span>
    );
}

function ExchangeForm({ metals, purities }: { metals: any[]; purities: any[] }) {
    const { dispatch } = usePOS();
    const { currentRates } = usePage().props as any;
    const [metalId, setMetalId] = useState('');
    const [purityId, setPurityId] = useState('');
    const [weight, setWeight] = useState('');
    const [deduction, setDeduction] = useState('0');

    const handleAdd = () => {
        if (!metalId || !purityId || !weight) return;
        const metal = metals.find((m) => m.id === parseInt(metalId));
        const purity = purities.find((p) => p.id === parseInt(purityId));
        if (!metal || !purity) return;

        // Detect this exchange's own live rate from the purity actually
        // selected — not a shared rate typed for the whole cart.
        const detectedRate = currentRates?.[purity.id];
        if (!detectedRate) {
            alert(`No current rate is set for ${purity.name}'s purity. Set one in Rate Management first.`);
            return;
        }

        const w = parseFloat(weight);
        const d = parseFloat(deduction) || 0;
        const netW = w * (1 - d / 100);
        // Re-weighed weight, less the melting-loss deduction, at this
        // purity's own live rate. Same basis as the sale line and the
        // Buy-Back service (no extra fineness multiplier — the purity's rate
        // already accounts for fineness).
        const valuation = netW * detectedRate.rate_per_gram;

        dispatch({
            type: 'ADD_EXCHANGE',
            payload: {
                id: genId(),
                metal_type_id: metal.id,
                purity_id: purity.id,
                metal_name: metal.name,
                purity_name: purity.name,
                purity_fraction: purity.fineness_percent / 100,
                weight_grams: w,
                deduction_percent: d,
                net_weight_grams: netW,
                rate_per_gram: detectedRate.rate_per_gram,
                valuation: Math.round(valuation),
            },
        });

        setMetalId('');
        setPurityId('');
        setWeight('');
        setDeduction('0');
    };

    return (
        <div className="flex items-end gap-3">
            <div className="flex-1">
                <label className="mb-1 block text-[10px] font-medium text-gray-500">Metal</label>
                <select
                    className="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-xs"
                    value={metalId}
                    onChange={(e) => setMetalId(e.target.value)}
                >
                    <option value="">Select Metal</option>
                    {metals?.map((m) => (
                        <option key={m.id} value={m.id}>
                            {m.name}
                        </option>
                    ))}
                </select>
            </div>
            <div className="flex-1">
                <label className="mb-1 block text-[10px] font-medium text-gray-500">Purity</label>
                <select
                    className="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-xs"
                    value={purityId}
                    onChange={(e) => setPurityId(e.target.value)}
                >
                    <option value="">Select Purity</option>
                    {purities
                        ?.filter((p) => !metalId || p.metal_type_id === parseInt(metalId))
                        .map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.name}
                            </option>
                        ))}
                </select>
            </div>
            <div className="w-24">
                <label className="mb-1 block text-[10px] font-medium text-gray-500">Wt. (g)</label>
                <input
                    type="number"
                    step="0.001"
                    className="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-xs"
                    placeholder="0.000"
                    value={weight}
                    onChange={(e) => setWeight(e.target.value)}
                />
            </div>
            <div className="w-20">
                <label className="mb-1 block text-[10px] font-medium text-gray-500">Ded. %</label>
                <input
                    type="number"
                    className="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-xs"
                    placeholder="0"
                    value={deduction}
                    onChange={(e) => setDeduction(e.target.value)}
                />
            </div>
            <button onClick={handleAdd} className="rounded-lg bg-gray-900 px-4 py-1.5 text-xs font-bold text-white transition hover:bg-gray-800">
                Add
            </button>
        </div>
    );
}

function POSSidebar() {
    const { state, dispatch } = usePOS();
    const { features, tax, shop, printerType } = usePage().props as any;
    const bankQrEnabled = features?.bank_qr_enabled ?? true;
    const exchangeEnabled = features?.exchange_enabled ?? true;
    const [discountInput, setDiscountInput] = useState(state.discount.toString());
    const [bankQrPayload, setBankQrPayload] = useState<Record<string, any> | null>(null);

    // Tax config lives in Admin → Settings — load it into the cart preview once.
    useEffect(() => {
        dispatch({ type: 'SET_TAX_CONFIG', payload: { enabled: !!tax?.enabled, ratePercent: Number(tax?.rate_percent) || 0 } });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const discountAmount = state.discountType === 'percentage' ? (state.subtotal * state.discount) / 100 : Math.min(state.subtotal, state.discount);

    const handleDiscountChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setDiscountInput(e.target.value);
        const val = parseFloat(e.target.value) || 0;
        dispatch({ type: 'SET_DISCOUNT', payload: { value: val, type: state.discountType } });
    };

    const toggleDiscountType = (type: 'flat' | 'percentage') => {
        dispatch({ type: 'SET_DISCOUNT', payload: { value: state.discount, type } });
    };

    return (
        <div className="pos-sidebar-container">
            {/* Rates are detected per item from its own purity (see the ticker
                above and the "Rate:" tag on each cart line) — no manual entry. */}
            <div className="pos-gold-rate-box">
                <div className="pos-gold-rate-header">
                    <h3 className="pos-gold-rate-title">
                        <span className="pos-gold-rate-icon">₹</span>
                        Pricing
                    </h3>
                    <div className="pos-gold-rate-status">
                        <div className="pos-gold-rate-status-dot" />
                        AUTO
                    </div>
                </div>
                <p className="px-1 pb-1 text-xs text-gray-500">
                    Each item is priced at its own purity's live rate automatically — see the rate shown on every cart line.
                </p>
            </div>

            {/* Customer Details */}
            <div>
                <h3 className="mb-2 text-[10px] font-bold tracking-wider text-gray-500 uppercase">Customer Details</h3>
                <div className="space-y-2">
                    <div>
                        <label className="mb-1 block text-[10px] font-medium text-gray-500">Customer Name</label>
                        <input
                            type="text"
                            className="block w-full rounded-lg border border-gray-200 px-3 py-1 text-xs font-medium text-gray-900 focus:border-[#b38a36] focus:ring-[#b38a36]"
                            placeholder="Enter name"
                            value={state.customer_name}
                            onChange={(e) => dispatch({ type: 'SET_CUSTOMER', payload: { name: e.target.value, phone: state.customer_phone } })}
                        />
                    </div>
                    <div>
                        <label className="mb-1 block text-[10px] font-medium text-gray-500">Phone Number</label>
                        <input
                            type="text"
                            className="block w-full rounded-lg border border-gray-200 px-3 py-1 text-xs font-medium text-gray-900 focus:border-[#b38a36] focus:ring-[#b38a36]"
                            placeholder="+92 3XX XXXXXXX"
                            value={state.customer_phone}
                            onChange={(e) => dispatch({ type: 'SET_CUSTOMER', payload: { name: state.customer_name, phone: e.target.value } })}
                        />
                    </div>
                </div>
            </div>

            <hr className="border-gray-100" />

            {/* Bill Summary */}
            <div>
                <h3 className="mb-2 text-[10px] font-bold tracking-wider text-gray-500 uppercase">Bill Summary</h3>
                <div className="pos-bill-summary">
                    {/* Subtotal */}
                    <div className="pos-bill-row">
                        <span className="pos-bill-row-label">Subtotal</span>
                        <span className="pos-bill-row-value">{formatPKR(state.subtotal)}</span>
                    </div>

                    {/* Exchanges — row hidden entirely when the module is off */}
                    {exchangeEnabled && (
                        <div className="pos-bill-row">
                            <span className="pos-bill-row-label">Exchanges</span>
                            <span className="pos-bill-row-value pos-bill-row-value--gold">
                                -{formatPKR(state.exchanges.reduce((sum, e) => sum + e.valuation, 0))}
                            </span>
                        </div>
                    )}

                    <hr className="pos-bill-divider" />

                    {/* Discount */}
                    <div className="pos-discount-row">
                        <div className="pos-discount-left">
                            <span className="pos-bill-row-label">Discount</span>
                            <div className="pos-discount-toggle">
                                <button
                                    onClick={() => toggleDiscountType('percentage')}
                                    className={state.discountType === 'percentage' ? 'active' : ''}
                                >
                                    %
                                </button>
                                <button onClick={() => toggleDiscountType('flat')} className={state.discountType === 'flat' ? 'active' : ''}>
                                    Rs
                                </button>
                            </div>
                        </div>
                        <div className="pos-discount-right">
                            <input type="number" className="pos-discount-input" value={discountInput} onChange={handleDiscountChange} />
                            <span className="pos-discount-amount">-{formatPKR(discountAmount)}</span>
                        </div>
                    </div>

                    {state.discount > 0 && (
                        <input
                            type="text"
                            className="block w-full rounded-lg border border-gray-200 px-3 py-1 text-xs font-medium text-gray-900 focus:border-[#b38a36] focus:ring-[#b38a36]"
                            placeholder="Reason for discount (optional)"
                            value={state.discount_reason}
                            onChange={(e) => dispatch({ type: 'SET_DISCOUNT_REASON', payload: e.target.value })}
                        />
                    )}

                    {state.taxEnabled && (
                        <div className="pos-bill-row">
                            <span className="pos-bill-row-label">Tax ({state.taxRatePercent}%)</span>
                            <span className="pos-bill-row-value">+{formatPKR(state.taxAmount)}</span>
                        </div>
                    )}
                </div>
            </div>

            {/* Grand Total */}
            <div className="pos-grand-total">
                <span className="pos-grand-total-label">Grand Total</span>
                <span className="pos-grand-total-value">{formatPKR(state.total)}</span>
            </div>

            {/* Payment Method */}
            <div>
                <h3 className="mb-2 text-[10px] font-bold tracking-wider text-gray-500 uppercase">Payment Method</h3>
                <div className="pos-payment-tabs">
                    {(['Cash', 'Card', 'Transfer', 'Bank QR'] as const)
                        .filter((m) => m !== 'Bank QR' || bankQrEnabled)
                        .map((method) => (
                            <button
                                key={method}
                                onClick={() => dispatch({ type: 'SET_PAYMENT_METHOD', payload: method as any })}
                                className={cn('pos-payment-tab', state.paymentMethod === method ? 'active' : '')}
                            >
                                {method}
                            </button>
                        ))}
                </div>
            </div>

            {/* Action Buttons */}
            <div className="mt-2 flex gap-3">
                <button
                    onClick={async () => {
                        if (state.items.length === 0) {
                            alert('Cart is empty.');
                            return;
                        }

                        const payload = {
                            customer_name: state.customer_name,
                            customer_phone: state.customer_phone,
                            discount: state.discount,
                            discountType: state.discountType,
                            discount_reason: state.discount_reason,
                            // Only send an override when the shopkeeper actually edited a
                            // line's rate away from the detected one — otherwise the
                            // server just prices from Rate Management as normal.
                            items: state.items.map((item) => ({
                                id: item.id,
                                ...(item.rate_per_gram !== item.detected_rate_per_gram ? { rate_override: item.rate_per_gram } : {}),
                            })),
                            exchanges: state.exchanges,
                        };

                        if (state.paymentMethod === 'Bank QR') {
                            setBankQrPayload(payload);
                            return;
                        }

                        try {
                            const response = await axios.post('/api/v1/pos/transaction', {
                                ...payload,
                                paymentMethod: state.paymentMethod,
                            });

                            if (response.data.success) {
                                toast.success('Sale completed', {
                                    description: `Invoice ${response.data.invoice.invoice_number} created.`,
                                });
                                dispatch({ type: 'CLEAR_CART' });
                            }
                        } catch (error: any) {
                            console.error(error);
                            if (error.response?.status === 422) {
                                const errors = error.response.data.errors;
                                const errorMessages = Object.values(errors).flat().join('\n');
                                alert('Validation Error:\n' + errorMessages);
                            } else {
                                alert(error.response?.data?.message || 'Failed to complete sale.');
                            }
                        }
                    }}
                    className="pos-complete-btn min-w-0 flex-[3]"
                >
                    Complete Sale
                </button>
                <button
                    type="button"
                    disabled={state.items.length === 0}
                    onClick={() => window.print()}
                    className="min-w-0 flex-1 rounded-lg border border-gray-200 bg-white py-1 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Print
                </button>
            </div>

            {/* Off-screen until @media print — see pos-print.css. Always a
                quotation, never a receipt: Print works straight from the
                live cart, whether or not a sale has actually been completed. */}
            <PosPrintSlip shop={shop} printerType={printerType} />

            {bankQrPayload && (
                <BankQrPaymentModal
                    startPayload={bankQrPayload}
                    onClose={() => setBankQrPayload(null)}
                    onCompleted={(invoiceNumber) => {
                        toast.success('Sale completed', {
                            description: `Invoice ${invoiceNumber} created.`,
                        });
                        dispatch({ type: 'CLEAR_CART' });
                        setBankQrPayload(null);
                    }}
                />
            )}
        </div>
    );
}

export default function POSIndex() {
    return (
        <AppLayout breadcrumbs={[{ title: 'Point of Sale (POS)', href: '/pos' }]}>
            <div className="font-sans selection:bg-[#c39b66]/30">
                <Head title="POS Billing | Zar & Noor" />

                <POSProvider>
                    <div className="mx-auto w-full p-2 sm:p-4 lg:p-6">
                        <RateTicker className="mb-4" />
                        <div className="pos-layout">
                            <div className="pos-main">
                                <POSCart />
                            </div>
                            <div className="pos-sidebar">
                                <POSSidebar />
                            </div>
                        </div>
                    </div>
                </POSProvider>
            </div>
        </AppLayout>
    );
}
