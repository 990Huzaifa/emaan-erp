<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleReturn extends Model
{
    use HasFactory;

    
    protected $fillable = [
        'dn_id',
        'sale_order_id',
        'customer_id',
        'product_id',
        'business_id',
        'sr_code',
        'received_date',
        'received_by',
        'reason',
        'status',
    ];

    public function items()
    {
        return $this->hasMany(SaleReturnItem::class, 'sale_return_id');
    }
}
