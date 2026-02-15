<?php

namespace App\Mcp\Tools;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\Expense;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateExpenseTool extends Tool
{
    protected string $name = 'update_expense';

    protected string $description = <<<'MARKDOWN'
        Update an existing expense record.
        Pass the expense id and only the fields you want to change.

        If amount or account_id changes, the balance is automatically corrected:
        the old amount is reversed on the old account, and the new amount is applied to the new account.

        Use list_expenses to find the expense ID first.
        Do NOT use this to delete — use request_delete instead.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $id = $request->get('id');
        $expense = Expense::find($id);

        if (! $expense) {
            return Response::error("Expense #{$id} not found. Use list_expenses to find valid IDs.");
        }

        $oldAmount = (float) $expense->amount;
        $oldAccountId = $expense->account_id;

        $data = [];

        if ($request->get('category')) {
            $category = ExpenseCategory::tryFrom($request->get('category'));
            if (! $category) {
                $valid = implode(', ', array_column(ExpenseCategory::cases(), 'value'));
                return Response::error("Invalid category. Must be one of: {$valid}");
            }
            $data['category'] = $category->value;
        }

        if ($request->get('payment_method')) {
            $pm = PaymentMethod::tryFrom($request->get('payment_method'));
            if (! $pm) {
                $valid = implode(', ', array_column(PaymentMethod::cases(), 'value'));
                return Response::error("Invalid payment_method. Must be one of: {$valid}");
            }
            $data['payment_method'] = $pm->value;
        }

        if ($request->get('account_id')) {
            $newAccount = Account::find($request->get('account_id'));
            if (! $newAccount) {
                return Response::error("Account #{$request->get('account_id')} not found.");
            }
            $data['account_id'] = $request->get('account_id');
        }

        foreach (['amount', 'description', 'expense_date', 'notes'] as $field) {
            if ($request->get($field) !== null) {
                $data[$field] = $request->get($field);
            }
        }

        if (empty($data)) {
            return Response::error('No valid fields provided to update.');
        }

        $amountChanged = isset($data['amount']) && (float) $data['amount'] !== $oldAmount;
        $accountChanged = isset($data['account_id']) && (int) $data['account_id'] !== $oldAccountId;

        DB::transaction(function () use ($expense, $data, $oldAmount, $oldAccountId, $amountChanged, $accountChanged) {
            if ($amountChanged || $accountChanged) {
                // Reverse old impact
                Account::where('id', $oldAccountId)->increment('balance', $oldAmount);

                // Update the expense
                $expense->updateQuietly($data);

                // Apply new impact
                $newAmount = (float) $expense->amount;
                Account::where('id', $expense->account_id)->decrement('balance', $newAmount);
            } else {
                $expense->updateQuietly($data);
            }
        });

        $expense->refresh();
        $account = Account::find($expense->account_id);

        return Response::text(json_encode([
            'message' => 'Expense updated',
            'expense' => $expense->toArray(),
            'old_amount' => number_format($oldAmount, 2, '.', ''),
            'account_balance' => $account->balance,
        ], JSON_PRETTY_PRINT));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required()->description('ID of the expense to update. Use list_expenses to find it.'),
            'account_id' => $schema->integer()->description('New account ID if changing which account this expense belongs to'),
            'category' => $schema->string()->enum(ExpenseCategory::class)->description('New expense category'),
            'amount' => $schema->number()->description('Corrected amount (balance will be auto-adjusted)'),
            'expense_date' => $schema->string()->description('Corrected date in YYYY-MM-DD format'),
            'payment_method' => $schema->string()->enum(PaymentMethod::class)->description('New payment method'),
            'description' => $schema->string()->nullable()->description('Updated description'),
            'notes' => $schema->string()->nullable()->description('Updated notes'),
        ];
    }
}
