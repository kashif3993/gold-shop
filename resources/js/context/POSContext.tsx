import React, { createContext, useContext, useReducer, ReactNode } from 'react';
import { CartState, CartAction, CartItem, ExchangeItem } from '../types/pos';

const initialState: CartState = {
    items: [],
    exchanges: [],
    subtotal: 0,
    discount: 0,
    discountType: 'percentage',
    discount_reason: '',
    total: 0,
    customer_name: '',
    customer_phone: '',
    goldRate: 25000, // Default fallback PKR rate
    paymentMethod: 'Cash',
};

// Helper to recalculate totals
const calculateTotals = (items: CartItem[], exchanges: ExchangeItem[], discount: number, discountType: 'flat' | 'percentage', goldRate: number) => {
    // 1. Calculate each item's total dynamically
    let subtotal = 0;
    items.forEach(item => {
        const itemGoldValue = item.net_weight_grams * goldRate;
        const lineTotal = itemGoldValue + item.labour_cost + item.polish_cost;
        subtotal += lineTotal;
    });

    let exchangeTotal = 0;
    exchanges.forEach(exc => {
        exchangeTotal += exc.valuation;
    });

    // 2. Apply discount
    let total = subtotal;
    if (discountType === 'flat') {
        total = Math.max(0, subtotal - discount);
    } else if (discountType === 'percentage') {
        const discountAmount = (subtotal * discount) / 100;
        total = Math.max(0, subtotal - discountAmount);
    }
    
    total = Math.max(0, total - exchangeTotal);

    // Rounding to nearest integer for PKR
    return { 
        subtotal: Math.round(subtotal), 
        total: Math.round(total) 
    };
};

const posReducer = (state: CartState, action: CartAction): CartState => {
    switch (action.type) {
        case 'ADD_ITEM': {
            if (state.items.find(i => i.id === action.payload.id)) {
                return state;
            }
            const newItems = [...state.items, action.payload];
            const { subtotal, total } = calculateTotals(newItems, state.exchanges, state.discount, state.discountType, state.goldRate);
            return { ...state, items: newItems, subtotal, total };
        }
        case 'REMOVE_ITEM': {
            const newItems = state.items.filter(i => i.id !== action.payload.id);
            const { subtotal, total } = calculateTotals(newItems, state.exchanges, state.discount, state.discountType, state.goldRate);
            return { ...state, items: newItems, subtotal, total };
        }
        case 'SET_DISCOUNT': {
            const { value, type } = action.payload;
            const { subtotal, total } = calculateTotals(state.items, state.exchanges, value, type, state.goldRate);
            return { ...state, discount: value, discountType: type, subtotal, total };
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
        case 'SET_GOLD_RATE': {
            const newRate = action.payload;
            const { subtotal, total } = calculateTotals(state.items, state.exchanges, state.discount, state.discountType, newRate);
            return { ...state, goldRate: newRate, subtotal, total };
        }
        case 'SET_PAYMENT_METHOD': {
            return { ...state, paymentMethod: action.payload };
        }
        case 'CLEAR_CART': {
            // Keep the gold rate when clearing cart
            return { ...initialState, goldRate: state.goldRate };
        }
        case 'ADD_EXCHANGE': {
            const newExchanges = [...state.exchanges, action.payload];
            const { subtotal, total } = calculateTotals(state.items, newExchanges, state.discount, state.discountType, state.goldRate);
            return { ...state, exchanges: newExchanges, subtotal, total };
        }
        case 'REMOVE_EXCHANGE': {
            const newExchanges = state.exchanges.filter(e => e.id !== action.payload.id);
            const { subtotal, total } = calculateTotals(state.items, newExchanges, state.discount, state.discountType, state.goldRate);
            return { ...state, exchanges: newExchanges, subtotal, total };
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
