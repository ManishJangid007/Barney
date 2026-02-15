<?php

namespace App\Mcp\Tools;

use App\Models\Account;
use App\Models\DeleteRequest;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Transfer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ConfirmDeleteTool extends Tool
{
    protected string $name = 'confirm_delete';

    protected string $description = <<<'MARKDOWN'
        Execute a previously created delete request AFTER the user has explicitly confirmed.

        IMPORTANT: Only call this tool after:
        1. You called request_delete to create a pending request
        2. You showed the user the record details
        3. The user explicitly confirmed the deletion

        The delete request must be in "pending" status. If the target record is an expense, income, or transfer,
        the associated account balance is automatically reversed.

        Returns a confirmation summary with updated balance info.
    MARKDOWN;

    private const MODEL_MAP = [
        'expenses' => Expense::class,
        'incomes' => Income::class,
        'transfers' => Transfer::class,
        'accounts' => Account::class,
    ];

    public function handle(Request $request): Response
    {
        $deleteRequestId = $request->get('delete_request_id');
        $deleteRequest = DeleteRequest::find($deleteRequestId);

        if (! $deleteRequest) {
            return Response::error("Delete request #{$deleteRequestId} not found.");
        }

        if ($deleteRequest->status->value !== 'pending') {
            return Response::error("Delete request #{$deleteRequestId} is not pending. Current status: {$deleteRequest->status->value}");
        }

        $modelClass = self::MODEL_MAP[$deleteRequest->table_name] ?? null;

        if (! $modelClass) {
            return Response::error("Unknown table: {$deleteRequest->table_name}");
        }

        $record = $modelClass::find($deleteRequest->record_id);

        if (! $record) {
            $deleteRequest->update(['status' => 'done']);
            return Response::error("Record #{$deleteRequest->record_id} no longer exists in {$deleteRequest->table_name}. Request marked as done.");
        }

        $result = [
            'message' => 'Record deleted successfully',
            'delete_request_id' => $deleteRequestId,
            'deleted_from' => $deleteRequest->table_name,
            'deleted_record_id' => $deleteRequest->record_id,
        ];

        DB::transaction(function () use ($deleteRequest, $record, &$result) {
            $deleteRequest->update(['status' => 'confirmed']);

            // Delete the record — observers handle balance reversal for expenses, incomes, transfers
            $record->delete();

            $deleteRequest->update(['status' => 'done']);

            // Report balance changes for financial records
            if ($record instanceof Expense) {
                $account = Account::find($record->account_id);
                $result['balance_reversed'] = true;
                $result['account'] = $account?->name;
                $result['new_balance'] = $account?->balance;
            } elseif ($record instanceof Income) {
                $account = Account::find($record->account_id);
                $result['balance_reversed'] = true;
                $result['account'] = $account?->name;
                $result['new_balance'] = $account?->balance;
            } elseif ($record instanceof Transfer) {
                $from = Account::find($record->from_account_id);
                $to = Account::find($record->to_account_id);
                $result['balance_reversed'] = true;
                $result['balances'] = [
                    $from?->name => $from?->balance,
                    $to?->name => $to?->balance,
                ];
            }
        });

        return Response::text(json_encode($result, JSON_PRETTY_PRINT));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'delete_request_id' => $schema->integer()->required()->description('ID of the pending delete request to execute. Must have been created by request_delete first.'),
        ];
    }
}
