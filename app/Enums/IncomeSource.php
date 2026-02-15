<?php

namespace App\Enums;

enum IncomeSource: string
{
    case Salary = 'salary';
    case Freelance = 'freelance';
    case Refund = 'refund';
    case Interest = 'interest';
    case Gift = 'gift';
    case Other = 'other';
}
