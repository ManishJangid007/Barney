<?php

namespace App\Mcp\Tools;

use App\Enums\IncomeSource;
use App\Models\Account;
use App\Models\Income;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class LogIncomeTool extends Tool
{
    protected string $name = 'log_income';

    protected string $description = <<<'MARKDOWN'
        Record a new income/credit. The linked account balance is automatically increased.

        IMPORTANT: Before calling this tool, call get_preferences to check for user rules like default accounts or salary schedules.
        Use list_accounts to look up valid account IDs if needed.

        Required fields: account_id, source, amount.
        income_date defaults to today if not provided.
        description and notes are optional but recommended.

        Valid sources: salary, freelance, refund, interest, gift, other.

        Returns the created income and the updated account balance.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $accountId = $request->get('account_id');
        $account = Account::find($accountId);

        if (! $account) {
            return Response::error("Account #{$accountId} not found. Use list_accounts to see valid accounts.");
        }

        $source = IncomeSource::tryFrom($request->get('source', ''));
        if (! $source) {
            $valid = implode(', ', array_column(IncomeSource::cases(), 'value'));
            return Response::error("Invalid source. Must be one of: {$valid}");
        }

        $amount = $request->get('amount');
        if (! $amount || $amount <= 0) {
            return Response::error('Amount must be a positive number.');
        }

        $income = Income::create([
            'account_id' => $accountId,
            'source' => $source->value,
            'amount' => $amount,
            'description' => $request->get('description'),
            'income_date' => $request->get('income_date', now()->toDateString()),
            'notes' => $request->get('notes'),
        ]);

        $account->refresh();

        return Response::text(json_encode([
            'message' => 'Income logged',
            'income' => $income->toArray(),
            'account_balance' => $account->balance,
        ], JSON_PRETTY_PRINT));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'account_id' => $schema->integer()->required()->description('ID of the account receiving the income. Use list_accounts to find valid IDs.'),
            'source' => $schema->string()->required()->enum(IncomeSource::class)->description('Income source'),
            'amount' => $schema->number()->required()->description('Income amount (positive number)'),
            'income_date' => $schema->string()->description('Date of income in YYYY-MM-DD format. Defaults to today.'),
            'description' => $schema->string()->nullable()->description('Description (e.g. "Feb salary", "Freelance project X")'),
            'notes' => $schema->string()->nullable()->description('Additional notes'),
        ];
    }
}
