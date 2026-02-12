export interface Currency {
    id: number;
    code: string;
    name: string;
    symbol: string;
    is_active: boolean;
}

export interface ExchangeRate {
    id: number;
    from_currency_id: number;
    to_currency_id: number;
    from_currency?: Currency;
    to_currency?: Currency;
    rate: number;
    effective_date: string;
}

export interface IncomeSource {
    id: number;
    user_id: number;
    name: string;
    description: string | null;
    default_currency_id: number;
    default_currency?: Currency;
    is_active: boolean;
}

export interface Income {
    id: number;
    user_id: number;
    income_source_id: number;
    income_source?: IncomeSource;
    currency_id: number;
    currency?: Currency;
    amount: number;
    received_date: string;
    description: string | null;
    is_recurring: boolean;
    recurrence_day: number | null;
    recurrence_end_date: string | null;
    metadata: any;
}

export interface PurchaseCategory {
    id: number;
    user_id: number;
    name: string;
    icon: string | null;
    color: string | null;
    purchases_count?: number;
}

export interface Purchase {
    id: number;
    user_id: number;
    currency_id: number;
    currency?: Currency;
    category_id: number | null;
    category?: PurchaseCategory;
    amount: number;
    purchase_date: string;
    description: string;
    notes: string | null;
    receipt_path: string | null;
    debt?: Debt;
}

export interface CreditCard {
    id: number;
    user_id: number;
    name: string;
    last_four_digits: string | null;
    owner_id: number | null;
    owner?: any;
    is_mine: boolean;
    interest_rate: number;
    tax_percentage: number;
    apply_interest: boolean;
    apply_tax: boolean;
    notes: string | null;
    active_debts_count?: number;
    debts?: Debt[];
}

export interface Debt {
    id: number;
    user_id: number;
    purchase_id: number;
    purchase?: Purchase;
    credit_card_id: number;
    credit_card?: CreditCard;
    currency_id: number;
    currency?: Currency;
    original_amount: number;
    remaining_amount: number;
    interest_amount: number;
    tax_amount: number;
    total_amount: number;
    due_date: string | null;
    status: 'pending' | 'partial' | 'paid';
    notes: string | null;
    payments?: DebtPayment[];
}

export interface DebtPayment {
    id: number;
    debt_id: number;
    currency_id: number;
    currency?: Currency;
    amount: number;
    payment_date: string;
    notes: string | null;
}

export interface WithdrawalCategory {
    id: number;
    name: string;
    color: string;
    icon?: string;
}

export interface Withdrawal {
    id: number;
    user_id: number;
    currency_id: number;
    category_id?: number | null;
    amount: number;
    withdrawal_date: string;
    description: string;
    notes?: string | null;
    is_recurring: boolean;
    recurrence_frequency?: 'monthly' | 'weekly' | 'yearly' | null;
    recurrence_day?: number | null;
    recurrence_end_date?: string | null;
    receipt_path?: string | null;

    category?: WithdrawalCategory;
    currency?: Currency;
}

export interface SavingsReserve {
    id: number;
    user_id: number;
    currency_id: number;
    name: string;
    description?: string | null;
    goal_amount?: number | null;
    current_amount: number;
    target_date?: string | null;
    color: string;
    icon?: string | null;
    is_active: boolean;

    currency?: Currency;
    transactions?: ReserveTransaction[];
    progress?: number;
}

export interface ReserveTransaction {
    id: number;
    reserve_id: number;
    currency_id: number;
    amount: number;
    transaction_type: 'deposit' | 'withdrawal';
    transaction_date: string;
    description?: string | null;
    notes?: string | null;

    currency?: Currency;
}

export interface CurrencyExchange {
    id: number;
    user_id: number;
    from_currency_id: number;
    to_currency_id: number;
    to_reserve_id?: number;
    from_amount: number;
    to_amount: number;
    exchange_rate: number;
    exchange_date: string;
    notes?: string;
    from_currency?: Currency;
    to_currency?: Currency;
    to_reserve?: SavingsReserve;
}
