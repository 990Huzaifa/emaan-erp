<?php

namespace App\Services;

use App\Models\OpeningBalance;
use App\Models\PurchaseVoucher;
use App\Models\PurchaseReturnVoucher;
use App\Models\SaleVoucher;
use App\Models\SaleReturnVoucher;

use App\Models\Transaction;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VoucherUpdateService
{

    public function updateVoucherFlow(int $voucherId, $type,array $data)
    {
        
        try {
            DB::beginTransaction();

            // 🔹 Load Voucher
            $voucher = $this->loadVoucher($voucherId, $type);

            if (!$voucher) {
                throw new Exception("Voucher not found");
            }

            // 🔹 New values
            $newAmount = $data['voucher_amount'];
            $newDate   = $data['voucher_date'];
            $oldDate   = $voucher->voucher_date;

            $recalculateFrom = min($oldDate, $newDate);

            // 🔹 Get transactions (NO PAIRING NOW 🎯)
            $transactions = $this->findVoucherTransactions($voucher, $type);

            $trx1 = $transactions[0];
            $trx2 = $transactions[1];

            // 🔹 Accounts
            $cashAccId = $voucher->acc_id;

            if (in_array($type, [0, 2])) {
                $partyAccId = $voucher->vendor->acc_id;
            } else {
                $partyAccId = $voucher->customer->acc_id;
            }

            // 🔹 Build mapping
            $entries = [];

            switch ($type) {

                case 0: // PURCHASE
                    $entries = [
                        $partyAccId => ['debit' => $newAmount, 'credit' => 0],
                        $cashAccId => ['debit' => 0, 'credit' => $newAmount],
                    ];
                    break;

                case 1: // SALE
                    $entries = [
                        $cashAccId => ['debit' => $newAmount, 'credit' => 0],
                        $partyAccId => ['debit' => 0, 'credit' => $newAmount],
                    ];
                    break;

                case 2: // PURCHASE RETURN
                    $entries = [
                        $partyAccId => ['debit' => 0, 'credit' => $newAmount],
                        $cashAccId => ['debit' => $newAmount, 'credit' => 0],
                    ];
                    break;

                case 3: // SALE RETURN
                    $entries = [
                        $cashAccId => ['debit' => 0, 'credit' => $newAmount],
                        $partyAccId => ['debit' => $newAmount, 'credit' => 0],
                    ];
                    break;
            }

            // 🔹 Update both transactions
            foreach ($transactions as $trx) {

                if (!isset($entries[$trx->acc_id])) {
                    Log::error("Transaction account {$trx->acc_id} does not match expected accounts for voucher {$voucher->id}");
                    throw new Exception("Transaction account mismatch.");
                }

                // $entry = $entries[$trx->acc_id];

                // $trx->debit  = $entry['debit'];
                // $trx->credit = $entry['credit'];
                // $trx->created_at = $newDate;

                // $trx->save();

                Transaction::where('id', $trx->id)
                    ->update([
                        'debit' => $entries[$trx->acc_id]['debit'],
                        'credit' => $entries[$trx->acc_id]['credit'],
                        'created_at' => $newDate,
                    ]);

                    
            }

            // 🔹 Recalculate ledger
            $startId = min($trx1->id, $trx2->id);

            // recalculateAccountTransactions($cashAccId);
            $this->recalculateAccountTransactionsFromDate($cashAccId, $recalculateFrom);
                
            if ($cashAccId != $partyAccId) {
                    // recalculateAccountTransactions($partyAccId);
                $this->recalculateAccountTransactionsFromDate($partyAccId, $recalculateFrom);
                $this->applyVoucherUpdates($voucher, $type, $data);
            }

            DB::commit();
            return ['success' => true, 'message' => 'Voucher and transactions updated successfully.'];

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function loadVoucher(int $voucherId, int $type)
    {   
        $voucher = null;
        switch ($type) {
                case 0: $voucher = PurchaseVoucher::with('vendor')->find($voucherId); break;
                case 1: $voucher = SaleVoucher::with('customer')->find($voucherId); break;
                case 2: $voucher = PurchaseReturnVoucher::with('vendor')->find($voucherId); break;
                case 3: $voucher = SaleReturnVoucher::with('customer')->find($voucherId); break;
                default: throw new Exception("Invalid type");
            }

        return $voucher;
        
    }

    private function getVoucherRelations(int $type): array
    {
        return in_array($type, [0, 2]) ? ['vendor'] : ['customer'];
    }

    private function extractOldVoucherState($voucher, int $type): array
    {
        $partyAccId = in_array($type, [0, 2])
            ? $voucher->vendor->acc_id
            : $voucher->customer->acc_id;

        return [
            'amount' => (float) $voucher->voucher_amount,
            'date' => $voucher->voucher_date,
            'cash_acc_id' => (int) $voucher->acc_id,
            'party_acc_id' => (int) $partyAccId,
        ];
    }

    private function applyVoucherUpdates($voucher, int $type, array $data): void
    {
        switch ($type) {
            case 0: // purchase
            case 2: // purchase return
                $voucher->vendor_id = $data['vendor_id'] ?? $voucher->vendor_id;
                break;

            case 1: // sale
            case 3: // sale return
                $voucher->customer_id = $data['customer_id'] ?? $voucher->customer_id;
                break;
        }

        $voucher->acc_id = $data['acc_id'] ?? $voucher->acc_id;
        $voucher->voucher_amount = $data['voucher_amount'] ?? $voucher->voucher_amount;
        $voucher->voucher_date = $data['voucher_date'] ?? $voucher->voucher_date;

        if (array_key_exists('description', $data)) {
            $voucher->description = $data['description'];
        }

        $voucher->save();
    }

    private function extractNewVoucherState($voucher, int $type): array
    {
        $partyAccId = in_array($type, [0, 2])
            ? $voucher->vendor->acc_id
            : $voucher->customer->acc_id;

        return [
            'amount' => (float) $voucher->voucher_amount,
            'date' => $voucher->voucher_date,
            'cash_acc_id' => (int) $voucher->acc_id,
            'party_acc_id' => (int) $partyAccId,
        ];
    }

    private function findVoucherTransactions($voucher, $type)
    {
        $amount = $voucher->getOriginal('voucher_amount');
        $date   = $voucher->getOriginal('voucher_date');

        // 🔹 Cash/Bank account (direct)
        $cashAccId = $voucher->acc_id;

        // 🔹 Customer/Vendor account
        $partyAccId = null;

        if (in_array($type, [0, 2])) { // purchase / purchase return
            $partyAccId = $voucher->vendor->acc_id;
        } else { // sale / sale return
            $partyAccId = $voucher->customer->acc_id;
        }
        Log::info("Query Data: type={$type}, amount={$amount}, date={$date}, cashAccId={$cashAccId}, partyAccId={$partyAccId}");
        // 🔹 Find both transactions
        $transactions = Transaction::where('transaction_type', $type)
            ->Where('created_at', $date)
            ->where(function ($q) use ($amount) {
                $q->where('debit', $amount)
                ->orWhere('credit', $amount);
            })
            ->whereIn('acc_id', [$cashAccId, $partyAccId])
            ->lockForUpdate()
            ->get();
                Log::info("Found transactions for voucher {$voucher->id}: " . json_encode($transactions));
        if ($transactions->count() !== 2) {
            throw new Exception('Exact voucher transactions not found.');
        }

        return $transactions;
    }

    function recalculateAccountTransactionsFromDate($acc_id, $fromDate)
    {
        // 🔹 Step 1: Get last transaction BEFORE given date
        $previousTransaction = Transaction::where('acc_id', $acc_id)
            ->where('created_at', '<', $fromDate)
            ->orderBy('created_at', 'desc')
            ->first();
        Log::info("Previous transaction for acc_id {$acc_id} before {$fromDate}: " . ($previousTransaction ? $previousTransaction->id : 'None' . " with balance " . ($previousTransaction ? $previousTransaction->current_balance : 'N/A')));
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