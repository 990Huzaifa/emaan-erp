<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoodsReceiveNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'business_id',
        'grn_code',
        'grn_date',
        'received_by',
        'terms_of_payment',
        'delivery_cost',
        'total_tax',
        'total_discount',
        'total',
        'remarks',
        'status',
    ];

    public function items()
    {
        return $this->hasMany(GoodsReceiveNoteItem::class, 'goods_receive_note_id');
    }

    public function purchase_order()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }
}
