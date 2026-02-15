<?php

namespace Database\Seeders;

use App\Models\Parameter;
use Illuminate\Database\Seeder;

class ParameterSeeder extends Seeder
{
    public function run(): void
    {
        Parameter::truncate();

        $keys = [
            // LLM Configuration
            ['key' => 'LLM_PROVIDER', 'value' => ''],
            ['key' => 'MODEL', 'value' => ''],
            ['key' => 'LLM_TEMPERATURE', 'value' => '0.2'],
            ['key' => 'SYSTEM_PROMPT', 'value' => "You are Barney, a personal finance assistant. You help the user track expenses, incomes, account balances, and transfers.\n\nRules:\n- ALWAYS call get_preferences before any finance action to respect user rules and defaults.\n- Use list_accounts to look up account IDs before logging expenses, incomes, or transfers.\n- NEVER delete records directly. Always use request_delete, show details to the user, and only call confirm_delete after explicit confirmation.\n- Keep responses short and concise.\n- When logging expenses or incomes, confirm the details back to the user.\n- Use get_summary for overview questions.\n- Respect user preferences for default accounts and categorization rules."],
            ['key' => 'OPENAI_KEY', 'value' => ''],
            ['key' => 'OPEN_ROUTER_KEY', 'value' => ''],
            ['key' => 'OLLAMA_KEY', 'value' => ''],
            ['key' => 'ANTHROPIC_KEY', 'value' => ''],

            // Telegram Bot Configuration
            ['key' => 'TELEGRAM_BOT_TOKEN', 'value' => ''],
            ['key' => 'TELEGRAM_BOT_USERNAME', 'value' => ''],
            ['key' => 'TELEGRAM_WEBHOOK_URL', 'value' => ''],
            ['key' => 'TELEGRAM_ADMIN_CHAT_ID', 'value' => ''],
        ];

        foreach ($keys as $param) {
            Parameter::create([
                'key' => $param['key'],
                'value' => $param['value'],
            ]);
        }
    }
}
