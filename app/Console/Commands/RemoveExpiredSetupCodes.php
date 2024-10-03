<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class RemoveExpiredSetupCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:remove-expired-setup-codes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove expired setup codes from the users table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get the current time
        $now = Carbon::now();

        // Find users where setup_code_expiry is older than now
        $users = User::where('setup_code_expiry', '<', $now)
                     ->whereNotNull('setup_code')
                     ->get();

        $revokedCount = 0;

        foreach ($users as $user) {
            // Remove the setup code and expiry
            $user->setup_code = null;
            $user->setup_code_expiry = null;
            $user->save();
    
            // Increment the count
            $revokedCount++;
        }
    
        // Output success message with the number of revoked codes
        $this->info("Expired setup codes removed successfully. Total revoked: {$revokedCount}");
        Log::info("Expired setup codes revoked for {$revokedCount} users.");
        return 0;
    }
}
