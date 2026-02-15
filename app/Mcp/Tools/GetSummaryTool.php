<?php

namespace App\Mcp\Tools;

use App\Models\Account;
use App\Models\Expense;
use App\Models\Income;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetSummaryTool extends Tool
{
    protected string $name = 'get_summary';

    protected string $description = <<<'MARKDOWN'
        Get a quick financial overview/dashboard for a given period.
        Use this when the user asks questions like "how am I doing this month", "what's my spending summary", or "show me my finances".

        Periods: today, this_week, this_month, this_year. Defaults to this_month.

        Returns:
        - All account balances with total
        - Total income and expenses in the period
        - Net savings (income - expenses)
        - Top 3 expense categories
        - 5 most recent transactions (expenses + incomes combined)
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $period = $request->get('period', 'this_month');
        [$dateFrom, $dateTo] = $this->getDateRange($period);

        $accounts = Account::all(['id', 'name', 'type', 'balance']);
        $totalBalance = $accounts->sum('balance');

        $totalIncome = Income::whereBetween('income_date', [$dateFrom, $dateTo])->sum('amount');
        $totalExpenses = Expense::whereBetween('expense_date', [$dateFrom, $dateTo])->sum('amount');

        $topCategories = Expense::whereBetween('expense_date', [$dateFrom, $dateTo])
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(3)
            ->get()
            ->map(fn ($row) => ['category' => $row->category, 'amount' => $row->total]);

        $recentExpenses = Expense::with('account:id,name')
            ->whereBetween('expense_date', [$dateFrom, $dateTo])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn ($e) => [
                'type' => 'expense',
                'category' => $e->category,
                'amount' => $e->amount,
                'description' => $e->description,
                'date' => $e->expense_date->toDateString(),
                'account' => $e->account?->name,
            ]);

        $recentIncomes = Income::with('account:id,name')
            ->whereBetween('income_date', [$dateFrom, $dateTo])
            ->orderByDesc('income_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn ($i) => [
                'type' => 'income',
                'source' => $i->source,
                'amount' => $i->amount,
                'description' => $i->description,
                'date' => $i->income_date->toDateString(),
                'account' => $i->account?->name,
            ]);

        $recentTransactions = $recentExpenses->merge($recentIncomes)
            ->sortByDesc('date')
            ->values()
            ->take(5);

        return Response::text(json_encode([
            'period' => $period,
            'date_range' => ['from' => $dateFrom->toDateString(), 'to' => $dateTo->toDateString()],
            'accounts' => $accounts->map(fn ($a) => ['name' => $a->name, 'type' => $a->type, 'balance' => $a->balance]),
            'total_balance' => $totalBalance,
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'net' => $totalIncome - $totalExpenses,
            'top_categories' => $topCategories,
            'recent_transactions' => $recentTransactions,
        ], JSON_PRETTY_PRINT));
    }

    protected function getDateRange(string $period): array
    {
        $now = Carbon::now();

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()->enum(['today', 'this_week', 'this_month', 'this_year'])->description('Time period for the summary. Defaults to this_month.'),
        ];
    }
}
