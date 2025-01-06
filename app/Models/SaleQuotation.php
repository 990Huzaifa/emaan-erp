<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleQuotation extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
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
        return $this->hasMany(SaleQuotationItem::class, 'sale_quotation_id');
    }
}
