export interface CartItem {
    id: number;
    item_code: string;
    item_type: string;
    metal_name: string;
    purity_name: string;
    gross_weight_grams: number;
    stone_weight_grams: number;
    cutting_loss_grams: number;
    net_weight_grams: number;
    labour_cost: number;
    polish_cost: number;
    // line_total is now computed on the fly based on the global gold rate
}

export interface ExchangeItem {
    id: string; // Temporary UUID for UI
    metal_type_id: number;
    purity_id: number;
    metal_name: string;
    purity_name: string;
    purity_fraction: number;
    weight_grams: number;
    deduction_percent: number;
    net_weight_grams: number;
    valuation: number;
}

export interface CartState {
    items: CartItem[];
    exchanges: ExchangeItem[];
    subtotal: number; // Before discount
    discount: number; 
    discountType: 'flat' | 'percentage';
    discount_reason?: string;
    total: number; // After discount and exchanges
    customer_name: string;
    customer_phone: string;
    goldRate: number; // Today's gold rate per gram
    paymentMethod: 'Cash' | 'Card' | 'Transfer';
}

export type CartAction =
    | { type: 'ADD_ITEM'; payload: CartItem }
    | { type: 'REMOVE_ITEM'; payload: { id: number } }
    | { type: 'ADD_EXCHANGE'; payload: ExchangeItem }
    | { type: 'REMOVE_EXCHANGE'; payload: { id: string } }
    | { type: 'SET_DISCOUNT'; payload: { value: number; type: 'flat' | 'percentage' } }
    | { type: 'SET_DISCOUNT_REASON'; payload: string }
    | { type: 'SET_CUSTOMER'; payload: { name: string; phone: string } }
    | { type: 'SET_GOLD_RATE'; payload: number }
    | { type: 'SET_PAYMENT_METHOD'; payload: 'Cash' | 'Card' | 'Transfer' }
    | { type: 'CLEAR_CART' };
