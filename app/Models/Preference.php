<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Preference extends Model
{
    public const MAX_ROWS = 10;

    protected $fillable = [
        'key',
        'instruction',
    ];
}
