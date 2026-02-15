<?php

namespace App\Observers;

use App\Models\Income;

class IncomeObserver
{
    public function created(Income $income): void
    {
        $income->account()->increment('balance', $income->amount);
    }

    public function deleted(Income $income): void
    {
        $income->account()->decrement('balance', $income->amount);
    }
}
