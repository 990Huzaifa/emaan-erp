<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalVoucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'voucher_code',
        'partner_id',
        'acc_id',
        'voucher_amount',
        'cheque_no',
        'cheque_date',
        'payment_method',
        'type',
        'bank_transaction_type',
        'description',
        'business_id',
        'voucher_date',
        'approve_date',
        'created_by',
        'approved_by',
        'status'
    ];
}
