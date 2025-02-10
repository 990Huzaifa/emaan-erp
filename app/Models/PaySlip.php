<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaySlip extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'employee_id',
        'slip_no',
        'status',
        'issue_date',
        'pay_period_start',
        'pay_period_end',
        'basic_pay',
        'loan_id',
        'loan_deduction',
        'tax_deduction',
        'allowance',
        'bonus',
        'net_pay',
        'remaining_balance'
    ];
}
