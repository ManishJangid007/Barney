<?php

namespace App\Observers;

use App\Models\Expense;

class ExpenseObserver
{
    public function created(Expense $expense): void
    {
        $expense->account()->decrement('balance', $expense->amount);
    }

    public function deleted(Expense $expense): void
    {
        $expense->account()->increment('balance', $expense->amount);
    }
}
