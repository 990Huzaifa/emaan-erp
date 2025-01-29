<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryVoucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'pay_slip_id',
        'acc_id',
        'business_id',
        'voucher_code',
        'voucher_amount',
        'status',
        'voucher_date',
        'payment_method',
        'cheque_no',
        'cheque_date'
    ];
}
