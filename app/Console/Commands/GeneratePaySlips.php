<?php

namespace App\Console\Commands;

use App\Http\Controllers\PaySlipController;
use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\PaySlip;
use Carbon\Carbon;

class GeneratePaySlips extends Command
{
    protected $signature = 'generate:payslips';
    protected $description = 'Generate payslips for employees after completing a month from their joining date';


    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today();

        $employees = Employee::whereDay('joining_date', '=', $today->day)
            ->whereMonth('joining_date', '<=', $today->month)
            ->get();

        foreach ($employees as $employee) {
            $lastPaySlip = PaySlip::where('employee_id', $employee->id)
                ->orderBy('created_at', 'desc')
                ->first();

            // If no payslip exists OR last payslip is older than 1 month, generate a new one
            if (!$lastPaySlip || Carbon::parse($lastPaySlip->created_at)->addMonth() <= $today) {
                $paySlip = new PaySlipController();
                $res = $paySlip->createSlipJob($employee->id);
                if($res == true){
                    $this->info("Payslip generated for employee ID: {$employee->id}");
                }else{
                    $this->info("Payslip generation failed for employee ID: {$employee->id}");
                }
            }
        }

        $this->info("Payslip generation process completed.");
    }
}
