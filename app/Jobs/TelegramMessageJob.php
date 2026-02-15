<?php

namespace App\Jobs;

use App\Services\Llm\LlmService;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TelegramMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        protected string|int $chatId,
        protected string $messageText,
    ) {}

    public function handle(): void
    {
        $telegram = new TelegramService;
        $telegram->sendTypingAction($this->chatId);

        $llm = new LlmService;
        // Use chat_id as session_id for persistent conversation
        $response = $llm->chat($this->messageText, (string) $this->chatId);

        $telegram->sendMessage($this->chatId, $response);
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error('TelegramMessageJob failed', [
            'chat_id' => $this->chatId,
            'message' => $this->messageText,
            'error' => $exception?->getMessage(),
        ]);

        // Notify user about the failure
        $telegram = new TelegramService;
        $telegram->sendMessage($this->chatId, 'Something went wrong while processing your message. Please try again.');
    }
}
