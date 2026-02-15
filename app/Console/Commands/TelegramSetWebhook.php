<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramSetWebhook extends Command
{
    protected $signature = 'telegram:set-webhook';

    protected $description = 'Register the Telegram webhook URL';

    public function handle(): int
    {
        $url = config('constants.TELEGRAM_WEBHOOK_URL', '');

        if (empty($url)) {
            $this->error('TELEGRAM_WEBHOOK_URL is not set in the parameters table.');

            return self::FAILURE;
        }

        $token = config('constants.TELEGRAM_BOT_TOKEN', '');
        if (empty($token)) {
            $this->error('TELEGRAM_BOT_TOKEN is not set in the parameters table.');

            return self::FAILURE;
        }

        $this->info("Setting webhook to: {$url}");

        $telegram = new TelegramService;
        $result = $telegram->setWebhook($url);

        if ($result['ok'] ?? false) {
            $this->info('Webhook registered successfully.');

            return self::SUCCESS;
        }

        $this->error('Failed: ' . ($result['description'] ?? 'Unknown error'));

        return self::FAILURE;
    }
}
