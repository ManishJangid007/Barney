<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected string $token;

    protected string $baseUrl;

    public function __construct()
    {
        $this->token = config('constants.TELEGRAM_BOT_TOKEN', '');
        $this->baseUrl = "https://api.telegram.org/bot{$this->token}";
    }

    /**
     * Send a text message to a chat.
     * Splits long messages into 4096-char chunks (Telegram limit).
     */
    public function sendMessage(string|int $chatId, string $text): bool
    {
        if (empty($this->token)) {
            Log::error('TelegramService: TELEGRAM_BOT_TOKEN not configured');

            return false;
        }

        if (empty($text)) {
            $text = 'I have nothing to say.';
        }

        // Telegram limit is 4096 characters per message
        $chunks = str_split($text, 4000);

        foreach ($chunks as $chunk) {
            $response = Http::post("{$this->baseUrl}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $chunk,
                'parse_mode' => 'Markdown',
            ]);

            if (! $response->successful()) {
                // Retry without Markdown if parse fails (common with special chars)
                $errorDescription = $response->json('description', '');
                if (str_contains($errorDescription, "can't parse")) {
                    Http::post("{$this->baseUrl}/sendMessage", [
                        'chat_id' => $chatId,
                        'text' => $chunk,
                    ]);
                } else {
                    Log::error('TelegramService: sendMessage failed', [
                        'chat_id' => $chatId,
                        'status' => $response->status(),
                        'body' => $response->json(),
                    ]);

                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Send "typing..." indicator to a chat.
     */
    public function sendTypingAction(string|int $chatId): void
    {
        Http::post("{$this->baseUrl}/sendChatAction", [
            'chat_id' => $chatId,
            'action' => 'typing',
        ]);
    }

    /**
     * Register webhook URL with Telegram.
     */
    public function setWebhook(string $url): array
    {
        $response = Http::post("{$this->baseUrl}/setWebhook", [
            'url' => $url,
            'allowed_updates' => ['message'],
        ]);

        return $response->json();
    }

    /**
     * Remove webhook registration.
     */
    public function removeWebhook(): array
    {
        $response = Http::post("{$this->baseUrl}/deleteWebhook");

        return $response->json();
    }

    /**
     * Get current webhook info.
     */
    public function getWebhookInfo(): array
    {
        $response = Http::get("{$this->baseUrl}/getWebhookInfo");

        return $response->json();
    }
}
