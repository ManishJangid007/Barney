<?php

namespace App\Enums;

enum ExpenseCategory: string
{
    case Food = 'food';
    case Groceries = 'groceries';
    case Clothes = 'clothes';
    case Travel = 'travel';
    case Rent = 'rent';
    case Utilities = 'utilities';
    case Entertainment = 'entertainment';
    case Health = 'health';
    case Education = 'education';
    case Subscriptions = 'subscriptions';
    case Transport = 'transport';
    case Emi = 'emi';
    case Other = 'other';
}
