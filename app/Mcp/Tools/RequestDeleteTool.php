<?php

namespace App\Mcp\Tools;

use App\Models\DeleteRequest;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class RequestDeleteTool extends Tool
{
    protected string $name = 'request_delete';

    protected string $description = <<<'MARKDOWN'
        Raise a delete request for a record. NEVER delete records directly — always use this tool first.

        This creates a pending delete request and returns the full details of the record that will be deleted.
        You MUST then present these details to the user and ask for explicit confirmation before calling confirm_delete.

        Allowed tables: expenses, incomes, transfers, accounts.
        The record_id must exist in the specified table.

        Workflow:
        1. Call request_delete with table_name and record_id → returns pending request + target record details
        2. Show the user exactly what will be deleted and ask "Should I delete this?"
        3. Only after user says yes → call confirm_delete with the delete_request_id
    MARKDOWN;

    private const ALLOWED_TABLES = ['expenses', 'incomes', 'transfers', 'accounts'];

    public function handle(Request $request): Response
    {
        $tableName = $request->get('table_name');
        $recordId = $request->get('record_id');
        $reason = $request->get('reason');

        if (! in_array($tableName, self::ALLOWED_TABLES)) {
            return Response::error('Invalid table_name. Must be one of: ' . implode(', ', self::ALLOWED_TABLES));
        }

        $record = DB::table($tableName)->where('id', $recordId)->first();

        if (! $record) {
            return Response::error("Record #{$recordId} not found in {$tableName}.");
        }

        $deleteRequest = DeleteRequest::create([
            'table_name' => $tableName,
            'record_id' => $recordId,
            'reason' => $reason,
            'status' => 'pending',
        ]);

        return Response::text(json_encode([
            'message' => 'Delete request created. Please confirm with the user before calling confirm_delete.',
            'delete_request' => [
                'id' => $deleteRequest->id,
                'table_name' => $tableName,
                'record_id' => $recordId,
                'reason' => $reason,
                'status' => 'pending',
            ],
            'target_record' => (array) $record,
        ], JSON_PRETTY_PRINT));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'table_name' => $schema->string()->required()->enum(self::ALLOWED_TABLES)->description('Table containing the record to delete: expenses, incomes, transfers, or accounts'),
            'record_id' => $schema->integer()->required()->description('Primary key (id) of the record to delete'),
            'reason' => $schema->string()->nullable()->description('Reason for deletion (e.g. "Duplicate entry", "Entered by mistake")'),
        ];
    }
}
