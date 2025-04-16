<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_no',
        'receipt_date',
        'business_id',
        'dn_id',
        'customer_id',
        'so_no',
        'terms_of_payment',
        'total',
        'status',
    ];

    public function items()
    {
        return $this->hasMany(SaleReceiptItem::class, 'sale_receipt_id');
    }
}
