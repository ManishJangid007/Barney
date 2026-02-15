<?php

namespace App\Services\Llm\Providers;

use App\Services\Llm\ProviderInterface;
use OpenAI;

class OpenRouterProvider implements ProviderInterface
{
    protected $client;

    protected string $model;

    public function __construct(string $apiKey, string $model)
    {
        $this->model = $model;
        $this->client = OpenAI::factory()
            ->withApiKey($apiKey)
            ->withBaseUri('https://openrouter.ai/api/v1')
            ->make();
    }

    public function sendMessage(array $messages, array $tools, float $temperature): array
    {
        $params = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $temperature,
        ];

        if (! empty($tools)) {
            $params['tools'] = $tools;
        }

        $response = $this->client->chat()->create($params);

        $choice = $response->choices[0] ?? null;
        if (! $choice) {
            return ['content' => null, 'tool_calls' => []];
        }

        $message = $choice->message;

        $toolCalls = [];
        if (! empty($message->toolCalls)) {
            foreach ($message->toolCalls as $tc) {
                $toolCalls[] = [
                    'id' => $tc->id,
                    'name' => $tc->function->name,
                    'arguments' => json_decode($tc->function->arguments, true) ?? [],
                ];
            }
        }

        return [
            'content' => $message->content,
            'tool_calls' => $toolCalls,
        ];
    }
}
