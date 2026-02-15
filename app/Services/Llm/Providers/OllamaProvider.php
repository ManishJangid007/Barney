<?php

namespace App\Services\Llm\Providers;

use App\Services\Llm\ProviderInterface;
use Illuminate\Support\Facades\Http;

class OllamaProvider implements ProviderInterface
{
    protected string $model;

    protected string $baseUrl;

    public function __construct(string $model, string $baseUrl = 'http://localhost:11434')
    {
        $this->model = $model;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function sendMessage(array $messages, array $tools, float $temperature): array
    {
        // Convert OpenAI-format messages to Ollama native format
        $ollamaMessages = $this->convertMessages($messages);

        $body = [
            'model' => $this->model,
            'messages' => $ollamaMessages,
            'stream' => false,
            'options' => [
                'temperature' => $temperature,
            ],
        ];

        if (! empty($tools)) {
            $body['tools'] = $tools;
        }

        $response = Http::timeout(120)->post("{$this->baseUrl}/api/chat", $body);

        if (! $response->successful()) {
            $error = $response->json('error', 'Ollama API error: ' . $response->status());

            return ['content' => null, 'tool_calls' => [], 'error' => $error];
        }

        $data = $response->json();
        $message = $data['message'] ?? [];

        $toolCalls = [];
        if (! empty($message['tool_calls'])) {
            foreach ($message['tool_calls'] as $i => $tc) {
                $fn = $tc['function'] ?? [];
                $toolCalls[] = [
                    'id' => 'call_' . ($i + 1) . '_' . uniqid(),
                    'name' => $fn['name'] ?? '',
                    'arguments' => $fn['arguments'] ?? [],
                ];
            }
        }

        return [
            'content' => $message['content'] ?? null,
            'tool_calls' => $toolCalls,
        ];
    }

    /**
     * Convert OpenAI-format messages to Ollama native format.
     * - Strips tool_call_id from tool messages
     * - Converts assistant tool_calls to Ollama format
     */
    protected function convertMessages(array $messages): array
    {
        $converted = [];

        foreach ($messages as $msg) {
            if ($msg['role'] === 'tool') {
                // Ollama tool result: just role + content (no tool_call_id)
                $converted[] = [
                    'role' => 'tool',
                    'content' => $msg['content'] ?? '',
                ];
            } elseif ($msg['role'] === 'assistant' && ! empty($msg['tool_calls'])) {
                // Convert OpenAI tool_calls to Ollama format
                $toolCalls = [];
                foreach ($msg['tool_calls'] as $tc) {
                    $args = $tc['function']['arguments'] ?? '{}';
                    $toolCalls[] = [
                        'function' => [
                            'name' => $tc['function']['name'],
                            'arguments' => is_string($args) ? (json_decode($args, true) ?: new \stdClass()) : ($args ?: new \stdClass()),
                        ],
                    ];
                }
                $converted[] = [
                    'role' => 'assistant',
                    'content' => $msg['content'] ?? '',
                    'tool_calls' => $toolCalls,
                ];
            } else {
                // system, user, assistant (no tool calls) - pass through
                $converted[] = [
                    'role' => $msg['role'],
                    'content' => $msg['content'] ?? '',
                ];
            }
        }

        return $converted;
    }
}
