<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoodsReceiveNoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'goods_receive_note_id',
        'product_id',
        'quantity',
        'receive',
        'billed',
        'purchase_unit_price',
        'sale_unit_price',
        'total_price',
        'tax',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
