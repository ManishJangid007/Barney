<?php

namespace App\Mcp\Tools;

use App\Models\Preference;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetPreferencesTool extends Tool
{
    protected string $name = 'get_preferences';

    protected string $description = <<<'MARKDOWN'
        Fetch all user preferences and rules.
        IMPORTANT: Call this tool BEFORE performing any finance action (logging expenses, income, transfers, etc.)
        to learn the user's habits and defaults (e.g. default account, categorization rules, salary schedule).
        Preferences act as decision-making rules — always respect them unless the user explicitly overrides in their message.
        Returns all preferences (max 10). Returns an empty list if none are set.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $preferences = Preference::all(['key', 'instruction']);

        return Response::text(json_encode([
            'count' => $preferences->count(),
            'preferences' => $preferences->toArray(),
        ], JSON_PRETTY_PRINT));
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
