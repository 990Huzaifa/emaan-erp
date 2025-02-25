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
        'link',
        'debit',
        'credit',
        'current_balance',
    ];
    public function chartOfAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'acc_id');
    }
}
