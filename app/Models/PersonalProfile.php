<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalProfile extends Model
{
    protected $fillable = [
        'first_name',
        'last_name',
        'date_of_birth',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'country',
        'pin_code',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }
}
