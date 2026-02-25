<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpenseVoucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'expense_acc_id',
        'asset_acc_id',
        'voucher_code',
        'payment_method',
        'cheque_no',
        'cheque_date',
        'bank_transaction_type',
        'description',
        'voucher_amount',
        'voucher_date',
        'approve_date',
        'created_by',
        'approved_by',
        'status',
    ];
}
