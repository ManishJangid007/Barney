<?php

namespace App\Models;

use App\Enums\ChatRole;
use Illuminate\Database\Eloquent\Model;

class ChatHistory extends Model
{
    protected $fillable = [
        'session_id',
        'role',
        'content',
    ];

    protected function casts(): array
    {
        return [
            'role' => ChatRole::class,
        ];
    }
}
