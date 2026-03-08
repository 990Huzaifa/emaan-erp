<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;
use App\Models\Transaction;
use App\Models\ChartOfAccount;

class LedgerController extends Controller
{
    public function index (Request $request):JsonResponse
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
            $perPage = $request->query('per_page', 10);

            

            $results = DB::table('opening_balances as ob')
                ->leftJoin('transactions as t', 'ob.acc_id', '=', 't.acc_id')
                ->leftJoin('chart_of_accounts as coa', 'ob.acc_id', '=', 'coa.id')
                ->select('ob.acc_id',
                        'coa.name as account_name',
                        'ob.created_at as opening_balance_date',
                        'ob.amount as opening_balance',
                        DB::raw('COALESCE(SUM(t.debit), 0) as total_debits'),
                        DB::raw('COALESCE(SUM(t.credit), 0) as total_credits'),
                        DB::raw('(ob.amount + COALESCE(SUM(t.debit), 0) - COALESCE(SUM(t.credit), 0)) as current_balance'))
                ->groupBy('ob.acc_id', 'ob.created_at','ob.amount', 'coa.name')  // Added `ob.created_at` to GROUP BY
                ->orderBy('t.created_at', 'asc')
                ->paginate($perPage);

            return response()->json($results);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    
    public function list (Request $request,$acc_id):JsonResponse
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
            $perPage = $request->query('per_page', 10);
            $start_date = $request->query('start_date');
            $end_date = $request->query('end_date');
            
            if (empty($acc_id)) throw new Exception('account id required', 404);

                    // Build the query
            $query = Transaction::where('acc_id', $acc_id);

            // Apply date filters if provided
            if (!empty($start_date)) {
                $query->where('created_at', '>=', $start_date);
            }

            if (!empty($end_date)) {
                $query->where('created_at', '<=', $end_date);
            }

            // Paginate the results
            $results = $query->orderBy('created_at', 'asc')->paginate($perPage);

            $totalDebit = $results->sum('debit');
            $totalCredit = $results->sum('credit');
    
            // Prepare response with totals and transactions
            return response()->json([
                'transactions' => $results,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit
            ]);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
    
    public  function listAccounts(Request $request):JsonResponse
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
            else if($name == 'EMPLOYEE_SALARY'){
                $parent_code = ChartOfAccount::select('code')->where('name','EMPLOYEES SALARY')->value('code');
                $results = ChartOfAccount::select('id','name','code')->where('parent_code', $parent_code)->get();
            }
            else if($name == 'BUSINESS_EXPENSE'){
                $parent_code = ChartOfAccount::select('code')->where('name','BUSINESS EXPENSE')->value('code');
                $results = ChartOfAccount::select('chart_of_accounts.id','chart_of_accounts.name','chart_of_accounts.code')
                ->join('business_has_accounts', 'chart_of_accounts.id', '=', 'business_has_accounts.chart_of_account_id')
                ->where('business_has_accounts.business_id', $businessId)
                ->where('parent_code', $parent_code)->get();
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
