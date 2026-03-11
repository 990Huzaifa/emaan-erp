<?php

namespace App\Services;

use App\Models\OpeningBalance;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\GoodsReceiveNote;
use App\Models\GoodsReceiveNoteItem;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\Lot;
use App\Models\InventoryDetail;
use App\Models\Transaction;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseOrderUpdateService
{
    public function updatePurchaseFlow(int $poId, array $data, int $businessId)
    {
        return DB::transaction(function () use ($poId, $data, $businessId) {

            /*
            |--------------------------------------------------------------------------
            | STEP 1: GET PO WITH RELATIONS
            |--------------------------------------------------------------------------
            */
            $po = PurchaseOrder::with([
                'purchase_order_items',
                'vendor',
                'goodsReceiveNote.items',
            ])
                ->lockForUpdate()
                ->find($poId);
            if (!$po) {
                throw new Exception('Purchase Order not found.', 404);
            }

            $grn = $po->goodsReceiveNote;

            $invoice = PurchaseInvoice::with('items')->where('grn_id',$grn->id)->first();

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
            $validate = $this->validatePurchaseOrderEditable($poId, $data['items']);
            Log::info('Validation failed: ' . $validate['message']);
            // return $validate;
            // if($validate['success'] === false){
            // }

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
            $this->updatePurchaseOrder($po, $data);

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
            $this->updatePurchaseInvoice($invoice, $data, $po, $grn);

            /*
            |--------------------------------------------------------------------------
            | STEP 5: UPDATE LOTS + INVENTORY
            |--------------------------------------------------------------------------
            */
            $this->updateLotsAndInventory($po, $grn, $data, $businessId, $oldLots);

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

    private function updatePurchaseOrder(PurchaseOrder $po, array $data): void
    {
        $po->update([
            'order_date' => $data['order_date'] ?? $po->order_date,
            'remarks' => $data['remarks'] ?? $po->remarks,
            'total_discount' => $data['total_discount'] ?? $po->total_discount,
            'total_tax' => $data['total_tax'] ?? $po->total_tax,
            'total' => $data['total'] ?? $po->total,
        ]);

        if (!empty($data['items']) && is_array($data['items'])) {
            PurchaseOrderItem::where('purchase_order_id', $po->id)->delete();

            foreach ($data['items'] as $item) {
                PurchaseOrderItem::create([
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

    private function updateGrn(GoodsReceiveNote $grn, array $data, PurchaseOrder $po): void
    {
        $grn->update([
            'grn_date' => $data['grn_date'] ?? $grn->grn_date,
            'remarks' => $data['remarks'] ?? $grn->remarks,
            'total' => $data['total'] ?? $grn->total_amount,
            'purchase_order_id' => $po->id,
        ]);

        if (!empty($data['items']) && is_array($data['items'])) {
            GoodsReceiveNoteItem::where('goods_receive_note_id', $grn->id)->delete();

            foreach ($data['items'] as $item) {
                $billed = $item['quantity'] * $item['unit_price'];
                GoodsReceiveNoteItem::create([
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

    private function updatePurchaseInvoice(PurchaseInvoice $invoice, array $data, PurchaseOrder $po, GoodsReceiveNote $grn): void
    {
        $invoice->update([
            'invoice_date' => $data['invoice_date'] ?? $invoice->invoice_date,
            'remarks' => $data['remarks'] ?? $invoice->remarks,
            'total' => $data['total'] ?? $invoice->total_amount,
        ]);

        if (!empty($data['items']) && is_array($data['items'])) {
            PurchaseInvoiceItem::where('purchase_invoice_id', $invoice->id)->delete();

            foreach ($data['items'] as $item) {
                PurchaseInvoiceItem::create([
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

    private function updateLotsAndInventory(
        PurchaseOrder $po,
        GoodsReceiveNote $grn,
        array $data,
        int $businessId,
        $oldLots
    ): void {
        if (empty($data['items']) || !is_array($data['items'])) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | FIRST: REMOVE OLD LOT EFFECT FROM INVENTORY
        |--------------------------------------------------------------------------
        */
        Log::info('Old Lots: in sub func' . $oldLots);
        foreach ($oldLots as $productId => $oldLot) {
            $inventory = InventoryDetail::where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (!$inventory) {
                throw new Exception("Inventory detail not found for product ID {$productId}", 404);
            }

            $inventory->stock = $inventory->stock - $oldLot->quantity;

            if ($inventory->stock < 0) {
                throw new Exception("Inventory stock would become negative for product ID {$productId}", 400);
            }

            $inventory->save();
        }

        /*
        |--------------------------------------------------------------------------
        | SECOND: UPDATE / RECREATE LOTS
        |--------------------------------------------------------------------------
        */
        Lot::where('purchase_order_id', $po->id)
            ->where('grn_id', $grn->id)
            ->delete();

        foreach ($data['items'] as $item) {
            do {
                $lot_code = 'LOT-'.str_pad(mt_rand(0, 999999999), 9, '0', STR_PAD_LEFT);
            } while (Lot::where('lot_code', $lot_code)->exists());
            $lot = Lot::create([
                'vendor_id' => $po->vendor_id,
                'product_id' => $item['product_id'],
                'purchase_order_id' => $po->id,
                'grn_id' => $grn->id,
                'quantity' => $item['quantity'],
                'purchase_unit_price' => $item['unit_price'] ?? 0,
                'sale_unit_price' => $item['unit_price'] ?? 0,
                'total_price' => $item['unit_price'] * $item['quantity'],
                'lot_code' => $lot_code,
                'expiry_date' => $item['expiry_date'] ?? null,
            ]);

            /*
            |--------------------------------------------------------------------------
            | THIRD: ADD NEW LOT EFFECT IN INVENTORY
            |--------------------------------------------------------------------------
            */
            $inventory = InventoryDetail::where('product_id', $item['product_id'])
                ->lockForUpdate()
                ->first();

            if (!$inventory) {
                $inventory = InventoryDetail::create([
                    'product_id' => $item['product_id'],
                    'stock' => 0,
                ]);
            }

            $inventory->stock = (float) $inventory->stock + (float) $lot->quantity;
            $inventory->save();
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
        $previousTransaction = Transaction::where('acc_id', $accId)
            ->where('id', '<', $fromTransactionId)
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->first();

        $openingBalance = OpeningBalance::where('acc_id', $accId)->value('amount') ?? 0;

        $runningBalance = $previousTransaction
            ? (float)$previousTransaction->current_balance
            : (float)$openingBalance;

        $transactions = Transaction::where('acc_id', $accId)
            ->where('id', '>=', $fromTransactionId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($transactions as $trx) {

            // YOUR SYSTEM FORMULA
            $runningBalance = $runningBalance - (float)$trx->debit + (float)$trx->credit;

            $trx->current_balance = $runningBalance;
            $trx->save();
        }
    }

    private function validatePurchaseOrderEditable(int $poId, array $items)
    {
        $lots = Lot::where('purchase_order_id', $poId)
            ->get()
            ->keyBy('product_id');

        foreach ($items as $item) {

            $productId = $item['product_id'];
            $newQty = $item['quantity'];

            if (!isset($lots[$productId])) {
                continue;
            }

            $lotQty = (float)$lots[$productId]->quantity;

            if ($newQty < $lotQty) {

                return [
                    'success' => false,
                    'message' => "Cannot reduce quantity for product ID {$productId} below {$lotQty} because it has already been used in transactions."
                ];
            }else{
                return [
                    'success' => true,
                    'message' => "Validation passed for product ID {$productId}. New quantity: {$newQty}, Old Lot quantity: {$lotQty}."
                ];
            }
        }
    }
}