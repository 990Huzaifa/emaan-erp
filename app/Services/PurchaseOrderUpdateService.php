<?php

namespace App\Services;

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

class PurchaseOrderUpdateService
{
    public function updatePurchaseFlow(int $poId, array $data, int $businessId): array
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
            return json_encode($po);
            if (!$po) {
                throw new Exception('Purchase Order not found.', 404);
            }

            $grn = $po->goodsReceiveNote();
            $invoice = PurchaseInvoice::with('items')->where('grn_id',$grn->id)->first();

            if (!$grn) {
                throw new Exception('Connected GRN not found.', 404);
            }

            if (!$invoice) {
                throw new Exception('Connected Purchase Invoice not found.', 404);
            }

            $vendor = $po->vendor();
            if (!$vendor) {
                throw new Exception('Vendor not found against this Purchase Order.', 404);
            }

            if (!$vendor->acc_id) {
                throw new Exception('Vendor account is missing.', 400);
            }

            /*
            |--------------------------------------------------------------------------
            | STORE OLD VALUES FOR LOT / LEDGER RECALC
            |--------------------------------------------------------------------------
            */
            $oldGrnTotal = (float) $grn->total_amount;

            $oldLots = Lot::where('po_id', $po->id)
                ->where('grn_id', $grn->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

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
            $newGrnTotal = (float) $updatedGrn->total_amount;

            $targetTransaction = $this->findTargetTransaction(
                accId: $vendor->acc_id,
                oldAmount: $oldGrnTotal,
                poCode: $po->order_code
            );

            if (!$targetTransaction) {
                throw new Exception('Target transaction not found for ledger update.', 404);
            }

            $targetTransaction->debit = $newGrnTotal;
            $targetTransaction->description = 'credit amount to vendor account by GRN with the PO is ' . $po->order_code;
            $targetTransaction->save();

            /*
            |--------------------------------------------------------------------------
            | STEP 7: RECALCULATE CURRENT BALANCE FOR THIS + NEXT TRANSACTIONS
            |--------------------------------------------------------------------------
            */
            $this->recalculateVendorLedger($vendor->acc_id, $targetTransaction->id);

            return [
                'success' => true,
                'message' => 'Purchase flow updated successfully.',
                'po_id' => $po->id,
                'grn_id' => $grn->id,
                'invoice_id' => $invoice->id,
                'transaction_id' => $targetTransaction->id
            ];
        });
    }

    private function updatePurchaseOrder(PurchaseOrder $po, array $data): void
    {
        $po->update([
            'vendor_id' => $data['vendor_id'] ?? $po->vendor_id,
            'order_date' => $data['order_date'] ?? $po->order_date,
            'remarks' => $data['remarks'] ?? $po->remarks,
            'total_amount' => $data['total_amount'] ?? $po->total_amount,
            'discount' => $data['discount'] ?? $po->discount,
            'tax_amount' => $data['tax_amount'] ?? $po->tax_amount,
            'net_amount' => $data['net_amount'] ?? $po->net_amount,
        ]);

        if (!empty($data['items']) && is_array($data['items'])) {
            PurchaseOrderItem::where('purchase_order_id', $po->id)->delete();

            foreach ($data['items'] as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['quantity'] * $item['unit_price'],
                ]);
            }
        }
    }

    private function updateGrn(GoodsReceiveNote $grn, array $data, PurchaseOrder $po): void
    {
        $grn->update([
            'vendor_id' => $data['vendor_id'] ?? $grn->vendor_id,
            'grn_date' => $data['grn_date'] ?? $grn->grn_date,
            'remarks' => $data['remarks'] ?? $grn->remarks,
            'total_amount' => $data['total_amount'] ?? $grn->total_amount,
            'purchase_order_id' => $po->id,
        ]);

        if (!empty($data['items']) && is_array($data['items'])) {
            GoodsReceiveNoteItem::where('goods_receive_note_id', $grn->id)->delete();

            foreach ($data['items'] as $item) {
                GoodsReceiveNoteItem::create([
                    'goods_receive_note_id' => $grn->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'purchase_unit_price' => $item['unit_price'],
                    'total_price' => $item['quantity'] * $item['unit_price'],
                ]);
            }
        }
    }

    private function updatePurchaseInvoice(PurchaseInvoice $invoice, array $data, PurchaseOrder $po, GoodsReceiveNote $grn): void
    {
        $invoice->update([
            'vendor_id' => $data['vendor_id'] ?? $invoice->vendor_id,
            'invoice_date' => $data['invoice_date'] ?? $invoice->invoice_date,
            'remarks' => $data['remarks'] ?? $invoice->remarks,
            'total_amount' => $data['total_amount'] ?? $invoice->total_amount,
            'purchase_order_id' => $po->id,
            'grn_id' => $grn->id,
        ]);

        if (!empty($data['items']) && is_array($data['items'])) {
            PurchaseInvoiceItem::where('purchase_invoice_id', $invoice->id)->delete();

            foreach ($data['items'] as $item) {
                PurchaseInvoiceItem::create([
                    'purchase_invoice_id' => $invoice->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['quantity'] * $item['unit_price'],
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
        foreach ($oldLots as $productId => $oldLot) {
            $inventory = InventoryDetail::where('business_id', $businessId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (!$inventory) {
                throw new Exception("Inventory detail not found for product ID {$productId}", 404);
            }

            $inventory->stock = (float) $inventory->stock - (float) $oldLot->quantity;

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
        Lot::where('po_id', $po->id)
            ->where('grn_id', $grn->id)
            ->delete();

        foreach ($data['items'] as $item) {
            $lot = Lot::create([
                'product_id' => $item['product_id'],
                'po_id' => $po->id,
                'grn_id' => $grn->id,
                'quantity' => $item['quantity'],
                'purchase_unit_price' => $item['unit_price'] ?? 0,
                'sale_unit_price' => $item['sale_unit_price'] ?? 0,
                'lot_code' => $item['lot_code'] ?? null,
                'expiry_date' => $item['expiry_date'] ?? null,
            ]);

            /*
            |--------------------------------------------------------------------------
            | THIRD: ADD NEW LOT EFFECT IN INVENTORY
            |--------------------------------------------------------------------------
            */
            $inventory = InventoryDetail::where('business_id', $businessId)
                ->where('product_id', $item['product_id'])
                ->lockForUpdate()
                ->first();

            if (!$inventory) {
                $inventory = InventoryDetail::create([
                    'business_id' => $businessId,
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

        $runningBalance = $previousTransaction ? (float) $previousTransaction->current_balance : 0;

        $transactions = Transaction::where('acc_id', $accId)
            ->where('id', '>=', $fromTransactionId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($transactions as $trx) {
            $runningBalance = $runningBalance + (float) $trx->debit - (float) $trx->credit;

            $trx->current_balance = $runningBalance;
            $trx->save();
        }
    }
}