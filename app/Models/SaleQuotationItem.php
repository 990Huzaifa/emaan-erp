<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleQuotationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_quotation_id',
        'product_id',
        'quantity'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

}
