<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReturn extends Model
{
    use HasFactory;

    protected $fillable = [
        'grn_id',
        'purchase_order_id',
        'vendor_id',
        'product_id',
        'business_id',
        'pr_code',
        'return_date',
        'return_by',
        'reason',
        'status',
    ];

    public function items()
    {
        return $this->hasMany(PurchaseReturnItem::class, 'purchase_return_id');
    }
}
