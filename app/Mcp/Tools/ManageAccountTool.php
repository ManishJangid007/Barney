<?php

namespace App\Mcp\Tools;

use App\Enums\AccountType;
use App\Models\Account;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ManageAccountTool extends Tool
{
    protected string $name = 'manage_account';

    protected string $description = <<<'MARKDOWN'
        Create or update an account (bank, cash, or wallet).

        Actions:
        - "create": Creates a new account. Requires name, type, and balance. bank_name and account_number are optional (relevant for bank accounts only).
        - "update": Updates an existing account. Requires id. Only pass fields you want to change. Do NOT use this to adjust balance from expenses/income — balances are auto-managed by observers.

        Account types: bank, cash, wallet.
        Currency defaults to INR.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $action = $request->get('action');

        return match ($action) {
            'create' => $this->createAccount($request),
            'update' => $this->updateAccount($request),
            default => Response::error('Invalid action. Must be one of: create, update.'),
        };
    }

    protected function createAccount(Request $request): Response
    {
        $data = $request->all();

        $required = ['name', 'type', 'balance'];
        $missing = array_diff($required, array_keys(array_filter($data, fn ($v) => $v !== null)));

        if (! empty($missing)) {
            return Response::error('Missing required fields: ' . implode(', ', $missing));
        }

        $account = Account::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'bank_name' => $data['bank_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'balance' => $data['balance'],
            'currency' => $data['currency'] ?? 'INR',
            'notes' => $data['notes'] ?? null,
        ]);

        return Response::text(json_encode([
            'message' => 'Account created',
            'account' => $account->toArray(),
        ], JSON_PRETTY_PRINT));
    }

    protected function updateAccount(Request $request): Response
    {
        $id = $request->get('id');

        if (! $id) {
            return Response::error('The "id" field is required for update.');
        }

        $account = Account::find($id);

        if (! $account) {
            return Response::error("Account #{$id} not found.");
        }

        $fields = ['name', 'type', 'bank_name', 'account_number', 'balance', 'currency', 'notes'];
        $data = array_filter(
            $request->all(),
            fn ($value, $key) => in_array($key, $fields) && $value !== null,
            ARRAY_FILTER_USE_BOTH
        );

        if (empty($data)) {
            return Response::error('No valid fields provided to update.');
        }

        $account->update($data);

        return Response::text(json_encode([
            'message' => 'Account updated',
            'account' => $account->fresh()->toArray(),
        ], JSON_PRETTY_PRINT));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->required()->enum(['create', 'update'])->description('Action to perform: create or update'),
            'id' => $schema->integer()->description('Account ID. Required for update.'),
            'name' => $schema->string()->description('Account display name (e.g. "HDFC Savings", "Cash")'),
            'type' => $schema->string()->enum(AccountType::class)->description('Account type: bank, cash, or wallet'),
            'bank_name' => $schema->string()->nullable()->description('Bank name (e.g. "HDFC", "SBI"). Only for bank accounts.'),
            'account_number' => $schema->string()->nullable()->description('Last 4 digits of account number (e.g. "***4521")'),
            'balance' => $schema->number()->description('Current balance amount'),
            'currency' => $schema->string()->description('Currency code, defaults to INR'),
            'notes' => $schema->string()->nullable()->description('Any notes about this account'),
        ];
    }
}
