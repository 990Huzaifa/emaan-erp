<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Vendor;
use Exception;
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
            
            if (!$user->hasBusinessPermission($businessId, 'list advance ledger') && $user->role != 'admin') {
                return response()->json(['error' => 'User does not have the required permission.'], 403);
            }
            
            $perPage = $request->query('per_page', 10);
            $start_date = $request->query('start_date');
            $end_date = $request->query('end_date');
            $sortOrder = $request->query('sort_order'); // No default sorting
            // $dateSort = $request->query('sort_order'); // No default sorting

            if (empty($acc_id)) throw new Exception('account id required', 404);
            if (empty($acc_type)) throw new Exception('account type required', 404);

            $results = null;
            if ($acc_type == 'CUSTOMERS') {
                $customer_id = Customer::where('acc_id', $acc_id)->value('id');
                
                $query = Transaction::select(
                    'transactions.id',
                    'transactions.acc_id',
                    'transactions.debit',
                    'transactions.credit',
                    'transactions.current_balance',
                    'transactions.description',
                    'transactions.created_at',
                    'transactions.updated_at',
                    'transactions.link as sale_order_id',
                )->where('acc_id', $acc_id)
                ->orderBy('created_at', 'asc');

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
                        $transaction->progress_percentage = null;
                    }
                }
                // Sorting based on query param if provided
                if ($sortOrder === 'credit_first') {
                    $results = $results->sortByDesc('credit')->values();
                } elseif ($sortOrder === 'debit_first') {
                    $results = $results->sortByDesc('debit')->values();
                }
                
            }
            else if($acc_type == 'VENDORS'){
                $vendor_id = Vendor::where('acc_id', $acc_id)->value('id');
                $query = Transaction::select(
                    'transactions.id',
                    'transactions.acc_id',
                    'transactions.debit',
                    'transactions.credit',
                    'transactions.current_balance',
                    'transactions.description',
                    'transactions.created_at',
                    'transactions.updated_at',
                    'transactions.link as purchase_order_id',
                )->where('acc_id', $acc_id)
                ->orderBy('created_at', 'asc');

                if (!empty($start_date)) {
                    $query->where('created_at', '>=', $start_date);
                }

                if (!empty($end_date)) {
                    $query->where('created_at', '<=', $end_date);
                }

                // Paginate the results
                $results = $query->get();
                $totalDebit = $results->sum('debit');
                $remainingDebit = $totalDebit;
                
                foreach ($results as $transaction) {
                    if($transaction->credit > 0){
                        if ($remainingDebit > 0 ) {
                            // Calculate how much of this debit is covered by remaining credit
                            $coveredAmount = min($transaction->credit, $remainingDebit);
                            
                            // Calculate percentage
                            $transaction->progress_percentage = ($coveredAmount / $transaction->credit) * 100;
                            
                            // Reduce remaining debit
                            $remainingDebit -= $coveredAmount;
                        }else{
                            $transaction->progress_percentage =0;
                        }
                    } else {
                        // If no remaining debit, progress is 0%
                        $transaction->progress_percentage = null;
                    }
                }
                // Sorting based on query param if provided
                if ($sortOrder === 'credit_first') {
                    $results = $results->sortByDesc('credit')->values();
                } elseif ($sortOrder === 'debit_first') {
                    $results = $results->sortByDesc('debit')->values();
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



    


    // for vendors advance ledger
}
