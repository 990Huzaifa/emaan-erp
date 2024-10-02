<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'v_code',
        'email',
        'phone',
        'address',
        'city_id',
        'phone',
        'telephone',
        'website',
        'avatar',
        'opening_balance',
    ];
}
