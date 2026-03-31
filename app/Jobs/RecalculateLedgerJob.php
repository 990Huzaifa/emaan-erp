<?php

namespace App\Jobs;

use App\Models\OpeningBalance;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecalculateLedgerJob implements ShouldQueue
{
    public $acc_id;
    public $from_date;

    public function __construct($acc_id, $from_date)
    {
        $this->acc_id = $acc_id;
        $this->from_date = $from_date;
    }

    public function handle()
    {
        $this->recalculateAccountTransactionsFromDate(
            $this->acc_id,
            $this->from_date
        );
    }

    function recalculateAccountTransactionsFromDate($acc_id, $fromDate)
    {
        // 🔹 Step 1: Get last transaction BEFORE given date
        $previousTransaction = Transaction::where('acc_id', $acc_id)
            ->where('created_at', '<', $fromDate)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        // 🔹 Step 2: Opening balance
        $openingBalance = OpeningBalance::where('acc_id', $acc_id)->value('amount') ?? 0;

        // 🔹 Step 3: Starting balance
        $runningBalance = $previousTransaction
            ? (float)$previousTransaction->current_balance
            : (float)$openingBalance;

        // 🔹 Step 4: Get transactions FROM given date
        $transactions = Transaction::where('acc_id', $acc_id)
            ->where('created_at', '>=', $fromDate)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $majorType = getAccountMajorType($acc_id);

        foreach ($transactions as $txn) {

            // ✅ SAME PERFECT LOGIC (no change)
            if ($txn->debit > 0) {
                if (in_array($majorType, ['ASSET', 'EXPENSE'])) {
                    $runningBalance += $txn->debit;
                } else {
                    $runningBalance -= $txn->debit;
                }
            }

            if ($txn->credit > 0) {
                if (in_array($majorType, ['LIABILITY', 'EQUITY', 'REVENUE'])) {
                    $runningBalance += $txn->credit;
                } else {
                    $runningBalance -= $txn->credit;
                }
            }

            $txn->update([
                'current_balance' => $runningBalance
            ]);
        }
    }
}
