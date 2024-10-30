<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'acc_id',
        'transaction_type',
        'description',
        'debit',
        'credit',
    ];
}
