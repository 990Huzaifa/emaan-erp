<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseVoucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'acc_id',
        'business_id',
        'voucher_code',
        'voucher_amount',
        'status',
        'payment_method',
        'bank_transaction_type',
        'description',
        'cheque_no',
        'cheque_date',
        'voucher_date',
        'approve_date',
        'created_by',
        'approved_by',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }
}
