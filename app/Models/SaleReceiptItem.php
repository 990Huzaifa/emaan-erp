<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleReceiptItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_receipt_id',
        'product_id',
        'measurement_unit',
        'quantity',
        'unit_price',
        'discount',
        'discount_in_percentage',
        'tax',
        'total',
    ];

    public function receipt()
    {
        return $this->belongsTo(SaleReceipt::class, 'sale_receipt_id');
    }
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
