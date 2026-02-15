<?php

namespace App\Http\Controllers;

use App\Jobs\TelegramMessageJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramController extends Controller
{
    public function webhook(Request $request): JsonResponse
    {
        $update = $request->all();

        // Extract message (ignore edits, channel posts, etc.)
        $message = $update['message'] ?? null;
        if (! $message) {
            return response()->json(['ok' => true]);
        }

        $chatId = $message['chat']['id'] ?? null;
        $text = $message['text'] ?? null;

        // Ignore non-text messages (photos, stickers, etc.)
        if (! $chatId || ! $text) {
            return response()->json(['ok' => true]);
        }

        // Security: only respond to admin
        $adminChatId = config('constants.TELEGRAM_ADMIN_CHAT_ID', '');
        if (! empty($adminChatId) && (string) $chatId !== (string) $adminChatId) {
            return response()->json(['ok' => true]);
        }

        // Dispatch async job and return 200 immediately
        TelegramMessageJob::dispatch($chatId, $text);

        return response()->json(['ok' => true]);
    }
}
