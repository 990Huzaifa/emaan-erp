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
    public function updatePurchaseFlow(int $poId, array $data, int $businessId)
    {
        return DB::transaction(function () use ($poId, $data, $businessId) {

            /*
            |--------------------------------------------------------------------------
            | STEP 1: GET PO WITH RELATIONS
            |--------------------------------------------------------------------------
            */
            $po = SaleOrder::with([
                'purchase_order_items',
                'vendor',
                'DeliveryNote.items',
            ])
                ->lockForUpdate()
                ->find($poId);
            if (!$po) {
                throw new Exception('Purchase Order not found.', 404);
            }

            $grn = $po->deliveryNote;

            $invoice = SaleReceipt::with('items')->where('grn_id',$grn->id)->first();

            if (!$grn) {
                throw new Exception('Connected GRN not found.', 404);
            }

            if (!$invoice) {
                throw new Exception('Connected Purchase Invoice not found.', 404);
            }

            $vendor = $po->vendor;
            
            if (!$vendor) {
                throw new Exception('Vendor not found against this Purchase Order.', 404);
            }

            if (!$vendor->acc_id) {
                throw new Exception('Vendor account is missing.', 400);
            }

            // validate if PO can be edited with the new quantities
            $validate = $this->validateSaleOrderEditable($poId, $data['items']);
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

            $oldLots = Lot::where('purchase_order_id', $po->id)
                ->where('grn_id', $grn->id)
                ->where('vendor_id', $po->vendor_id)
                ->whereIn('product_id', $po->purchase_order_items->pluck('product_id')->toArray())
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
            $this->updateSaleOrder($po, $data);

            /*
            |--------------------------------------------------------------------------
            | STEP 3: UPDATE GRN
            |--------------------------------------------------------------------------
            */
            $this->updateGrn($grn, $data, $po);

            /*
            |--------------------------------------------------------------------------
            | STEP 4: UPDATE PURCHASE INVOICE
            |--------------------------------------------------------------------------
            */
            $this->updateSaleReceipt($invoice, $data, $po, $grn);


            /*
            |--------------------------------------------------------------------------
            | STEP 6: UPDATE TARGETED TRANSACTION
            |--------------------------------------------------------------------------
            */
            $updatedGrn = $grn->fresh();
            $newGrnTotal = (float) $updatedGrn->total;

            $targetTransaction = $this->findTargetTransaction(
                accId: $vendor->acc_id,
                oldAmount: $oldGrnTotal,
                poCode: $po->order_code
            );
            // Log::info('Target Transaction: ' . $targetTransaction);
            // return "test done";
            if (!$targetTransaction) {
                throw new Exception('Target transaction not found for ledger update.', 404);
            }

            $targetTransaction->credit = $newGrnTotal;
            $targetTransaction->description = 'by edit: credit amount to vendor account by GRN with the PO is ' . $po->order_code;
            $targetTransaction->save();

            /*
            |--------------------------------------------------------------------------
            | STEP 7: RECALCULATE CURRENT BALANCE FOR THIS + NEXT TRANSACTIONS
            |--------------------------------------------------------------------------
            */
            $this->recalculateVendorLedger($vendor->acc_id, $targetTransaction->id);
            $res = [
                'success' => true,
                'message' => 'Purchase flow updated successfully.',
                'po_id' => $po->id,
                'grn_id' => $grn->id,
                'invoice_id' => $invoice->id,
                'transaction_id' => $targetTransaction->id
            ];
            Log::info($res);
            return $res;
        });
    }

    private function updateSaleOrder(SaleOrder $po, array $data): void
    {
        $po->update([
            'order_date' => $data['order_date'] ?? $po->order_date,
            'remarks' => $data['remarks'] ?? $po->remarks,
            'total_discount' => $data['total_discount'] ?? $po->total_discount,
            'total_tax' => $data['total_tax'] ?? $po->total_tax,
            'total' => $data['total'] ?? $po->total,
        ]);

        if (!empty($data['items']) && is_array($data['items'])) {
            SaleOrderItem::where('purchase_order_id', $po->id)->delete();

            foreach ($data['items'] as $item) {
                SaleOrderItem::create([
                    'purchase_order_id' => $po->id,
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

    private function updateGrn(DeliveryNote $grn, array $data, SaleOrder $po): void
    {
        $grn->update([
            'grn_date' => $data['grn_date'] ?? $grn->grn_date,
            'remarks' => $data['remarks'] ?? $grn->remarks,
            'total' => $data['total'] ?? $grn->total_amount,
            'purchase_order_id' => $po->id,
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

    private function updateSaleReceipt(SaleReceipt $invoice, array $data, SaleOrder $po, DeliveryNote $grn): void
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


    private function findTargetTransaction(int $accId, float $oldAmount, string $poCode): ?Transaction
    {
        return Transaction::where('acc_id', $accId)
            ->where('transaction_type', 0)
            ->where(function ($q) use ($oldAmount) {
                $q->where('debit', $oldAmount)
                    ->orWhere('credit', $oldAmount);
            })
            ->where('description', 'like', '%' . $poCode . '%')
            ->lockForUpdate()
            ->orderBy('id')
            ->first();
    }

    private function recalculateVendorLedger(int $accId, int $fromTransactionId): void
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