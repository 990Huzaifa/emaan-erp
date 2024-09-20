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
    ];
}
