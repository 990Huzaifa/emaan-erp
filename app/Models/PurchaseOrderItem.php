<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'measurement_unit',
        'quantity',
        'unit_price',
        'discount_in_percentage',
        'discount',
        'total_price',
        'total_tax',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
