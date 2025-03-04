<?php

namespace App\Console\Commands;

use App\Models\InventoryDetail;
use App\Models\Lot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateInventoryStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-inventory-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check inventory quantity and update status daily';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $lowStockThreshold = 10;

        // Fetch all inventory items
        $inventories = InventoryDetail::all();
        $lots = Lot::all();

        foreach ($inventories as $inventory) {
            if ($inventory->stock <= 0) {
                $inventory->in_stock = 0;
            } elseif ($inventory->stock <= $lowStockThreshold) {
                $inventory->in_stock = 2;
            } else {
                $inventory->in_stock = 1;
            }

            // Save changes
            $inventory->save();
        }

        foreach ($lots as $lot) {
            if ($lot->quantity <= 0) {
                $lot->status = 0;
            } elseif ($lot->quantity <= $lowStockThreshold) {
                $lot->status = 2;
            } else {
                $lot->status = 1;
            }
            // Save changes
            $lot->save();
        }

        Log::info('Inventory statuses updated successfully.');
    }
}
