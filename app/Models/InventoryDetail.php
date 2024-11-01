<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'lot_id',
        'product_id',
        'unit_price',
        'stock',
        'in_stock',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
