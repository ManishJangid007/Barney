<?php

namespace App\Mcp\Tools;

use App\Enums\IncomeSource;
use App\Models\Income;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListIncomesTool extends Tool
{
    protected string $name = 'list_incomes';

    protected string $description = <<<'MARKDOWN'
        Search and filter income records with optional filters.
        All filters are optional. If no filters are provided, returns the most recent 20 incomes.

        Supports filtering by:
        - Date range (date_from, date_to)
        - Source (e.g. "salary", "freelance")
        - Account ID
        - Amount range (min_amount, max_amount)
        - Limit (defaults to 20, max 100)

        Returns matching incomes with account name, total amount, and count.
        Results are sorted by income_date descending (most recent first).
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $query = Income::with('account:id,name');

        if ($request->get('date_from')) {
            $query->where('income_date', '>=', $request->get('date_from'));
        }

        if ($request->get('date_to')) {
            $query->where('income_date', '<=', $request->get('date_to'));
        }

        if ($request->get('source')) {
            $query->where('source', $request->get('source'));
        }

        if ($request->get('account_id')) {
            $query->where('account_id', $request->get('account_id'));
        }

        if ($request->get('min_amount')) {
            $query->where('amount', '>=', $request->get('min_amount'));
        }

        if ($request->get('max_amount')) {
            $query->where('amount', '<=', $request->get('max_amount'));
        }

        $limit = min((int) ($request->get('limit', 20)), 100);

        $incomes = $query->orderBy('income_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        $results = $incomes->map(fn ($i) => [
            'id' => $i->id,
            'source' => $i->source,
            'amount' => $i->amount,
            'description' => $i->description,
            'income_date' => $i->income_date->toDateString(),
            'account_name' => $i->account?->name,
            'notes' => $i->notes,
        ]);

        return Response::text(json_encode([
            'incomes' => $results,
            'total' => $incomes->sum('amount'),
            'count' => $incomes->count(),
        ], JSON_PRETTY_PRINT));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'date_from' => $schema->string()->description('Start date filter in YYYY-MM-DD format'),
            'date_to' => $schema->string()->description('End date filter in YYYY-MM-DD format'),
            'source' => $schema->string()->enum(IncomeSource::class)->description('Filter by income source'),
            'account_id' => $schema->integer()->description('Filter by account ID'),
            'min_amount' => $schema->number()->description('Minimum income amount'),
            'max_amount' => $schema->number()->description('Maximum income amount'),
            'limit' => $schema->integer()->description('Max results to return (default 20, max 100)'),
        ];
    }
}
