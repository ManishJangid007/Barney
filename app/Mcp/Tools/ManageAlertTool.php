<?php

namespace App\Mcp\Tools;

use App\Enums\AlertPeriod;
use App\Enums\AlertType;
use App\Models\Alert;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ManageAlertTool extends Tool
{
    protected string $name = 'manage_alert';

    protected string $description = <<<'MARKDOWN'
        Add, update, or remove a smart alert/reminder.
        Alerts are checked periodically and the user is notified via Telegram when triggered.

        Alert types:
        - "budget_limit": Alert when spending in a category exceeds threshold for the period (e.g. "food > ₹5000/month")
        - "low_balance": Alert when an account balance drops below threshold (e.g. "HDFC < ₹10,000")
        - "spending_spike": Alert when daily spending is unusually high compared to average. No threshold needed.
        - "reminder": Recurring reminder message (e.g. "Pay rent on 1st of month"). No threshold needed.
        - "income_expected": Alert if expected income hasn't arrived by a date (e.g. "Salary not received by 5th")
        - "daily_digest": Send a daily spending/balance summary. No threshold or category needed.

        Actions:
        - "add": Create a new alert. Requires type and description. Other fields depend on type.
        - "update": Update an existing alert by id. Pass only the fields to change.
        - "remove": Delete an alert by id.
        - "toggle": Enable or disable an alert by id.

        The "description" field is the human-readable rule that the LLM will evaluate (e.g. "Alert me if food spending exceeds 5000 this month").
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $action = $request->get('action');

        return match ($action) {
            'add' => $this->addAlert($request),
            'update' => $this->updateAlert($request),
            'remove' => $this->removeAlert($request),
            'toggle' => $this->toggleAlert($request),
            default => Response::error('Invalid action. Must be one of: add, update, remove, toggle.'),
        };
    }

    protected function addAlert(Request $request): Response
    {
        $type = $request->get('type');
        $description = $request->get('description');

        if (! $type || ! $description) {
            return Response::error('Both "type" and "description" are required when adding an alert.');
        }

        $validTypes = array_column(AlertType::cases(), 'value');
        if (! in_array($type, $validTypes)) {
            return Response::error('Invalid type. Must be one of: ' . implode(', ', $validTypes));
        }

        $data = [
            'type' => $type,
            'description' => $description,
            'category' => $request->get('category'),
            'account_id' => $request->get('account_id'),
            'threshold' => $request->get('threshold'),
            'period' => $request->get('period'),
        ];

        if ($data['account_id']) {
            $account = \App\Models\Account::find($data['account_id']);
            if (! $account) {
                return Response::error("Account ID {$data['account_id']} not found.");
            }
        }

        $alert = Alert::create($data);

        return Response::json([
            'message' => 'Alert created',
            'alert' => $alert->toArray(),
            'total_active' => Alert::where('is_active', true)->count(),
        ]);
    }

    protected function updateAlert(Request $request): Response
    {
        $id = $request->get('id');
        if (! $id) {
            return Response::error('The "id" field is required for update.');
        }

        $alert = Alert::find($id);
        if (! $alert) {
            return Response::error("Alert ID {$id} not found.");
        }

        $fields = ['type', 'description', 'category', 'account_id', 'threshold', 'period'];
        $updates = [];
        foreach ($fields as $field) {
            $value = $request->get($field);
            if ($value !== null) {
                $updates[$field] = $value;
            }
        }

        if (empty($updates)) {
            return Response::error('No fields to update. Provide at least one of: type, description, category, account_id, threshold, period.');
        }

        $alert->update($updates);

        return Response::json([
            'message' => 'Alert updated',
            'alert' => $alert->fresh()->toArray(),
        ]);
    }

    protected function removeAlert(Request $request): Response
    {
        $id = $request->get('id');
        if (! $id) {
            return Response::error('The "id" field is required for remove.');
        }

        $alert = Alert::find($id);
        if (! $alert) {
            return Response::error("Alert ID {$id} not found.");
        }

        $alert->delete();

        return Response::json([
            'message' => 'Alert removed',
            'id' => $id,
            'total_active' => Alert::where('is_active', true)->count(),
        ]);
    }

    protected function toggleAlert(Request $request): Response
    {
        $id = $request->get('id');
        if (! $id) {
            return Response::error('The "id" field is required for toggle.');
        }

        $alert = Alert::find($id);
        if (! $alert) {
            return Response::error("Alert ID {$id} not found.");
        }

        $alert->update(['is_active' => ! $alert->is_active]);

        return Response::json([
            'message' => 'Alert ' . ($alert->is_active ? 'enabled' : 'disabled'),
            'alert' => $alert->fresh()->toArray(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->required()->enum(['add', 'update', 'remove', 'toggle'])->description('Action: add, update, remove, or toggle'),
            'id' => $schema->integer()->description('Alert ID. Required for update, remove, and toggle.'),
            'type' => $schema->string()->enum(AlertType::class)->description('Alert type. Required for add.'),
            'description' => $schema->string()->description('Human-readable alert rule. Required for add.'),
            'category' => $schema->string()->description('Expense category for budget_limit alerts (e.g. "food", "transport").'),
            'account_id' => $schema->integer()->description('Account ID for low_balance alerts.'),
            'threshold' => $schema->number()->description('Amount threshold (e.g. 5000 for budget limit, 10000 for low balance).'),
            'period' => $schema->string()->enum(AlertPeriod::class)->description('Period for budget alerts: daily, weekly, or monthly.'),
        ];
    }
}
