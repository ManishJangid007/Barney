<?php

namespace App\Mcp\Tools;

use App\Models\Transfer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListTransfersTool extends Tool
{
    protected string $name = 'list_transfers';

    protected string $description = <<<'MARKDOWN'
        List transfer history between accounts with optional filters.
        All filters are optional. If no filters are provided, returns the most recent 20 transfers.

        Supports filtering by:
        - Date range (date_from, date_to)
        - Account ID (matches either source or destination)
        - Limit (defaults to 20, max 100)

        Returns transfer rows with source and destination account names.
        Results are sorted by transfer_date descending (most recent first).
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $query = Transfer::with(['fromAccount:id,name', 'toAccount:id,name']);

        if ($request->get('date_from')) {
            $query->where('transfer_date', '>=', $request->get('date_from'));
        }

        if ($request->get('date_to')) {
            $query->where('transfer_date', '<=', $request->get('date_to'));
        }

        if ($request->get('account_id')) {
            $accountId = $request->get('account_id');
            $query->where(function ($q) use ($accountId) {
                $q->where('from_account_id', $accountId)
                  ->orWhere('to_account_id', $accountId);
            });
        }

        $limit = min((int) ($request->get('limit', 20)), 100);

        $transfers = $query->orderBy('transfer_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();

        $results = $transfers->map(fn ($t) => [
            'id' => $t->id,
            'from_account' => $t->fromAccount?->name,
            'to_account' => $t->toAccount?->name,
            'amount' => $t->amount,
            'transfer_date' => $t->transfer_date->toDateString(),
            'notes' => $t->notes,
        ]);

        return Response::text(json_encode([
            'transfers' => $results,
            'count' => $transfers->count(),
        ], JSON_PRETTY_PRINT));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'date_from' => $schema->string()->description('Start date filter in YYYY-MM-DD format'),
            'date_to' => $schema->string()->description('End date filter in YYYY-MM-DD format'),
            'account_id' => $schema->integer()->description('Filter by account ID (matches either source or destination)'),
            'limit' => $schema->integer()->description('Max results to return (default 20, max 100)'),
        ];
    }
}
