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
        'tax'
    ];

    public function items()
    {
        return $this->hasMany(SaleOrderItem::class, 'sale_order_id');
    }
}
