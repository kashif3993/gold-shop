import React, { createContext, useContext, useReducer, ReactNode } from 'react';
import { CartState, CartAction, CartItem, ExchangeItem } from '../types/pos';

const initialState: CartState = {
    items: [],
    exchanges: [],
    subtotal: 0,
    discount: 0,
    discountType: 'percentage',
    discount_reason: '',
    taxEnabled: false,
    taxRatePercent: 0,
    taxAmount: 0,
    total: 0,
    customer_name: '',
    customer_phone: '',
    paymentMethod: 'Cash',
};

// Helper to recalculate totals. Each item/exchange already carries its own
// detected rate (from its own purity) — there's no shared rate to multiply by.
// This is a preview only — the server (POSSaleService) recomputes everything
// authoritatively, including tax, from the same Admin → Settings values.
const calculateTotals = (
    items: CartItem[], exchanges: ExchangeItem[], discount: number, discountType: 'flat' | 'percentage',
    taxEnabled: boolean, taxRatePercent: number,
) => {
    // 1. Calculate each item's total dynamically
    let subtotal = 0;
    items.forEach(item => {
        const itemGoldValue = item.net_weight_grams * item.rate_per_gram;
        const lineTotal = itemGoldValue + item.labour_cost + item.polish_cost;
        subtotal += lineTotal;
    });

    let exchangeTotal = 0;
    exchanges.forEach(exc => {
        exchangeTotal += exc.valuation;
    });

    // 2. Apply discount
    let discountAmount = 0;
    if (discountType === 'flat') {
        discountAmount = Math.min(subtotal, discount);
    } else if (discountType === 'percentage') {
        discountAmount = (subtotal * discount) / 100;
    }
    const taxable = Math.max(0, subtotal - discountAmount);

    // 3. Tax, on the taxable amount — before the exchange deduction
    const taxAmount = taxEnabled && taxRatePercent > 0 ? (taxable * taxRatePercent) / 100 : 0;

    const total = Math.max(0, taxable + taxAmount - exchangeTotal);

    // Rounding to nearest integer for PKR
    return {
        subtotal: Math.round(subtotal),
        taxAmount: Math.round(taxAmount),
        total: Math.round(total),
    };
};

const posReducer = (state: CartState, action: CartAction): CartState => {
    switch (action.type) {
        case 'ADD_ITEM': {
            if (state.items.find(i => i.id === action.payload.id)) {
                return state;
            }
            const newItems = [...state.items, action.payload];
            const { subtotal, taxAmount, total } = calculateTotals(newItems, state.exchanges, state.discount, state.discountType, state.taxEnabled, state.taxRatePercent);
            return { ...state, items: newItems, subtotal, taxAmount, total };
        }
        case 'REMOVE_ITEM': {
            const newItems = state.items.filter(i => i.id !== action.payload.id);
            const { subtotal, taxAmount, total } = calculateTotals(newItems, state.exchanges, state.discount, state.discountType, state.taxEnabled, state.taxRatePercent);
            return { ...state, items: newItems, subtotal, taxAmount, total };
        }
        case 'UPDATE_ITEM_RATE': {
            const newItems = state.items.map(i =>
                i.id === action.payload.id ? { ...i, rate_per_gram: action.payload.rate_per_gram } : i
            );
            const { subtotal, taxAmount, total } = calculateTotals(newItems, state.exchanges, state.discount, state.discountType, state.taxEnabled, state.taxRatePercent);
            return { ...state, items: newItems, subtotal, taxAmount, total };
        }
        case 'SET_DISCOUNT': {
            const { value, type } = action.payload;
            const { subtotal, taxAmount, total } = calculateTotals(state.items, state.exchanges, value, type, state.taxEnabled, state.taxRatePercent);
            return { ...state, discount: value, discountType: type, subtotal, taxAmount, total };
        }
        case 'SET_DISCOUNT_REASON': {
            return { ...state, discount_reason: action.payload };
        }
        case 'SET_CUSTOMER': {
            return {
                ...state,
                customer_name: action.payload.name,
                customer_phone: action.payload.phone,
            };
        }
        case 'SET_PAYMENT_METHOD': {
            return { ...state, paymentMethod: action.payload };
        }
        case 'SET_TAX_CONFIG': {
            const { enabled, ratePercent } = action.payload;
            const { subtotal, taxAmount, total } = calculateTotals(state.items, state.exchanges, state.discount, state.discountType, enabled, ratePercent);
            return { ...state, taxEnabled: enabled, taxRatePercent: ratePercent, subtotal, taxAmount, total };
        }
        case 'CLEAR_CART': {
            // Tax config comes from the shop's settings, not the cart — keep it.
            return { ...initialState, taxEnabled: state.taxEnabled, taxRatePercent: state.taxRatePercent };
        }
        case 'ADD_EXCHANGE': {
            const newExchanges = [...state.exchanges, action.payload];
            const { subtotal, taxAmount, total } = calculateTotals(state.items, newExchanges, state.discount, state.discountType, state.taxEnabled, state.taxRatePercent);
            return { ...state, exchanges: newExchanges, subtotal, taxAmount, total };
        }
        case 'REMOVE_EXCHANGE': {
            const newExchanges = state.exchanges.filter(e => e.id !== action.payload.id);
            const { subtotal, taxAmount, total } = calculateTotals(state.items, newExchanges, state.discount, state.discountType, state.taxEnabled, state.taxRatePercent);
            return { ...state, exchanges: newExchanges, subtotal, taxAmount, total };
        }
        default:
            return state;
    }
};

interface POSContextProps {
    state: CartState;
    dispatch: React.Dispatch<CartAction>;
}

const POSContext = createContext<POSContextProps | undefined>(undefined);

export const POSProvider = ({ children }: { children: ReactNode }) => {
    const [state, dispatch] = useReducer(posReducer, initialState);

    return (
        <POSContext.Provider value={{ state, dispatch }}>
            {children}
        </POSContext.Provider>
    );
};

export const usePOS = () => {
    const context = useContext(POSContext);
    if (!context) {
        throw new Error('usePOS must be used within a POSProvider');
    }
    return context;
};
