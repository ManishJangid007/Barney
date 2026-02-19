# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Barney is a Telegram-powered personal finance AI bot built with Laravel 12 and the Model Context Protocol (MCP). Users manage finances through natural language via Telegram. An LLM backend (OpenAI, Anthropic, OpenRouter, or Ollama) processes messages and invokes MCP tools to perform financial operations.

## Common Commands

```bash
# Full setup (install deps, generate key, migrate, build frontend)
composer setup

# Run all dev services concurrently (server, queue worker, logs, vite)
composer dev

# Run tests (clears config cache first)
composer test
# or: php artisan test

# Lint with Laravel Pint
./vendor/bin/pint

# Run a single test file
php artisan test --filter=ExampleTest

# Database migrations
php artisan migrate

# Seed parameters table (LLM config, Telegram tokens, etc.)
php artisan db:seed --class=ParameterSeeder

# Telegram webhook management
php artisan telegram:set-webhook
php artisan telegram:remove-webhook

# Run alert checks manually
php artisan barney:check-alerts

# Production: start queue worker + scheduler via PM2
pm2 start laravel-worker.yaml
```

## Architecture

### Request Flow

1. Telegram sends webhook POST to `/telegram/webhook` → `TelegramController`
2. Controller dispatches `TelegramMessageJob` to queue, returns 200 immediately
3. Queue worker picks up job → calls `LlmService::chat()`
4. LlmService enters a tool-calling loop (max 15 iterations): sends messages + tool definitions to LLM provider, executes any tool calls via `ToolRunner`, appends results, repeats until LLM returns a final text response
5. Response sent back to user via `TelegramService`

### Key Subsystems

**LLM Service** (`app/Services/Llm/`): Provider-agnostic LLM orchestration. All providers implement `ProviderInterface` with normalized OpenAI-format I/O. The Anthropic provider handles format conversion internally. Provider/model selection is configured via the `parameters` database table, not .env.

**MCP Tools** (`app/Mcp/Tools/`): 18 tools registered in `BarneyServer`. Each tool extends `Laravel\Mcp\Server\Tool` with a `handle()` method and `schema()` for input validation. Tools cover: expenses, incomes, accounts, transfers, alerts, profile, preferences, and a two-step deletion safety workflow (request_delete → confirm_delete).

**Parameter-Based Config** (`app/Providers/AppServiceProvider.php`): The `parameters` table stores runtime config (LLM_PROVIDER, MODEL, API keys, TELEGRAM_BOT_TOKEN, SYSTEM_PROMPT, etc.). All rows are loaded into Laravel's config as `config('constants.KEY_NAME')` at boot. This allows dynamic configuration without .env changes.

**Alert System** (`app/Console/Commands/CheckAlerts.php`): Scheduled command that builds a financial snapshot, sends it to the LLM with alert rules for analysis, and delivers triggered alerts via Telegram. Tracks `last_triggered_at` to prevent alert spam.

### Data Model

- **Expenses/Incomes**: Linked to accounts. `ExpenseObserver` auto-deducts from account balance on create/update; income observer auto-adds.
- **Transfers**: Between two accounts (from_account_id → to_account_id).
- **ChatHistories**: Session-based conversation storage. Last 10 messages loaded per request for context.
- **Preferences**: User-defined rules/instructions included in the LLM system prompt.
- **DeleteRequests**: Safety wall — deletions require a two-step confirm flow.

### Enums

Located in `app/Enums/`. Key enums: `ExpenseCategory` (food, groceries, rent, etc.), `PaymentMethod` (cash, bank, upi, card), `AccountType` (bank, cash, wallet), `AlertType`, `AlertPeriod`, `IncomeSource`.

### Routes

- `routes/telegram.php` — Webhook endpoint
- `routes/ai.php` — MCP HTTP + stdio endpoints (`/barney`)
- `routes/web.php` — Landing page

## Testing

PHPUnit with SQLite in-memory database. Test config in `phpunit.xml` sets `QUEUE_CONNECTION=sync` so jobs execute synchronously during tests.

## Single-User Design

Currently designed for a single admin user. The `TELEGRAM_ADMIN_CHAT_ID` parameter restricts access. All models are unscoped (no user_id foreign keys).
