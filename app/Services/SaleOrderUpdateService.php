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
use App\Models\InventoryDetail;
use App\Models\Transaction;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaleOrderUpdateService
{
    public function updatePurchaseFlow(int $soId, array $data, int $businessId)
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
                throw new Exception('Purchase Order not found.', 404);
            }

            $grn = $so->deliveryNote;

            $invoice = SaleReceipt::with('items')->where('grn_id',$grn->id)->first();

            if (!$grn) {
                throw new Exception('Connected GRN not found.', 404);
            }

            if (!$invoice) {
                throw new Exception('Connected Purchase Invoice not found.', 404);
            }

            $customer = $so->customer;
            
            if (!$customer) {
                throw new Exception('customer not found against this Purchase Order.', 404);
            }

            if (!$customer->acc_id) {
                throw new Exception('customer account is missing.', 400);
            }

            // validate if so can be edited with the new quantities
            $validate = $this->validateSaleOrderEditable($soId, $data['items']);
            Log::info('Validation failed: ' . $validate['message']);
            // return $validate;
            if($validate['success'] === false){
                return $validate;
            }

            /*
            |--------------------------------------------------------------------------
            | STORE OLD VALUES FOR LOT / LEDGER RECALC
            |--------------------------------------------------------------------------
            */
            $oldGrnTotal = (float) $grn->total_amount;

            $oldLots = Lot::where('purchase_order_id', $so->id)
                ->where('grn_id', $grn->id)
                ->where('customer_id', $so->customer_id)
                ->whereIn('product_id', $so->purchase_order_items->pluck('product_id')->toArray())
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');
            Log::info('Old Lots: ' . $oldLots);

            // return "test done";

            /*
            |--------------------------------------------------------------------------
            | STEP 2: UPDATE PURCHASE ORDER
            |--------------------------------------------------------------------------
            */
            $this->updateSaleOrder($so, $data);

            /*
            |--------------------------------------------------------------------------
            | STEP 3: UPDATE GRN
            |--------------------------------------------------------------------------
            */
            $this->updateGrn($grn, $data, $so);

            /*
            |--------------------------------------------------------------------------
            | STEP 4: UPDATE PURCHASE INVOICE
            |--------------------------------------------------------------------------
            */
            $this->updateSaleReceipt($invoice, $data, $so, $grn);


            /*
            |--------------------------------------------------------------------------
            | STEP 6: UPDATE TARGETED TRANSACTION
            |--------------------------------------------------------------------------
            */
            $updatedGrn = $grn->fresh();
            $newGrnTotal = (float) $updatedGrn->total;

            $targetTransaction = $this->findTargetTransaction(
                $customer->acc_id,
                $oldGrnTotal,
                $so->order_code
            );
            // Log::info('Target Transaction: ' . $targetTransaction);
            // return "test done";
            if (!$targetTransaction) {
                throw new Exception('Target transaction not found for ledger update.', 404);
            }

            $targetTransaction->credit = $newGrnTotal;
            $targetTransaction->description = 'by edit: credit amount to customer account by GRN with the so is ' . $so->order_code;
            $targetTransaction->save();

            /*
            |--------------------------------------------------------------------------
            | STEP 7: RECALCULATE CURRENT BALANCE FOR THIS + NEXT TRANSACTIONS
            |--------------------------------------------------------------------------
            */
            $this->recalculatecustomerLedger($customer->acc_id, $targetTransaction->id);
            $res = [
                'success' => true,
                'message' => 'Purchase flow updated successfully.',
                'so_id' => $so->id,
                'grn_id' => $grn->id,
                'invoice_id' => $invoice->id,
                'transaction_id' => $targetTransaction->id
            ];
            Log::info($res);
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
            SaleOrderItem::where('purchase_order_id', $so->id)->delete();

            foreach ($data['items'] as $item) {
                SaleOrderItem::create([
                    'purchase_order_id' => $so->id,
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

    private function updateGrn(DeliveryNote $grn, array $data, SaleOrder $so): void
    {
        $grn->update([
            'grn_date' => $data['grn_date'] ?? $grn->grn_date,
            'remarks' => $data['remarks'] ?? $grn->remarks,
            'total' => $data['total'] ?? $grn->total_amount,
            'purchase_order_id' => $so->id,
        ]);

        if (!empty($data['items']) && is_array($data['items'])) {
            DeliveryNoteItem::where('goods_receive_note_id', $grn->id)->delete();

            foreach ($data['items'] as $item) {
                $billed = $item['quantity'] * $item['unit_price'];
                DeliveryNoteItem::create([
                    'goods_receive_note_id' => $grn->id,
                    'product_id' => $item['product_id'],
                    'measurement_unit' => $item['measurement_unit'],
                    'quantity' => $item['quantity'],
                    'receive' => $item['quantity'],
                    'billed' => $billed,
                    'purchase_unit_price' => $item['unit_price'],
                    'sale_unit_price' => $item['unit_price'],
                    'discount' => $item['discount'],
                    'discount_in_percentage' => $item['discount_in_percentage'],
                    'tax' => $item['tax'],
                    'total_price' => $item['total_price'],
                ]);
            }
        }
    }

    private function updateSaleReceipt(SaleReceipt $invoice, array $data, SaleOrder $so, DeliveryNote $grn): void
    {
        $invoice->update([
            'invoice_date' => $data['invoice_date'] ?? $invoice->invoice_date,
            'remarks' => $data['remarks'] ?? $invoice->remarks,
            'total' => $data['total'] ?? $invoice->total_amount,
        ]);

        if (!empty($data['items']) && is_array($data['items'])) {
            SaleReceiptItem::where('purchase_invoice_id', $invoice->id)->delete();

            foreach ($data['items'] as $item) {
                SaleReceiptItem::create([
                    'purchase_invoice_id' => $invoice->id,
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