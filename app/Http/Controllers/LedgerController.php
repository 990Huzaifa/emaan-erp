<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\QueryException;

class LedgerController extends Controller
{
    public function index (Request $request):JsonResponse
    {
        try{
            $user = Auth::user();
            $businessId = $user->login_business;
                if (!$user->hasBusinessPermission($businessId, 'list ledger')) {
            if ($user->role == 'user') {
                    return response()->json([
                        'error' => 'User does not have the required permission.'
                    ], 403);
                }
            }
            $perPage = $request->query('per_page', 10);

            

            $results = DB::table('opening_balances as ob')
                ->leftJoin('transactions as t', 'ob.acc_id', '=', 't.acc_id')
                ->select('ob.account_id',
                        'ob.created_at as opening_balance_date',
                        'ob.amount as opening_balance',
                        DB::raw('COALESCE(SUM(t.debit), 0) as total_debits'),
                        DB::raw('COALESCE(SUM(t.credit), 0) as total_credits'),
                        DB::raw('(ob.amount + COALESCE(SUM(t.debit), 0) - COALESCE(SUM(t.credit), 0)) as current_balance'))
                ->groupBy('ob.account_id')
                ->get();

            return response()->json($results);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);
        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
