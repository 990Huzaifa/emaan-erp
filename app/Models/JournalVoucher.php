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
        'status',
        'cheque_no',
        'cheque_date',
        'payment_method',
        'TYPE',
        'business_id',
        'voucher_date'
    ];
}
