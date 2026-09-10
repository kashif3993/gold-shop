import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { POSProvider, usePOS } from '@/context/POSContext';
import RateTicker from '@/components/rate-ticker';
import { useState, useEffect } from 'react';
import { Search, Trash2, Edit2, CheckCircle2, QrCode, Plus } from 'lucide-react';
import { cn } from '@/lib/utils';
import axios from 'axios';
import '../../../css/pos.css';

// --- Utility to format currency as PKR ---
const formatPKR = (amount: number) => {
    return new Intl.NumberFormat('en-PK', {
        style: 'currency',
        currency: 'PKR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount).replace('PKR', 'Rs.');
};

// Removed POSHeader to prevent double-header with AppLayout

function POSCart() {
    const { state, dispatch } = usePOS();
    const { metals, purities } = usePage().props as any;
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
                console.error("Search error", error);
            } finally {
                setIsSearching(false);
            }
        }, 300);

        return () => clearTimeout(delayDebounceFn);
    }, [searchQuery]);

    const handleAddItem = (item: any) => {
        if (state.items.some(cartItem => cartItem.id === item.id)) {
            alert("Item is already in the cart.");
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
            }
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
                const exactMatch = searchResults.find(r => r.item_code === searchQuery || r.qr_payload === searchQuery);
                if (exactMatch) {
                    handleAddItem(exactMatch);
                }
            }
        }
    };

    return (
        <div className="pos-cart-container">
            {/* Search Bar */}
            <div className="flex gap-4 relative">
                <div className="relative flex-grow">
                    <div className="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <QrCode className="h-5 w-5 text-gray-400" />
                    </div>
                    <input
                        type="text"
                        className="block w-full pl-11 pr-12 py-2 border border-gray-200 rounded-lg focus:ring-[#b38a36] focus:border-[#b38a36] shadow-sm text-gray-900 placeholder-gray-400"
                        placeholder="Scan barcode or search item code (e.g. ITM-1042)..."
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                        onKeyDown={handleKeyDown}
                        autoFocus
                    />
                    <div className="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none">
                        {isSearching ? (
                            <div className="animate-spin h-4 w-4 border-2 border-gray-400 border-t-transparent rounded-full" />
                        ) : (
                            <Search className="h-5 w-5 text-gray-400" />
                        )}
                    </div>
                    
                    {/* Search Results Dropdown */}
                    {searchResults.length > 0 && (
                        <div className="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                            {searchResults.map((item) => (
                                <button
                                    key={item.id}
                                    onClick={() => handleAddItem(item)}
                                    className="w-full text-left px-4 py-3 hover:bg-gray-50 border-b border-gray-100 last:border-0 flex justify-between items-center transition"
                                >
                                    <div>
                                        <div className="font-bold text-gray-900">{item.item_code}</div>
                                        <div className="text-xs text-gray-500">{item.item_type} - {item.metal_name} {item.purity_name}</div>
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
            <div className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden flex flex-col min-h-[400px]">
                <div className="overflow-x-auto">
                    <table className="min-w-[650px] w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50/50">
                            <tr>
                                <th scope="col" className="px-6 py-4 text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider w-12">#</th>
                                <th scope="col" className="px-6 py-4 text-left text-[11px] font-bold text-gray-500 uppercase tracking-wider">Item Details</th>
                                <th scope="col" className="px-6 py-4 text-right text-[11px] font-bold text-gray-500 uppercase tracking-wider">Net Weight</th>
                                <th scope="col" className="px-6 py-4 text-right text-[11px] font-bold text-gray-500 uppercase tracking-wider">Labour</th>
                                <th scope="col" className="px-6 py-4 text-right text-[11px] font-bold text-gray-500 uppercase tracking-wider">Polish</th>
                                <th scope="col" className="px-6 py-4 text-right text-[11px] font-bold text-gray-500 uppercase tracking-wider">
                                    Calculated Price
                                    <div className="text-[9px] text-[#c39b66] normal-case tracking-normal mt-0.5">Based on today's rate</div>
                                </th>
                                <th scope="col" className="px-6 py-4 relative w-12">
                                    <span className="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-100">
                            {state.items.map((item, index) => {
                                const itemGoldValue = item.net_weight_grams * state.goldRate;
                                const lineTotal = itemGoldValue + item.labour_cost + item.polish_cost;

                                return (
                                    <tr key={item.id} className="hover:bg-gray-50/50 transition">
                                        <td className="px-6 py-5 whitespace-nowrap text-sm text-gray-400 font-medium">
                                            {index + 1}
                                        </td>
                                        <td className="px-6 py-5">
                                            <div className="text-sm font-bold text-gray-900 mb-1.5">{item.purity_name} {item.metal_name} {item.item_type}</div>
                                            <div className="flex gap-2">
                                                <span className="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-800">
                                                    {item.item_code}
                                                </span>
                                                <span className="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-amber-50 text-amber-800 border border-amber-100">
                                                    {item.purity_name} Hallmark
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-5 whitespace-nowrap text-sm text-gray-900 font-bold text-right tabular-nums">
                                            {item.net_weight_grams.toFixed(3)} g
                                        </td>
                                        <td className="px-6 py-5 whitespace-nowrap text-sm text-gray-600 text-right tabular-nums">
                                            {formatPKR(item.labour_cost)}
                                        </td>
                                        <td className="px-6 py-5 whitespace-nowrap text-sm text-gray-600 text-right tabular-nums">
                                            {formatPKR(item.polish_cost)}
                                        </td>
                                        <td className="px-6 py-5 whitespace-nowrap text-sm text-gray-900 font-bold text-right tabular-nums">
                                            {formatPKR(lineTotal)}
                                        </td>
                                        <td className="px-6 py-5 whitespace-nowrap text-right text-sm font-medium">
                                            <button
                                                onClick={() => dispatch({ type: 'REMOVE_ITEM', payload: { id: item.id } })}
                                                className="text-gray-400 hover:text-red-600 transition"
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
                                            <QrCode className="h-10 w-10 mb-3 opacity-20" />
                                            <p>Cart is empty. Scan an item to begin.</p>
                                        </div>
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Exchanges (Old Gold) Section */}
            <div className="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden flex flex-col mt-4">
                <div className="bg-gray-50/50 px-6 py-3 border-b border-gray-200 flex justify-between items-center">
                    <h3 className="text-xs font-bold text-gray-700 uppercase tracking-wider">Old Gold / Silver Exchange</h3>
                </div>
                <div className="p-4">
                    <ExchangeForm metals={metals} purities={purities} />
                    
                    {state.exchanges.length > 0 && (
                        <div className="mt-4">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th scope="col" className="px-4 py-2 text-left text-[10px] font-bold text-gray-500 uppercase">Item</th>
                                        <th scope="col" className="px-4 py-2 text-right text-[10px] font-bold text-gray-500 uppercase">Gross Wt.</th>
                                        <th scope="col" className="px-4 py-2 text-right text-[10px] font-bold text-gray-500 uppercase">Deduction</th>
                                        <th scope="col" className="px-4 py-2 text-right text-[10px] font-bold text-gray-500 uppercase">Net Wt.</th>
                                        <th scope="col" className="px-4 py-2 text-right text-[10px] font-bold text-gray-500 uppercase">Valuation</th>
                                        <th scope="col" className="px-4 py-2 relative w-12"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {state.exchanges.map((exc) => (
                                        <tr key={exc.id}>
                                            <td className="px-4 py-2 text-sm text-gray-900 font-medium">
                                                {exc.metal_name} {exc.purity_name}
                                            </td>
                                            <td className="px-4 py-2 text-sm text-right tabular-nums">{exc.weight_grams} g</td>
                                            <td className="px-4 py-2 text-sm text-right text-red-500 tabular-nums">{exc.deduction_percent}%</td>
                                            <td className="px-4 py-2 text-sm text-right tabular-nums font-bold">{exc.net_weight_grams.toFixed(3)} g</td>
                                            <td className="px-4 py-2 text-sm text-right text-[#b38a36] font-bold tabular-nums">
                                                -{formatPKR(exc.valuation)}
                                            </td>
                                            <td className="px-4 py-2 text-right">
                                                <button onClick={() => dispatch({ type: 'REMOVE_EXCHANGE', payload: { id: exc.id } })} className="text-gray-400 hover:text-red-600 transition">
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

            {/* Bottom Actions */}
            <div className="flex justify-between items-center text-sm text-gray-500 font-medium">
                <div className="flex items-center gap-4">
                    <button className="text-[#b38a36] hover:underline flex items-center gap-1">
                        <Plus className="h-4 w-4" /> Add custom item
                    </button>
                    <span className="text-gray-300">|</span>
                    <button className="hover:text-gray-900 transition">View Silver & Platinum rates</button>
                </div>
                <div>
                    Showing {state.items.length} items in cart
                </div>
            </div>
        </div>
    );
}

function ExchangeForm({ metals, purities }: { metals: any[], purities: any[] }) {
    const { state, dispatch } = usePOS();
    const [metalId, setMetalId] = useState('');
    const [purityId, setPurityId] = useState('');
    const [weight, setWeight] = useState('');
    const [deduction, setDeduction] = useState('0');

    const handleAdd = () => {
        if (!metalId || !purityId || !weight) return;
        const metal = metals.find((m) => m.id === parseInt(metalId));
        const purity = purities.find((p) => p.id === parseInt(purityId));
        if (!metal || !purity) return;

        const w = parseFloat(weight);
        const d = parseFloat(deduction) || 0;
        const netW = w * (1 - d / 100);
        // Re-weighed weight, less the melting-loss deduction, at today's rate.
        // Same basis as the sale line and the Buy-Back service — the rate box
        // already holds the rate for the gold being transacted (no extra
        // fineness multiplier).
        const valuation = netW * state.goldRate;

        dispatch({
            type: 'ADD_EXCHANGE',
            payload: {
                id: crypto.randomUUID(),
                metal_type_id: metal.id,
                purity_id: purity.id,
                metal_name: metal.name,
                purity_name: purity.name,
                purity_fraction: purity.fineness_percent / 100,
                weight_grams: w,
                deduction_percent: d,
                net_weight_grams: netW,
                valuation: Math.round(valuation)
            }
        });

        setMetalId('');
        setPurityId('');
        setWeight('');
        setDeduction('0');
    };

    return (
        <div className="flex gap-3 items-end">
            <div className="flex-1">
                <label className="block text-[10px] font-medium text-gray-500 mb-1">Metal</label>
                <select className="w-full py-1.5 px-3 border border-gray-200 rounded-lg text-xs" value={metalId} onChange={e => setMetalId(e.target.value)}>
                    <option value="">Select Metal</option>
                    {metals?.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
                </select>
            </div>
            <div className="flex-1">
                <label className="block text-[10px] font-medium text-gray-500 mb-1">Purity</label>
                <select className="w-full py-1.5 px-3 border border-gray-200 rounded-lg text-xs" value={purityId} onChange={e => setPurityId(e.target.value)}>
                    <option value="">Select Purity</option>
                    {purities?.filter(p => !metalId || p.metal_type_id === parseInt(metalId)).map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                </select>
            </div>
            <div className="w-24">
                <label className="block text-[10px] font-medium text-gray-500 mb-1">Wt. (g)</label>
                <input type="number" step="0.001" className="w-full py-1.5 px-3 border border-gray-200 rounded-lg text-xs" placeholder="0.000" value={weight} onChange={e => setWeight(e.target.value)} />
            </div>
            <div className="w-20">
                <label className="block text-[10px] font-medium text-gray-500 mb-1">Ded. %</label>
                <input type="number" className="w-full py-1.5 px-3 border border-gray-200 rounded-lg text-xs" placeholder="0" value={deduction} onChange={e => setDeduction(e.target.value)} />
            </div>
            <button onClick={handleAdd} className="bg-gray-900 text-white px-4 py-1.5 rounded-lg text-xs font-bold hover:bg-gray-800 transition">
                Add
            </button>
        </div>
    );
}

function POSSidebar() {
    const { state, dispatch } = usePOS();
    const [discountInput, setDiscountInput] = useState(state.discount.toString());

    const handleRateChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const val = parseFloat(e.target.value.replace(/[^0-9.]/g, '')) || 0;
        dispatch({ type: 'SET_GOLD_RATE', payload: val });
    };

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
            {/* Gold Rate Box */}
            <div className="pos-gold-rate-box">
                <div className="pos-gold-rate-header">
                    <h3 className="pos-gold-rate-title">
                        <span className="pos-gold-rate-icon">₹</span>
                        Today's Gold Rate
                    </h3>
                    <div className="pos-gold-rate-status">
                        <div className="pos-gold-rate-status-dot" />
                        STABLE
                    </div>
                </div>
                <div className="pos-input-wrapper">
                    <div className="pos-input-prefix">
                        <span>Rs.</span>
                    </div>
                    <input
                        type="text"
                        className="pos-gold-rate-input"
                        value={state.goldRate.toLocaleString()}
                        onChange={handleRateChange}
                    />
                    <div className="pos-input-suffix">
                        <span>/ gram</span>
                    </div>
                </div>
            </div>

            {/* Customer Details */}
            <div>
                <h3 className="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">Customer Details</h3>
                <div className="space-y-2">
                    <div>
                        <label className="block text-[10px] font-medium text-gray-500 mb-1">Customer Name</label>
                        <input
                            type="text"
                            className="block w-full py-1 px-3 border border-gray-200 rounded-lg focus:ring-[#b38a36] focus:border-[#b38a36] text-xs text-gray-900 font-medium"
                            placeholder="Enter name"
                            value={state.customer_name}
                            onChange={(e) => dispatch({ type: 'SET_CUSTOMER', payload: { name: e.target.value, phone: state.customer_phone } })}
                        />
                    </div>
                    <div>
                        <label className="block text-[10px] font-medium text-gray-500 mb-1">Phone Number</label>
                        <input
                            type="text"
                            className="block w-full py-1 px-3 border border-gray-200 rounded-lg focus:ring-[#b38a36] focus:border-[#b38a36] text-xs text-gray-900 font-medium"
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
                <h3 className="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">Bill Summary</h3>
                <div className="pos-bill-summary">
                    {/* Subtotal */}
                    <div className="pos-bill-row">
                        <span className="pos-bill-row-label">Subtotal</span>
                        <span className="pos-bill-row-value">{formatPKR(state.subtotal)}</span>
                    </div>

                    {/* Exchanges */}
                    <div className="pos-bill-row">
                        <span className="pos-bill-row-label">Exchanges</span>
                        <span className="pos-bill-row-value pos-bill-row-value--gold">
                            -{formatPKR(state.exchanges.reduce((sum, e) => sum + e.valuation, 0))}
                        </span>
                    </div>

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
                                <button
                                    onClick={() => toggleDiscountType('flat')}
                                    className={state.discountType === 'flat' ? 'active' : ''}
                                >
                                    Rs
                                </button>
                            </div>
                        </div>
                        <div className="pos-discount-right">
                            <input
                                type="number"
                                className="pos-discount-input"
                                value={discountInput}
                                onChange={handleDiscountChange}
                            />
                            <span className="pos-discount-amount">
                                -{formatPKR(state.subtotal - state.total)}
                            </span>
                        </div>
                    </div>

                    {state.discount > 0 && (
                        <input
                            type="text"
                            className="block w-full py-1 px-3 border border-gray-200 rounded-lg focus:ring-[#b38a36] focus:border-[#b38a36] text-xs text-gray-900 font-medium"
                            placeholder="Reason for discount (optional)"
                            value={state.discount_reason}
                            onChange={(e) => dispatch({ type: 'SET_DISCOUNT_REASON', payload: e.target.value })}
                        />
                    )}
                </div>
            </div>

            {/* Grand Total */}
            <div className="pos-grand-total">
                <span className="pos-grand-total-label">Grand Total</span>
                <span className="pos-grand-total-value">
                    {formatPKR(state.total)}
                </span>
            </div>

            {/* Payment Method */}
            <div>
                <h3 className="text-[10px] font-bold text-gray-500 uppercase tracking-wider mb-2">Payment Method</h3>
                <div className="pos-payment-tabs">
                    {['Cash', 'Card', 'Transfer'].map((method) => (
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
                            alert("Cart is empty.");
                            return;
                        }

                        try {
                            const response = await axios.post('/api/v1/pos/transaction', {
                                customer_name: state.customer_name,
                                customer_phone: state.customer_phone,
                                goldRate: state.goldRate,
                                discount: state.discount,
                                discountType: state.discountType,
                                discount_reason: state.discount_reason,
                                paymentMethod: state.paymentMethod,
                                items: state.items,
                                exchanges: state.exchanges
                            });

                            if (response.data.success) {
                                alert(`Sale Completed! Invoice ${response.data.invoice.invoice_number} created.`);
                                dispatch({ type: 'CLEAR_CART' });
                            }
                        } catch (error: any) {
                            console.error(error);
                            if (error.response?.status === 422) {
                                const errors = error.response.data.errors;
                                const errorMessages = Object.values(errors).flat().join('\n');
                                alert("Validation Error:\n" + errorMessages);
                            } else {
                                alert(error.response?.data?.message || "Failed to complete sale.");
                            }
                        }
                    }}
                    className="pos-complete-btn flex-[3] min-w-0"
                >
                    Complete Sale
                </button>
                <button className="flex-1 min-w-0 py-1 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 rounded-lg text-sm font-bold transition shadow-sm">
                    Print
                </button>
            </div>
        </div>
    );
}

export default function POSIndex() {
    return (
        <AppLayout breadcrumbs={[{ title: 'Point of Sale (POS)', href: '/pos' }]}>
            <div className="font-sans selection:bg-[#c39b66]/30">
                <Head title="POS Billing | Zar & Noor" />

                <POSProvider>
                    <div className="p-2 sm:p-4 lg:p-6 w-full mx-auto">
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
