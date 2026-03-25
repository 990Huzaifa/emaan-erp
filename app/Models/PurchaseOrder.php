<?php

namespace App\Models;

use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'order_code',
        'order_date',
        'due_date',
        'total',
        'status',
        'note',
        'user_id',
        'business_id',
        'terms_of_payment',
        'remarks',
        'delivery_cost',
        'total_discount',
        'total_tax',
        'total',
        'pdf',
        'reference'
    ];

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function goodsReceiveNote()
    {
        return $this->hasOne(GoodsReceiveNote::class, 'purchase_order_id');
    }

    public function lots()
    {
        return $this->hasMany(Lot::class, 'po_id');
    }
}
