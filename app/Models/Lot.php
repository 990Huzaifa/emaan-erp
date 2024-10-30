<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lot extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'vendor_id',
        'lot_code',
        'quantity',
        'status',
        'purchase_unit_price',
        'sale_unit_price',
        'total_price',
    ];
}
