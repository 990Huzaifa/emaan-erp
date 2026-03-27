<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'order_code',
        'order_date',
        'due_date',
        'status',
        'note',
        'user_id',
        'business_id',
        'special',
        'delivery_cost',
        'total',
        'total_tax',
        'total_discount',
        'remarks',
        'terms_of_payment'
    ];

    public function items()
    {
        return $this->hasMany(SaleOrderItem::class, 'sale_order_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }


    public function deliveryNote()
    {
        return $this->hasOne(DeliveryNote::class, 'sale_order_id');
    }
}
