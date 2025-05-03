<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_no',
        'invoice_date',
        'business_id',
        'grn_id',
        'vendor_id',
        'po_no',
        'terms_of_payment',
        'remarks',
        'delivery_cost',
        'total_tax',
        'total_discount',
        'total',
        'status',
    ];

    public function items()
    {
        return $this->hasMany(PurchaseInvoiceItem::class, 'purchase_invoice_id');
    }
}
