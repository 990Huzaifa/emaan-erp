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
        'lot_id',
        'quantity'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function lot()
    {
        return $this->belongsTo(Lot::class, 'lot_id');
    }
}
