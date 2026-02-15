<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Alert;
use App\Models\Expense;
use App\Models\Income;
use App\Services\Llm\LlmService;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckAlerts extends Command
{
    protected $signature = 'barney:check-alerts';

    protected $description = 'Analyze active alerts using LLM and notify via Telegram if needed';

    public function handle(): int
    {
        $alerts = Alert::where('is_active', true)->get();

        if ($alerts->isEmpty()) {
            $this->info('No active alerts.');

            return self::SUCCESS;
        }

        $adminChatId = config('constants.TELEGRAM_ADMIN_CHAT_ID', '');
        if (empty($adminChatId)) {
            $this->error('TELEGRAM_ADMIN_CHAT_ID not configured.');

            return self::FAILURE;
        }

        // Build financial snapshot
        $snapshot = $this->buildFinancialSnapshot();

        // Build alert rules
        $alertRules = $alerts->map(fn ($a) => [
            'id' => $a->id,
            'type' => $a->type->value,
            'description' => $a->description,
            'category' => $a->category,
            'threshold' => $a->threshold,
            'period' => $a->period?->value,
            'account_id' => $a->account_id,
            'last_triggered' => $a->last_triggered_at?->toDateTimeString(),
        ])->toArray();

        // Build prompt for LLM
        $prompt = $this->buildAnalysisPrompt($snapshot, $alertRules);

        $this->info('Analyzing ' . $alerts->count() . ' active alerts...');

        try {
            $llm = new LlmService;
            // Use a dedicated session for alert checks
            $response = $llm->chat($prompt, 'barney-alert-checker');

            // Check if LLM returned EMPTY (nothing to report)
            $trimmed = trim(strtoupper($response));
            if ($trimmed === 'EMPTY' || $trimmed === '**EMPTY**' || str_contains($trimmed, 'NO ALERTS') || str_contains($trimmed, 'NOTHING TO REPORT')) {
                $this->info('No alerts triggered.');

                return self::SUCCESS;
            }

            // Send notification via Telegram
            $telegram = new TelegramService;
            $telegram->sendMessage($adminChatId, $response);

            // Update last_triggered_at for all active alerts
            Alert::where('is_active', true)->update(['last_triggered_at' => Carbon::now()]);

            $this->info('Alert notification sent.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('CheckAlerts failed', ['error' => $e->getMessage()]);
            $this->error('Failed: ' . $e->getMessage());

            return self::FAILURE;
        }
    }

    protected function buildFinancialSnapshot(): array
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfWeek = $now->copy()->startOfWeek();
        $today = $now->copy()->startOfDay();

        // Account balances
        $accounts = Account::all()->map(fn ($a) => [
            'id' => $a->id,
            'name' => $a->name,
            'type' => $a->type,
            'balance' => (float) $a->balance,
        ])->toArray();

        // This month's expenses by category
        $monthlyExpenses = Expense::where('expense_date', '>=', $startOfMonth)
            ->selectRaw('category, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('category')
            ->get()
            ->toArray();

        // Today's total spending
        $todaySpending = (float) Expense::where('expense_date', '>=', $today)->sum('amount');

        // This week's total spending
        $weeklySpending = (float) Expense::where('expense_date', '>=', $startOfWeek)->sum('amount');

        // This month's total spending
        $monthlySpending = (float) Expense::where('expense_date', '>=', $startOfMonth)->sum('amount');

        // Average daily spending (last 30 days)
        $thirtyDaysAgo = $now->copy()->subDays(30);
        $last30DaysTotal = (float) Expense::where('expense_date', '>=', $thirtyDaysAgo)->sum('amount');
        $avgDailySpending = round($last30DaysTotal / 30, 2);

        // This month's income
        $monthlyIncome = (float) Income::where('income_date', '>=', $startOfMonth)->sum('amount');

        // Income sources this month
        $incomeSources = Income::where('income_date', '>=', $startOfMonth)
            ->selectRaw('source, SUM(amount) as total')
            ->groupBy('source')
            ->get()
            ->toArray();

        return [
            'current_date' => $now->format('Y-m-d H:i'),
            'day_of_month' => $now->day,
            'accounts' => $accounts,
            'total_balance' => array_sum(array_column($accounts, 'balance')),
            'today_spending' => $todaySpending,
            'weekly_spending' => $weeklySpending,
            'monthly_spending' => $monthlySpending,
            'monthly_expenses_by_category' => $monthlyExpenses,
            'avg_daily_spending_30d' => $avgDailySpending,
            'monthly_income' => $monthlyIncome,
            'income_sources_this_month' => $incomeSources,
        ];
    }

    protected function buildAnalysisPrompt(array $snapshot, array $alertRules): string
    {
        $snapshotJson = json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $alertsJson = json_encode($alertRules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are a financial alert analyzer. Your job is to check alert rules against financial data and decide what needs the user's attention RIGHT NOW.

IMPORTANT RULES:
- If NOTHING needs attention, respond with exactly: EMPTY
- Do NOT spam the user. Only alert for genuinely important things.
- Do NOT repeat alerts that were triggered recently (check last_triggered field).
- For budget_limit: alert at 80% threshold (warning) and 100% (exceeded). Don't alert if already triggered today.
- For low_balance: only alert if balance is CURRENTLY below threshold.
- For spending_spike: only alert if today's spending is 2x+ the 30-day daily average.
- For reminder: check if the reminder is relevant for today based on its description.
- For income_expected: check if the described income is missing based on the day of month and income data.
- For daily_digest: always include a brief summary if this type is active.
- Keep alerts SHORT and actionable. One message, not separate ones.
- Use ₹ for currency amounts.

FINANCIAL SNAPSHOT:
{$snapshotJson}

ACTIVE ALERT RULES:
{$alertsJson}

Analyze the data against each alert rule. If any alerts should fire, write a single concise Telegram message covering all triggered alerts. If nothing needs attention, respond with: EMPTY
PROMPT;
    }
}
