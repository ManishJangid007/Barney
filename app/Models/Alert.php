<?php

namespace App\Models;

use App\Enums\AlertPeriod;
use App\Enums\AlertType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    protected $fillable = [
        'type',
        'category',
        'account_id',
        'threshold',
        'period',
        'description',
        'is_active',
        'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => AlertType::class,
            'period' => AlertPeriod::class,
            'threshold' => 'decimal:2',
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
