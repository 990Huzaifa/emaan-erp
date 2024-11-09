<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseVoucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'grn_id',
        'acc_id',
        'business_id',
        'voucher_code',
        'voucher_amount',
        'status',
        'voucher_date',
    ];
}
