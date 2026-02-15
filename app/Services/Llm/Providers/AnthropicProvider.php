<?php

namespace App\Services\Llm\Providers;

use App\Services\Llm\ProviderInterface;
use Illuminate\Support\Facades\Http;

class AnthropicProvider implements ProviderInterface
{
    protected string $apiKey;

    protected string $model;

    public function __construct(string $apiKey, string $model)
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    public function sendMessage(array $messages, array $tools, float $temperature): array
    {
        // Extract system message (Anthropic uses a separate 'system' param)
        $systemPrompt = '';
        $anthropicMessages = [];

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemPrompt .= $msg['content'] . "\n";
            } elseif ($msg['role'] === 'tool') {
                // Convert OpenAI tool result → Anthropic tool_result
                // Must be appended to the last user message or as a new user message
                $anthropicMessages[] = [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'tool_result',
                            'tool_use_id' => $msg['tool_call_id'],
                            'content' => $msg['content'] ?? '',
                        ],
                    ],
                ];
            } elseif ($msg['role'] === 'assistant' && isset($msg['tool_calls'])) {
                // Convert OpenAI assistant tool_calls → Anthropic tool_use content blocks
                $content = [];
                if (! empty($msg['content'])) {
                    $content[] = ['type' => 'text', 'text' => $msg['content']];
                }
                foreach ($msg['tool_calls'] as $tc) {
                    $content[] = [
                        'type' => 'tool_use',
                        'id' => $tc['id'],
                        'name' => $tc['function']['name'],
                        'input' => json_decode($tc['function']['arguments'], true) ?? [],
                    ];
                }
                $anthropicMessages[] = ['role' => 'assistant', 'content' => $content];
            } else {
                $anthropicMessages[] = [
                    'role' => $msg['role'],
                    'content' => $msg['content'] ?? '',
                ];
            }
        }

        // Merge consecutive same-role messages (Anthropic requires alternating roles)
        $anthropicMessages = $this->mergeConsecutiveRoles($anthropicMessages);

        // Convert OpenAI tool format → Anthropic tool format
        $anthropicTools = [];
        foreach ($tools as $tool) {
            $fn = $tool['function'];
            $anthropicTools[] = [
                'name' => $fn['name'],
                'description' => $fn['description'] ?? '',
                'input_schema' => $fn['parameters'],
            ];
        }

        $body = [
            'model' => $this->model,
            'max_tokens' => 4096,
            'temperature' => $temperature,
            'messages' => $anthropicMessages,
        ];

        if (trim($systemPrompt) !== '') {
            $body['system'] = trim($systemPrompt);
        }

        if (! empty($anthropicTools)) {
            $body['tools'] = $anthropicTools;
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(120)->post('https://api.anthropic.com/v1/messages', $body);

        if (! $response->successful()) {
            $error = $response->json('error.message', 'Anthropic API error');

            return ['content' => null, 'tool_calls' => [], 'error' => $error];
        }

        $data = $response->json();

        // Parse Anthropic response content blocks
        $textContent = '';
        $toolCalls = [];

        foreach ($data['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                $textContent .= $block['text'];
            } elseif ($block['type'] === 'tool_use') {
                $toolCalls[] = [
                    'id' => $block['id'],
                    'name' => $block['name'],
                    'arguments' => $block['input'] ?? [],
                ];
            }
        }

        return [
            'content' => $textContent ?: null,
            'tool_calls' => $toolCalls,
        ];
    }

    /**
     * Anthropic requires alternating user/assistant roles.
     * Merge consecutive messages with the same role.
     */
    protected function mergeConsecutiveRoles(array $messages): array
    {
        if (empty($messages)) {
            return [];
        }

        $merged = [$messages[0]];

        for ($i = 1; $i < count($messages); $i++) {
            $last = &$merged[count($merged) - 1];
            $current = $messages[$i];

            if ($last['role'] === $current['role']) {
                // Merge content
                $lastContent = is_array($last['content']) ? $last['content'] : [['type' => 'text', 'text' => $last['content']]];
                $currentContent = is_array($current['content']) ? $current['content'] : [['type' => 'text', 'text' => $current['content']]];
                $last['content'] = array_merge($lastContent, $currentContent);
            } else {
                $merged[] = $current;
            }
        }

        return $merged;
    }
}
