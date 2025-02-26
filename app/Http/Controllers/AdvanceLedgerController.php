<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\SaleVoucher;
use App\Models\Vendor;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use App\Models\Transaction;
use App\Models\ChartOfAccount;

class AdvanceLedgerController extends Controller
{
    public function list(Request $request, $acc_id, $acc_type): JsonResponse
    {
        try {
            $user = Auth::user();
            $businessId = $user->login_business;
            
            if (!$user->hasBusinessPermission($businessId, 'list ledger') && $user->role != 'admin') {
                return response()->json(['error' => 'User does not have the required permission.'], 403);
            }
            
            $perPage = $request->query('per_page', 10);
            $start_date = $request->query('start_date');
            $end_date = $request->query('end_date');

            if (empty($acc_id)) throw new Exception('account id required', 404);
            if (empty($acc_type)) throw new Exception('account type required', 404);

            $results = null;
            if ($acc_type == 'CUSTOMERS') {
                $customer_id = Customer::where('acc_id', $acc_id)->value('id');
                
                $query = Transaction::where('acc_id', $acc_id);

                // Apply date filters if provided
                if (!empty($start_date)) {
                    $query->where('created_at', '>=', $start_date);
                }

                if (!empty($end_date)) {
                    $query->where('created_at', '<=', $end_date);
                }

                // Paginate the results
                $results = $query->get();
                $totalCredit = $results->sum('credit');
                $remainingCredit = $totalCredit;
                
                foreach ($results as $transaction) {
                    if($transaction->debit > 0){
                        if ($remainingCredit > 0 ) {
                            // Calculate how much of this debit is covered by remaining credit
                            $coveredAmount = min($transaction->debit, $remainingCredit);
                            
                            // Calculate percentage
                            $transaction->progress_percentage = ($coveredAmount / $transaction->debit) * 100;
                            
                            // Reduce remaining credit
                            $remainingCredit -= $coveredAmount;
                        }else{
                            $transaction->progress_percentage =0;
                        }
                    } else {
                        // If no remaining credit, progress is 0%
                        $transaction->progress_percentage = '-';
                    }
                }
                
            }
            return response()->json($results, 200);
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function listAccounts(Request $request): JsonResponse
    {
        try{

            $user = Auth::user();
            $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list ledger')) {
            if ($user->role != 'admin') {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $name = $request->query('name');
            if(empty($name)) throw new Exception('Account name Required', 400);
            $parent_code = null;
            $results = null;

            if ($name === 'CUSTOMERS') {
                $parent_code = ChartOfAccount::select('code')->where('name',$name)->value('code');
                $results = ChartOfAccount::select('chart_of_accounts.id','chart_of_accounts.name','chart_of_accounts.code')
                ->join('customers', 'chart_of_accounts.ref_id', '=', 'customers.id')
                ->where('customers.business_id', $businessId)
                ->where('parent_code', $parent_code)->get();
            }
            else if($name == 'VENDORS'){
                $parent_code = ChartOfAccount::select('code')->where('name',$name)->value('code');
                $results = ChartOfAccount::select('id','name','code')->where('parent_code', $parent_code)->get();
            }
            else{
                 throw new Exception('Invalid Account', 400);
            }

            return response()->json($results,200);

        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
