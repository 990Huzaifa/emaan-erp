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
        'total_tax',
        'terms_of_payment',
        'remarks',
        'reference'
    ];

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class, 'purchase_order_id');
    }
}
