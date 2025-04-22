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
        'measurement_unit',
        'quantity',
        'delivered',
        'charged',
        'unit_price',
        'total_price',
        'discount',
        'discount_in_percentage',
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

    public function deliveryNote()
    {
        return $this->belongsTo(DeliveryNote::class, 'delivery_note_id');
    }
}
