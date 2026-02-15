<?php

namespace App\Models;

use App\Enums\IncomeSource;
use App\Observers\IncomeObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(IncomeObserver::class)]
class Income extends Model
{
    protected $fillable = [
        'account_id',
        'source',
        'amount',
        'description',
        'income_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'source' => IncomeSource::class,
            'amount' => 'decimal:2',
            'income_date' => 'date',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
