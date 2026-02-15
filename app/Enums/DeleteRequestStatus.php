<?php

namespace App\Enums;

enum DeleteRequestStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Done = 'done';
}
