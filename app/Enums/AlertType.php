<?php

namespace App\Enums;

enum AlertType: string
{
    case BudgetLimit = 'budget_limit';
    case LowBalance = 'low_balance';
    case SpendingSpike = 'spending_spike';
    case Reminder = 'reminder';
    case IncomeExpected = 'income_expected';
    case DailyDigest = 'daily_digest';
}
