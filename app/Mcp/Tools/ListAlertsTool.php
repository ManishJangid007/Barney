<?php

namespace App\Mcp\Tools;

use App\Models\Alert;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListAlertsTool extends Tool
{
    protected string $name = 'list_alerts';

    protected string $description = <<<'MARKDOWN'
        List all configured alerts and reminders.
        Optionally filter by active status or alert type.
        Returns each alert's id, type, description, threshold, period, and whether it's active.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $query = Alert::query();

        $activeOnly = $request->get('active_only');
        if ($activeOnly === true || $activeOnly === 'true') {
            $query->where('is_active', true);
        }

        $type = $request->get('type');
        if ($type) {
            $query->where('type', $type);
        }

        $alerts = $query->orderBy('created_at', 'desc')->get();

        return Response::json([
            'alerts' => $alerts->toArray(),
            'count' => $alerts->count(),
            'active_count' => Alert::where('is_active', true)->count(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'active_only' => $schema->boolean()->description('If true, only return active alerts.'),
            'type' => $schema->string()->description('Filter by alert type (e.g. "budget_limit", "reminder").'),
        ];
    }
}
