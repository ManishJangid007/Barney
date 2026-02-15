<?php

namespace App\Mcp\Tools;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\Account;
use App\Models\Expense;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class LogExpenseTool extends Tool
{
    protected string $name = 'log_expense';

    protected string $description = <<<'MARKDOWN'
        Record a new expense. The linked account balance is automatically deducted.

        IMPORTANT: Before calling this tool, call get_preferences to check for user rules like default accounts or categorization rules.
        Use list_accounts to look up valid account IDs if needed.

        Required fields: account_id, category, amount, payment_method.
        expense_date defaults to today if not provided.
        description and notes are optional but recommended for clarity.

        Valid categories: food, groceries, clothes, travel, rent, utilities, entertainment, health, education, subscriptions, transport, emi, other.
        Valid payment methods: cash, bank, upi, card.

        Returns the created expense and the updated account balance.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $accountId = $request->get('account_id');
        $account = Account::find($accountId);

        if (! $account) {
            return Response::error("Account #{$accountId} not found. Use list_accounts to see valid accounts.");
        }

        $category = ExpenseCategory::tryFrom($request->get('category', ''));
        if (! $category) {
            $valid = implode(', ', array_column(ExpenseCategory::cases(), 'value'));
            return Response::error("Invalid category. Must be one of: {$valid}");
        }

        $paymentMethod = PaymentMethod::tryFrom($request->get('payment_method', ''));
        if (! $paymentMethod) {
            $valid = implode(', ', array_column(PaymentMethod::cases(), 'value'));
            return Response::error("Invalid payment_method. Must be one of: {$valid}");
        }

        $amount = $request->get('amount');
        if (! $amount || $amount <= 0) {
            return Response::error('Amount must be a positive number.');
        }

        $expense = Expense::create([
            'account_id' => $accountId,
            'category' => $category->value,
            'amount' => $amount,
            'description' => $request->get('description'),
            'expense_date' => $request->get('expense_date', now()->toDateString()),
            'payment_method' => $paymentMethod->value,
            'notes' => $request->get('notes'),
        ]);

        $account->refresh();

        return Response::text(json_encode([
            'message' => 'Expense logged',
            'expense' => $expense->toArray(),
            'account_balance' => $account->balance,
        ], JSON_PRETTY_PRINT));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'account_id' => $schema->integer()->required()->description('ID of the account to deduct from. Use list_accounts to find valid IDs.'),
            'category' => $schema->string()->required()->enum(ExpenseCategory::class)->description('Expense category'),
            'amount' => $schema->number()->required()->description('Expense amount (positive number)'),
            'expense_date' => $schema->string()->description('Date of expense in YYYY-MM-DD format. Defaults to today.'),
            'payment_method' => $schema->string()->required()->enum(PaymentMethod::class)->description('How the payment was made'),
            'description' => $schema->string()->nullable()->description('What was purchased (e.g. "Swiggy dinner", "Monthly rent")'),
            'notes' => $schema->string()->nullable()->description('Additional notes'),
        ];
    }
}
