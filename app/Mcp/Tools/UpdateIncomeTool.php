<?php

namespace App\Mcp\Tools;

use App\Enums\IncomeSource;
use App\Models\Account;
use App\Models\Income;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateIncomeTool extends Tool
{
    protected string $name = 'update_income';

    protected string $description = <<<'MARKDOWN'
        Update an existing income record.
        Pass the income id and only the fields you want to change.

        If amount or account_id changes, the balance is automatically corrected:
        the old amount is reversed on the old account, and the new amount is applied to the new account.

        Use list_incomes to find the income ID first.
        Do NOT use this to delete — use request_delete instead.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $id = $request->get('id');
        $income = Income::find($id);

        if (! $income) {
            return Response::error("Income #{$id} not found. Use list_incomes to find valid IDs.");
        }

        $oldAmount = (float) $income->amount;
        $oldAccountId = $income->account_id;

        $data = [];

        if ($request->get('source')) {
            $source = IncomeSource::tryFrom($request->get('source'));
            if (! $source) {
                $valid = implode(', ', array_column(IncomeSource::cases(), 'value'));
                return Response::error("Invalid source. Must be one of: {$valid}");
            }
            $data['source'] = $source->value;
        }

        if ($request->get('account_id')) {
            $newAccount = Account::find($request->get('account_id'));
            if (! $newAccount) {
                return Response::error("Account #{$request->get('account_id')} not found.");
            }
            $data['account_id'] = $request->get('account_id');
        }

        foreach (['amount', 'description', 'income_date', 'notes'] as $field) {
            if ($request->get($field) !== null) {
                $data[$field] = $request->get($field);
            }
        }

        if (empty($data)) {
            return Response::error('No valid fields provided to update.');
        }

        $amountChanged = isset($data['amount']) && (float) $data['amount'] !== $oldAmount;
        $accountChanged = isset($data['account_id']) && (int) $data['account_id'] !== $oldAccountId;

        DB::transaction(function () use ($income, $data, $oldAmount, $oldAccountId, $amountChanged, $accountChanged) {
            if ($amountChanged || $accountChanged) {
                // Reverse old impact
                Account::where('id', $oldAccountId)->decrement('balance', $oldAmount);

                // Update the income
                $income->updateQuietly($data);

                // Apply new impact
                $newAmount = (float) $income->amount;
                Account::where('id', $income->account_id)->increment('balance', $newAmount);
            } else {
                $income->updateQuietly($data);
            }
        });

        $income->refresh();
        $account = Account::find($income->account_id);

        return Response::text(json_encode([
            'message' => 'Income updated',
            'income' => $income->toArray(),
            'old_amount' => number_format($oldAmount, 2, '.', ''),
            'account_balance' => $account->balance,
        ], JSON_PRETTY_PRINT));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required()->description('ID of the income to update. Use list_incomes to find it.'),
            'account_id' => $schema->integer()->description('New account ID if changing which account this income belongs to'),
            'source' => $schema->string()->enum(IncomeSource::class)->description('New income source'),
            'amount' => $schema->number()->description('Corrected amount (balance will be auto-adjusted)'),
            'income_date' => $schema->string()->description('Corrected date in YYYY-MM-DD format'),
            'description' => $schema->string()->nullable()->description('Updated description'),
            'notes' => $schema->string()->nullable()->description('Updated notes'),
        ];
    }
}
