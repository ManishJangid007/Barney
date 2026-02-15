<?php

namespace App\Mcp\Tools;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\Expense;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListExpensesTool extends Tool
{
    protected string $name = 'list_expenses';

    protected string $description = <<<'MARKDOWN'
        Search and filter expenses with optional filters.
        All filters are optional. If no filters are provided, returns the most recent 20 expenses.

        Supports filtering by:
        - Date range (date_from, date_to)
        - Category (single value like "food" or comma-separated like "food,groceries")
        - Account ID
        - Payment method
        - Amount range (min_amount, max_amount)
        - Limit (defaults to 20, max 100)

        Returns matching expenses with account name, total amount, and count.
        Results are sorted by expense_date descending (most recent first).
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $query = Expense::with('account:id,name');

        if ($request->get('date_from')) {
            $query->where('expense_date', '>=', $request->get('date_from'));
        }

        if ($request->get('date_to')) {
            $query->where('expense_date', '<=', $request->get('date_to'));
        }

        if ($request->get('category')) {
            $categories = array_map('trim', explode(',', $request->get('category')));
            $query->whereIn('category', $categories);
        }

        if ($request->get('account_id')) {
            $query->where('account_id', $request->get('account_id'));
        }

        if ($request->get('payment_method')) {
            $query->where('payment_method', $request->get('payment_method'));
        }

        if ($request->get('min_amount')) {
            $query->where('amount', '>=', $request->get('min_amount'));
        }

        if ($request->get('max_amount')) {
            $query->where('amount', '<=', $request->get('max_amount'));
        }

        $limit = min((int) ($request->get('limit', 20)), 100);

        $expenses = $query->orderBy('expense_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        $results = $expenses->map(fn ($e) => [
            'id' => $e->id,
            'category' => $e->category,
            'amount' => $e->amount,
            'description' => $e->description,
            'expense_date' => $e->expense_date->toDateString(),
            'payment_method' => $e->payment_method,
            'account_name' => $e->account?->name,
            'notes' => $e->notes,
        ]);

        return Response::text(json_encode([
            'expenses' => $results,
            'total' => $expenses->sum('amount'),
            'count' => $expenses->count(),
        ], JSON_PRETTY_PRINT));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'date_from' => $schema->string()->description('Start date filter in YYYY-MM-DD format'),
            'date_to' => $schema->string()->description('End date filter in YYYY-MM-DD format'),
            'category' => $schema->string()->description('Filter by category. Single value or comma-separated (e.g. "food" or "food,groceries")'),
            'account_id' => $schema->integer()->description('Filter by account ID'),
            'payment_method' => $schema->string()->enum(PaymentMethod::class)->description('Filter by payment method'),
            'min_amount' => $schema->number()->description('Minimum expense amount'),
            'max_amount' => $schema->number()->description('Maximum expense amount'),
            'limit' => $schema->integer()->description('Max results to return (default 20, max 100)'),
        ];
    }
}
