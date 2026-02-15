<?php

namespace App\Mcp\Tools;

use App\Models\Account;
use App\Models\Transfer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class TransferFundsTool extends Tool
{
    protected string $name = 'transfer_funds';

    protected string $description = <<<'MARKDOWN'
        Move money between the user's own accounts (e.g. bank to cash, salary account to savings).
        The source account balance is deducted and the destination account balance is increased automatically.

        Use list_accounts to look up valid account IDs.
        from_account_id and to_account_id must be different.
        transfer_date defaults to today if not provided.

        Returns the transfer record and both updated account balances.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $fromId = $request->get('from_account_id');
        $toId = $request->get('to_account_id');

        if ($fromId == $toId) {
            return Response::error('from_account_id and to_account_id must be different.');
        }

        $fromAccount = Account::find($fromId);
        if (! $fromAccount) {
            return Response::error("Source account #{$fromId} not found. Use list_accounts to see valid accounts.");
        }

        $toAccount = Account::find($toId);
        if (! $toAccount) {
            return Response::error("Destination account #{$toId} not found. Use list_accounts to see valid accounts.");
        }

        $amount = $request->get('amount');
        if (! $amount || $amount <= 0) {
            return Response::error('Amount must be a positive number.');
        }

        $transfer = Transfer::create([
            'from_account_id' => $fromId,
            'to_account_id' => $toId,
            'amount' => $amount,
            'transfer_date' => $request->get('transfer_date', now()->toDateString()),
            'notes' => $request->get('notes'),
        ]);

        $fromAccount->refresh();
        $toAccount->refresh();

        return Response::text(json_encode([
            'message' => 'Transfer complete',
            'transfer' => [
                'id' => $transfer->id,
                'from_account' => $fromAccount->name,
                'to_account' => $toAccount->name,
                'amount' => $transfer->amount,
                'transfer_date' => $transfer->transfer_date->toDateString(),
                'notes' => $transfer->notes,
            ],
            'balances' => [
                $fromAccount->name => $fromAccount->balance,
                $toAccount->name => $toAccount->balance,
            ],
        ], JSON_PRETTY_PRINT));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'from_account_id' => $schema->integer()->required()->description('ID of the source account (money goes out)'),
            'to_account_id' => $schema->integer()->required()->description('ID of the destination account (money comes in)'),
            'amount' => $schema->number()->required()->description('Amount to transfer (positive number)'),
            'transfer_date' => $schema->string()->description('Date of transfer in YYYY-MM-DD format. Defaults to today.'),
            'notes' => $schema->string()->nullable()->description('Notes (e.g. "ATM withdrawal", "Monthly savings")'),
        ];
    }
}
