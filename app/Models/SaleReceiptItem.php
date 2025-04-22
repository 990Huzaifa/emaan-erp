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
        'tax',
        'total',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
