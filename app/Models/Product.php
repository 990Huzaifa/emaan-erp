<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'p_code',
        'title',
        'description',
        'sku',
        'image',
        'sku',
        'category_id',
        'sub_category_id',
        'business_id',
        'added_by',
        'acc_id',
        'brand_name',
        'terms_of_payment',
        'measurement_unit_id',
        'sales_tax_rate',
        'purchase_price',
        'sale_price',
        'opening_balance',
        'is_active'
    ];

    public function measurementUnit()
    {
        return $this->belongsTo(MeasurementUnit::class, 'measurement_unit_id', 'id');
    }
}
