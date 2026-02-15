<?php

namespace App\Mcp\Tools;

use App\Models\Account;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListAccountsTool extends Tool
{
    protected string $name = 'list_accounts';

    protected string $description = <<<'MARKDOWN'
        List all accounts (bank, cash, wallet) with their current balances.
        Returns every account with id, name, type, bank_name, account_number, balance, and currency.
        Also returns the total balance across all accounts.
        Use this to look up account IDs before logging expenses, incomes, or transfers.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $accounts = Account::all();

        return Response::text(json_encode([
            'accounts' => $accounts->toArray(),
            'total_balance' => $accounts->sum('balance'),
        ], JSON_PRETTY_PRINT));
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
