<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'acc_id',
        'ref_id',
        'business_id',
        'code',
        'type',
        'amount',
        'status',
        'description',
        'approved_by',
    ];
}
