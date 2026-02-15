<?php

namespace App\Services\Llm;

use App\Models\ChatHistory;
use App\Models\PersonalProfile;
use App\Models\Preference;
use App\Services\Llm\Providers\AnthropicProvider;
use App\Services\Llm\Providers\OllamaProvider;
use App\Services\Llm\Providers\OpenAiProvider;
use App\Services\Llm\Providers\OpenRouterProvider;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LlmService
{
    protected const MAX_TOOL_ITERATIONS = 15;

    protected const CONTEXT_MESSAGE_LIMIT = 10;

    protected ToolRunner $toolRunner;

    public function __construct()
    {
        $this->toolRunner = new ToolRunner;
    }

    /**
     * Main entry point. Accepts a user query, returns the final LLM response as a string.
     * All errors are returned as messages (never thrown).
     */
    public function chat(string $userMessage, ?string $sessionId = null): string
    {
        try {
            // Generate session ID if not provided
            $sessionId = $sessionId ?? Str::uuid()->toString();

            // Step 1: Resolve provider config
            $config = $this->resolveConfig();
            if (is_string($config)) {
                return $config; // error message
            }

            // Step 2: Create provider
            $provider = $this->createProvider($config);
            if (is_string($provider)) {
                return $provider; // error message
            }

            // Step 3: Build system prompt
            $systemPrompt = $this->buildSystemPrompt();

            // Step 4: Load conversation history
            $history = $this->loadHistory($sessionId);

            // Step 5: Build messages
            $messages = [];
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
            foreach ($history as $msg) {
                $messages[] = $msg;
            }
            $messages[] = ['role' => 'user', 'content' => $userMessage];

            // Step 6: Build tool definitions
            $tools = $this->toolRunner->buildToolDefinitions();

            // Step 7: Tool calling loop
            $temperature = (float) ($config['temperature'] ?? 0.2);
            $finalContent = $this->runToolLoop($provider, $messages, $tools, $temperature);

            // Step 8: Save conversation
            $this->saveHistory($sessionId, $userMessage, $finalContent);

            return $finalContent;
        } catch (\Throwable $e) {
            Log::error('LlmService::chat error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 'Something went wrong: ' . $e->getMessage();
        }
    }

    /**
     * Resolve and validate LLM configuration from parameters table.
     *
     * @return array|string Config array or error message string.
     */
    protected function resolveConfig(): array|string
    {
        $provider = config('constants.LLM_PROVIDER', '');
        $model = config('constants.MODEL', '');

        if (empty($provider)) {
            return 'LLM provider is not configured. Please set LLM_PROVIDER in the parameters table (openai, openrouter, anthropic, or ollama).';
        }

        if (empty($model)) {
            return 'LLM model is not configured. Please set MODEL in the parameters table.';
        }

        $validProviders = ['openai', 'openrouter', 'anthropic', 'ollama'];
        if (! in_array(strtolower($provider), $validProviders)) {
            return "Invalid LLM_PROVIDER: '{$provider}'. Supported: " . implode(', ', $validProviders);
        }

        // Check API key based on provider
        $keyMap = [
            'openai' => 'OPENAI_KEY',
            'openrouter' => 'OPEN_ROUTER_KEY',
            'anthropic' => 'ANTHROPIC_KEY',
            'ollama' => 'OLLAMA_KEY',
        ];

        $keyName = $keyMap[strtolower($provider)];
        $apiKey = config('constants.' . $keyName, '');

        // Ollama doesn't require an API key
        if (empty($apiKey) && strtolower($provider) !== 'ollama') {
            return "API key not configured. Please set {$keyName} in the parameters table.";
        }

        return [
            'provider' => strtolower($provider),
            'model' => $model,
            'api_key' => $apiKey,
            'temperature' => config('constants.LLM_TEMPERATURE', '0.2'),
        ];
    }

    /**
     * Create the appropriate provider instance.
     *
     * @return ProviderInterface|string Provider instance or error message.
     */
    protected function createProvider(array $config): ProviderInterface|string
    {
        return match ($config['provider']) {
            'openai' => new OpenAiProvider($config['api_key'], $config['model']),
            'openrouter' => new OpenRouterProvider($config['api_key'], $config['model']),
            'anthropic' => new AnthropicProvider($config['api_key'], $config['model']),
            'ollama' => new OllamaProvider($config['model']),
            default => "Unsupported provider: {$config['provider']}",
        };
    }

    /**
     * Build the system prompt from SYSTEM_PROMPT parameter + profile + preferences + current time.
     */
    protected function buildSystemPrompt(): string
    {
        $parts = [];

        // Base system prompt from parameters
        $basePrompt = config('constants.SYSTEM_PROMPT', '');
        if (! empty($basePrompt)) {
            $parts[] = $basePrompt;
        }

        // Append current date and time
        $parts[] = "\nCurrent date and time: " . Carbon::now()->format('l, F j, Y g:i A') . ' (Asia/Kolkata)';

        // Append user profile context
        $profile = PersonalProfile::first();
        if ($profile) {
            $profileInfo = collect([
                'Name' => trim(($profile->first_name ?? '') . ' ' . ($profile->last_name ?? '')),
                'City' => $profile->city,
                'State' => $profile->state,
                'DOB' => $profile->date_of_birth,
                'Email' => $profile->email,
                'Phone' => $profile->phone,
            ])->filter()->map(fn ($v, $k) => "{$k}: {$v}")->implode(', ');

            if ($profileInfo) {
                $parts[] = "\nUser profile: {$profileInfo}";
            }
        }

        // Append user preferences
        $preferences = Preference::all();
        if ($preferences->isNotEmpty()) {
            $prefLines = $preferences->map(fn ($p) => "- {$p->key}: {$p->instruction}")->implode("\n");
            $parts[] = "\nUser preferences:\n{$prefLines}";
        }

        return implode("\n", $parts);
    }

    /**
     * Load recent conversation history for a session.
     */
    protected function loadHistory(string $sessionId): array
    {
        $messages = ChatHistory::where('session_id', $sessionId)
            ->orderBy('created_at', 'desc')
            ->take(self::CONTEXT_MESSAGE_LIMIT)
            ->get()
            ->sortBy('created_at')
            ->values();

        $history = [];
        foreach ($messages as $msg) {
            $history[] = [
                'role' => $msg->role->value,
                'content' => $msg->content,
            ];
        }

        return $history;
    }

    /**
     * Run the tool-calling loop with the LLM provider.
     */
    protected function runToolLoop(ProviderInterface $provider, array &$messages, array $tools, float $temperature): string
    {
        for ($i = 0; $i < self::MAX_TOOL_ITERATIONS; $i++) {
            $response = $provider->sendMessage($messages, $tools, $temperature);

            // Check for provider errors
            if (isset($response['error'])) {
                return "LLM error: {$response['error']}";
            }

            // No tool calls → we have the final answer
            if (empty($response['tool_calls'])) {
                return $response['content'] ?? 'I received an empty response. Please try again.';
            }

            // Build assistant message with tool calls (OpenAI format for message history)
            $assistantMessage = [
                'role' => 'assistant',
                'content' => $response['content'],
                'tool_calls' => [],
            ];

            foreach ($response['tool_calls'] as $tc) {
                $assistantMessage['tool_calls'][] = [
                    'id' => $tc['id'],
                    'type' => 'function',
                    'function' => [
                        'name' => $tc['name'],
                        'arguments' => json_encode($tc['arguments'] ?: new \stdClass()),
                    ],
                ];
            }
            $messages[] = $assistantMessage;

            // Execute each tool and append results
            foreach ($response['tool_calls'] as $tc) {
                $result = $this->toolRunner->executeTool($tc['name'], $tc['arguments']);

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $tc['id'],
                    'content' => $result,
                ];
            }
        }

        // If we exhausted iterations, return what we have
        return $response['content'] ?? 'I needed too many steps to process your request. Please try a simpler question.';
    }

    /**
     * Save the user message and assistant response to chat history.
     */
    protected function saveHistory(string $sessionId, string $userMessage, string $assistantResponse): void
    {
        ChatHistory::create([
            'session_id' => $sessionId,
            'role' => 'user',
            'content' => $userMessage,
        ]);

        ChatHistory::create([
            'session_id' => $sessionId,
            'role' => 'assistant',
            'content' => $assistantResponse,
        ]);
    }
}
