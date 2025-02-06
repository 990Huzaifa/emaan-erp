<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoanVoucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'voucher_code',
        'employee_id',
        'acc_id',
        'voucher_amount',
        'cheque_no',
        'cheque_date',
        'payment_method',
        'business_id',
        'voucher_date',
        'status'
    ];
}
