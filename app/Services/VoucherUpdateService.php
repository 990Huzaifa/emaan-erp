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

class VoucherUpdateService
{

    public function updateVoucherFlow(int $voucherId, $type,array $data)
    {
        DB::beginTransaction();

        try {

            // 🔹 Load Voucher
            switch ($type) {
                case 0: $voucher = PurchaseVoucher::with('vendor')->find($voucherId); break;
                case 1: $voucher = SaleVoucher::with('customer')->find($voucherId); break;
                case 2: $voucher = PurchaseReturnVoucher::with('vendor')->find($voucherId); break;
                case 3: $voucher = SaleReturnVoucher::with('customer')->find($voucherId); break;
                default: throw new Exception("Invalid type");
            }

            if (!$voucher) {
                throw new Exception("Voucher not found");
            }

            // 🔹 New values
            $newAmount = $voucher->voucher_amount;
            $newDate   = $voucher->voucher_date;

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
                    throw new Exception("Transaction account mismatch.");
                }

                $entry = $entries[$trx->acc_id];

                $trx->debit  = $entry['debit'];
                $trx->credit = $entry['credit'];
                $trx->created_at = $newDate;

                $trx->save();
            }

            // 🔹 Recalculate ledger
            $startId = min($trx1->id, $trx2->id);

            $this->recalculateLedger($cashAccId, $startId);

            if ($cashAccId != $partyAccId) {
                $this->recalculateLedger($partyAccId, $startId);
            }

            DB::commit();

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

        // 🔹 Find both transactions
        $transactions = Transaction::where('transaction_type', $type)
            ->whereDate('created_at', $date)
            ->where(function ($q) use ($amount) {
                $q->where('debit', $amount)
                ->orWhere('credit', $amount);
            })
            ->whereIn('acc_id', [$cashAccId, $partyAccId])
            ->lockForUpdate()
            ->get();

        if ($transactions->count() !== 2) {
            throw new Exception('Exact voucher transactions not found.');
        }

        return $transactions;
    }

    private function recalculateLedger(int $accId, int $fromTransactionId): void
    {
        $currentTransaction = Transaction::find($fromTransactionId);
        if (!$currentTransaction) return;

        $createdAt = $currentTransaction->created_at;

        // ✅ Get correct previous transaction (DATE + ID SAFE)
        $previousTransaction = Transaction::where('acc_id', $accId)
            ->where(function ($q) use ($createdAt, $fromTransactionId) {
                $q->where('created_at', '<', $createdAt)
                ->orWhere(function ($q2) use ($createdAt, $fromTransactionId) {
                    $q2->where('created_at', $createdAt)
                        ->where('id', '<', $fromTransactionId);
                });
            })
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        $openingBalance = OpeningBalance::where('acc_id', $accId)->value('amount') ?? 0;

        $runningBalance = $previousTransaction
            ? (float)$previousTransaction->current_balance
            : (float)$openingBalance;

        // ✅ Get all affected transactions (DATE BASED)
        $transactions = Transaction::where('acc_id', $accId)
            ->where(function ($q) use ($createdAt, $fromTransactionId) {
                $q->where('created_at', '>', $createdAt)
                ->orWhere(function ($q2) use ($createdAt, $fromTransactionId) {
                    $q2->where('created_at', $createdAt)
                        ->where('id', '>=', $fromTransactionId);
                });
            })
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($transactions as $trx) {

            // ✅ YOUR SYSTEM (CREDIT BASED)
            $runningBalance = $runningBalance 
                - (float)$trx->debit 
                + (float)$trx->credit;

            $trx->current_balance = $runningBalance;
            $trx->save();
        }
    }
}