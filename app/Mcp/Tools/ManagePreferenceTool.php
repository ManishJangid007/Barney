<?php

namespace App\Mcp\Tools;

use App\Models\Preference;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ManagePreferenceTool extends Tool
{
    protected string $name = 'manage_preference';

    protected string $description = <<<'MARKDOWN'
        Add, update, or remove a user preference/rule.
        Preferences are persistent rules that guide your decision-making (e.g. "Use HDFC for daily expenses").
        Maximum 10 preferences allowed. If the limit is reached, ask the user to remove one before adding.
        The "key" is a unique short label (e.g. "default_expense_account").
        The "instruction" is the full rule text.

        Actions:
        - "add": Creates a new preference. Requires key and instruction. Fails if 10 already exist or key is duplicate.
        - "update": Updates the instruction for an existing key. Requires key and instruction.
        - "remove": Deletes a preference by key. Only requires key.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $action = $request->get('action');
        $key = $request->get('key');
        $instruction = $request->get('instruction');

        return match ($action) {
            'add' => $this->addPreference($key, $instruction),
            'update' => $this->updatePreference($key, $instruction),
            'remove' => $this->removePreference($key),
            default => Response::error('Invalid action. Must be one of: add, update, remove.'),
        };
    }

    protected function addPreference(string $key, ?string $instruction): Response
    {
        if (! $instruction) {
            return Response::error('The "instruction" field is required when adding a preference.');
        }

        if (Preference::count() >= Preference::MAX_ROWS) {
            return Response::error('Maximum ' . Preference::MAX_ROWS . ' preferences reached. Remove one before adding.');
        }

        if (Preference::where('key', $key)->exists()) {
            return Response::error("Preference with key \"{$key}\" already exists. Use action \"update\" to modify it.");
        }

        $pref = Preference::create(['key' => $key, 'instruction' => $instruction]);

        return Response::text(json_encode([
            'message' => 'Preference added',
            'preference' => ['key' => $pref->key, 'instruction' => $pref->instruction],
            'total' => Preference::count(),
        ], JSON_PRETTY_PRINT));
    }

    protected function updatePreference(string $key, ?string $instruction): Response
    {
        if (! $instruction) {
            return Response::error('The "instruction" field is required when updating a preference.');
        }

        $pref = Preference::where('key', $key)->first();

        if (! $pref) {
            return Response::error("Preference with key \"{$key}\" not found.");
        }

        $pref->update(['instruction' => $instruction]);

        return Response::text(json_encode([
            'message' => 'Preference updated',
            'preference' => ['key' => $pref->key, 'instruction' => $pref->instruction],
        ], JSON_PRETTY_PRINT));
    }

    protected function removePreference(string $key): Response
    {
        $pref = Preference::where('key', $key)->first();

        if (! $pref) {
            return Response::error("Preference with key \"{$key}\" not found.");
        }

        $pref->delete();

        return Response::text(json_encode([
            'message' => 'Preference removed',
            'key' => $key,
            'total' => Preference::count(),
        ], JSON_PRETTY_PRINT));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->required()->enum(['add', 'update', 'remove'])->description('Action to perform: add, update, or remove'),
            'key' => $schema->string()->required()->description('Unique short label for the preference (e.g. "default_expense_account")'),
            'instruction' => $schema->string()->description('The full rule/instruction text. Required for add and update actions.'),
        ];
    }
}
