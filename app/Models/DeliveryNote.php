<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_order_id',
        'business_id',
        'dn_code',
        'dn_date',
        'received_by',
        'remarks',
        'status',
    ];

    public function items()
    {
        return $this->hasMany(DeliveryNoteItem::class, 'delivery_note_id');
    }

    public function sale_order()
    {
        return $this->belongsTo(SaleOrder::class, 'sale_order_id');
    }
}
