export type ReportMonthOption = {
    month: number;
    label: string;
};

export type ReportTotals = {
    income: number;
    expenses: number;
    balance: number;
};

export type ExpensesByCategoryRow = {
    category_id: number;
    category_name: string;
    total: number;
};

export type IncomeVsExpensesRow = {
    month: number;
    income: number;
    expenses: number;
};

export type SpendingTrendPoint = {
    date: string;
    expenses: number;
};