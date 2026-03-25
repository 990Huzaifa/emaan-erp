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
        'total',
        'status',
        'note',
        'user_id',
        'business_id',
        'total_tax',
        'special',
        'delivery_cost',
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
        return $this->hasOne(deliveryNote::class, 'sale_order_id');
    }
}
