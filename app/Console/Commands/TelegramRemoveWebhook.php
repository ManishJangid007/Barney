<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Illuminate\Console\Command;

class TelegramRemoveWebhook extends Command
{
    protected $signature = 'telegram:remove-webhook';

    protected $description = 'Remove the Telegram webhook registration';

    public function handle(): int
    {
        $telegram = new TelegramService;
        $result = $telegram->removeWebhook();

        if ($result['ok'] ?? false) {
            $this->info('Webhook removed successfully.');

            return self::SUCCESS;
        }

        $this->error('Failed: ' . ($result['description'] ?? 'Unknown error'));

        return self::FAILURE;
    }
}
