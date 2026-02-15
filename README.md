# Barney - Personal Finance AI Bot

Telegram bot powered by LLM with MCP tools for tracking expenses, incomes, accounts, and transfers.

## Requirements

- PHP 8.2+
- MySQL
- Composer
- PM2 (for queue worker)
- Telegram bot token (from @BotFather)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure `.env`:

```
DB_DATABASE=barney
DB_USERNAME=root
DB_PASSWORD=
QUEUE_CONNECTION=database
```

Run migrations and seed:

```bash
php artisan migrate
php artisan db:seed
```

## Parameters Table

Fill these in the `parameters` table after seeding:

| Key                      | Value                                                    |
| ------------------------ | -------------------------------------------------------- |
| `LLM_PROVIDER`           | `openai` / `openrouter` / `anthropic` / `ollama`         |
| `MODEL`                  | Model name (e.g. `gpt-4o`, `claude-sonnet-4-5-20250929`) |
| `OPENAI_KEY`             | OpenAI API key (if using openai)                         |
| `OPEN_ROUTER_KEY`        | OpenRouter API key (if using openrouter)                 |
| `ANTHROPIC_KEY`          | Anthropic API key (if using anthropic)                   |
| `OLLAMA_KEY`             | Leave empty for local Ollama                             |
| `TELEGRAM_BOT_TOKEN`     | Bot token from @BotFather                                |
| `TELEGRAM_BOT_USERNAME`  | Bot username without @                                   |
| `TELEGRAM_ADMIN_CHAT_ID` | Your chat ID (get from @userinfobot)                     |
| `TELEGRAM_WEBHOOK_URL`   | `https://your-domain.com/telegram/webhook`               |

## Start Services

```bash
# Web server
php artisan serve

# Queue worker
pm2 start laravel-worker.yaml

# Register webhook (run once)
php artisan telegram:set-webhook
```

## Webhook Commands

```bash
php artisan telegram:set-webhook      # Register webhook with Telegram
php artisan telegram:remove-webhook   # Remove webhook
```

## Queue Worker (PM2)

```bash
pm2 start laravel-worker.yaml    # Start
pm2 restart barney-worker        # Restart after code changes
sudo php artisan queue:restart   # Restart after code changes
pm2 logs barney-worker           # View logs
pm2 stop barney-worker           # Stop
```
