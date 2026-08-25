# Módulo Finance

Models: Purchase, PurchaseCategory, Income, IncomeSource, Debt, DebtPayment, CreditCard, Currency, ExchangeRate, CurrencyExchange, Withdrawal, WithdrawalCategory, SavingsReserve, ReserveTransaction

Rutas 13: purchases, incomes, debts (+payments), credit-cards, currencies (+restore), exchange-rates (+convert), income-sources, categories, statistics, withdrawal-categories, withdrawals, savings-reserves (+deposit/withdraw), currency-exchanges

Pages: `finance/dashboard.tsx`, `finance/purchases/index.tsx`, `finance/debts/*`, `finance/credit-cards/*`, `finance/currencies/*`, etc.

Dashboard: balances por moneda, overdueDebts alert, recentTransactions, monthly stats, management grid.

QA diario: crear purchase/income/debt, filtro purchases (category/currency/date), overdue warning, savings deposit/withdraw, exchange convert, currencies restore.

Features: Budgets mensuales, Savings Goals ring, cashflow chart, breakdown chart-1..5 rojizos.
