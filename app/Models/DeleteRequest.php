<?php

namespace App\Models;

use App\Enums\DeleteRequestStatus;
use Illuminate\Database\Eloquent\Model;

class DeleteRequest extends Model
{
    protected $fillable = [
        'table_name',
        'record_id',
        'reason',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeleteRequestStatus::class,
        ];
    }
}
