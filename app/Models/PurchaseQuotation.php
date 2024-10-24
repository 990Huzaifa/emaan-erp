<?php

namespace App\Models;

use App\Models\PurchaseQuotationItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseQuotation extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'business_id',
        'quotation_code',
        'order_date',
        'due_date',
        'terms_of_payment',
        'remarks',
        'reference',
        'status',
    ];

    public function items()
    {
        return $this->hasMany(PurchaseQuotationItem::class, 'purchase_quotation_id');
    }
}
