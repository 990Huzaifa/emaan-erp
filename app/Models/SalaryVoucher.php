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
        'bank_transaction_type',
        'payment_method',
        'description',
        'cheque_no',
        'cheque_date',
        'voucher_date',
        'approve_date',
        'created_by',
        'approved_by',
    ];
}
