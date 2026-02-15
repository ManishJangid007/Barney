<?php

namespace App\Services\Llm;

interface ProviderInterface
{
    /**
     * Send messages to the LLM and get a response.
     *
     * Messages are always in OpenAI format:
     *   [['role' => 'system|user|assistant|tool', 'content' => '...', ...]]
     *
     * Tools are always in OpenAI function-calling format:
     *   [['type' => 'function', 'function' => ['name' => '...', 'description' => '...', 'parameters' => [...]]]]
     *
     * Returns normalized response:
     *   ['content' => string|null, 'tool_calls' => [['id' => string, 'name' => string, 'arguments' => array], ...]]
     */
    public function sendMessage(array $messages, array $tools, float $temperature): array;
}
