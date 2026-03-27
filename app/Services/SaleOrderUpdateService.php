<?php

namespace App\Services;

use App\Models\OpeningBalance;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\DeliveryNote;
use App\Models\DeliveryNoteItem;
use App\Models\SaleReceipt;
use App\Models\SaleReceiptItem;
use App\Models\Lot;
use App\Models\Transaction;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaleOrderUpdateService
{
    public function updateSaleFlow(int $soId, array $data, int $businessId)
    {
        return DB::transaction(function () use ($soId, $data, $businessId) {

            /*
            |--------------------------------------------------------------------------
            | STEP 1: GET so WITH RELATIONS
            |--------------------------------------------------------------------------
            */
            $so = SaleOrder::with([
                'items',
                'customer',
                'deliveryNote.items',
            ])
                ->lockForUpdate()
                ->find($soId);
            if (!$so) {
                throw new Exception('Sale Order not found.', 404);
            }

            $DN = $so->deliveryNote;

            $invoice = SaleReceipt::with('items')->where('DN_id',$DN->id)->first();

            if (!$DN) {
                throw new Exception('Connected DN not found.', 404);
            }

            if (!$invoice) {
                throw new Exception('Connected Sale Invoice not found.', 404);
            }

            $customer = $so->customer;
            
            if (!$customer) {
                throw new Exception('customer not found against this Sale Order.', 404);
            }

            if (!$customer->acc_id) {
                throw new Exception('customer account is missing.', 400);
            }

            /*
            |--------------------------------------------------------------------------
            | STORE OLD VALUES FOR LOT / LEDGER RECALC
            |--------------------------------------------------------------------------
            */
            $oldDNTotal = (float) $DN->total_amount;


            /*
            |--------------------------------------------------------------------------
            | STEP 2: UPDATE SALE ORDER
            |--------------------------------------------------------------------------
            */
            $this->updateSaleOrder($so, $data);

            /*
            |--------------------------------------------------------------------------
            | STEP 3: UPDATE DN
            |--------------------------------------------------------------------------
            */
            $this->updateDN($DN, $data, $so);

            /*
            |--------------------------------------------------------------------------
            | STEP 4: UPDATE SALE INVOICE
            |--------------------------------------------------------------------------
            */
            $this->updateSaleReceipt($invoice, $data, $so, $DN);


            /*
            |--------------------------------------------------------------------------
            | STEP 6: UPDATE TARGETED TRANSACTION
            |--------------------------------------------------------------------------
            */
            $updatedDN = $DN->fresh();
            $newDNTotal = (float) $updatedDN->total;

            $targetTransaction = $this->findTargetTransaction(
                $customer->acc_id,
                $oldDNTotal,
                $so->order_code
            );
            // Log::info('Target Transaction: ' . $targetTransaction);
            // return "test done";
            if (!$targetTransaction) {
                throw new Exception('Target transaction not found for ledger update.', 404);
            }

            $targetTransaction->credit = $newDNTotal;
            $targetTransaction->description = 'by edit: credit amount to customer account by DN with the so is ' . $so->order_code;
            $targetTransaction->save();

            /*
            |--------------------------------------------------------------------------
            | STEP 7: RECALCULATE CURRENT BALANCE FOR THIS + NEXT TRANSACTIONS
            |--------------------------------------------------------------------------
            */
            $this->recalculatecustomerLedger($customer->acc_id, $targetTransaction->id);
            $res = [
                'success' => true,
                'message' => 'sale flow updated successfully.',
                'so_id' => $so->id,
                'dn_id' => $DN->id,
                'invoice_id' => $invoice->id,
                'transaction_id' => $targetTransaction->id
            ];
            return $res;
        });
    }

    private function updateSaleOrder(SaleOrder $so, array $data): void
    {
        $so->update([
            'order_date' => $data['order_date'] ?? $so->order_date,
            'remarks' => $data['remarks'] ?? $so->remarks,
            'total_discount' => $data['total_discount'] ?? $so->total_discount,
            'total_tax' => $data['total_tax'] ?? $so->total_tax,
            'total' => $data['total'] ?? $so->total,
        ]);

        if (!empty($data['items']) && is_array($data['items'])) {
            SaleOrderItem::where('sale_order_id', $so->id)->delete();

            foreach ($data['items'] as $item) {
                SaleOrderItem::create([
                    'sale_order_id' => $so->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'measurement_unit' => $item['measurement_unit'],
                    'unit_price' => $item['unit_price'],
                    'tax' => $item['tax'],
                    'discount_in_percentage' => $item['discount_in_percentage'],
                    'discount' => $item['discount'],
                    'total_price' => $item['quantity'] * $item['unit_price'],
                ]);
            }
        }
    }

    private function updateDN(DeliveryNote $DN, array $data, SaleOrder $so): void
    {
        $DN->update([
            'dn_date' => $data['order_date'] ?? $DN->dn_date,
            'remarks' => $data['remarks'] ?? $DN->remarks,
            'total' => $data['total'] ?? $DN->total_amount,
            'sale_order_id' => $so->id,
        ]);

        if (!empty($data['items']) && is_array($data['items'])) {
            DeliveryNoteItem::where('delivery_note_id', $DN->id)->delete();

            foreach ($data['items'] as $item) {
                $charged = $item['quantity'] * $item['unit_price'];
                DeliveryNoteItem::create([
                    'delivery_note_id' => $DN->id,
                    'product_id' => $item['product_id'],
                    'measurement_unit' => $item['measurement_unit'],
                    'quantity' => $item['quantity'],
                    'delivered' => $item['quantity'],
                    'charged' => $charged,
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount'],
                    'discount_in_percentage' => $item['discount_in_percentage'],
                    'tax' => $item['tax'],
                    'total_price' => $item['total_price'],
                ]);
            }
        }
    }

    private function updateSaleReceipt(SaleReceipt $receipt, array $data, SaleOrder $so, DeliveryNote $DN): void
    {
        $receipt->update([
            'receipt_date' => $data['order_date'] ?? $receipt->receipt_date,
            'remarks' => $data['remarks'] ?? $receipt->remarks,
            'total' => $data['total'] ?? $receipt->total_amount,
        ]);

        if (!empty($data['items']) && is_array($data['items'])) {
            SaleReceiptItem::where('sale_receipt_id', $receipt->id)->delete();

            foreach ($data['items'] as $item) {
                SaleReceiptItem::create([
                    'sale_receipt_id' => $receipt->id,
                    'product_id' => $item['product_id'],
                    'measurement_unit' => $item['measurement_unit'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total' => $item['total_price'],
                    'discount_in_percentage' => $item['discount_in_percentage'],
                    'discount' => $item['discount'],
                    'tax' => $item['tax'],
                ]);
            }
        }
    }


    private function findTargetTransaction(int $accId, float $oldAmount, string $soCode): ?Transaction
    {
        return Transaction::where('acc_id', $accId)
            ->where('transaction_type', 0)
            ->where(function ($q) use ($oldAmount) {
                $q->where('debit', $oldAmount)
                    ->orWhere('credit', $oldAmount);
            })
            ->where('description', 'like', '%' . $soCode . '%')
            ->lockForUpdate()
            ->orderBy('id')
            ->first();
    }

    private function recalculatecustomerLedger(int $accId, int $fromTransactionId): void
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