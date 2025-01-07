<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_order_id',
        'product_id',
        'lot_id',
        'quantity',
        'unit_price',
        'total_price',
        'total_tax',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
