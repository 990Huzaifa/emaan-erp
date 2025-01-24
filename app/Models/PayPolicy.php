<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'policy_code',
        'basic_pay',
        'bonus_percentage',
        'allowance',
        'deductions',
        'loan_limit',
        'tax_rate',
        'status',
    ];
}
