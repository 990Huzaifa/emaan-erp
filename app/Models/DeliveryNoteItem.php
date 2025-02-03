<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryNoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_note_id',
        'product_id',
        'lot_id',
        'quantity',
        'delivered',
        'charged',
        'unit_price',
        'total_price',
        'tax'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
    public function lot()
    {
        return $this->belongsTo(Lot::class, 'lot_id');
    }
}
