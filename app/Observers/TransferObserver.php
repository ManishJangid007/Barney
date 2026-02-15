<?php

namespace App\Observers;

use App\Models\Transfer;

class TransferObserver
{
    public function created(Transfer $transfer): void
    {
        $transfer->fromAccount()->decrement('balance', $transfer->amount);
        $transfer->toAccount()->increment('balance', $transfer->amount);
    }

    public function deleted(Transfer $transfer): void
    {
        $transfer->fromAccount()->increment('balance', $transfer->amount);
        $transfer->toAccount()->decrement('balance', $transfer->amount);
    }
}
