<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalVoucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'voucher_code',
        'from_acc_id',
        'to_acc_id',
        'voucher_amount',
        'payment_method',
        'type',
        'description',
        'business_id',
        'voucher_date',
        'approve_date',
        'created_by',
        'approved_by',
        'status'
    ];
}
