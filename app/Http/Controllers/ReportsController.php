<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function inventoryReport(): JsonResponse
    {
        $data =Product::select('id','title','image')->pluck('id')->toArray();
        return response()->json($data);
    }

    public function inventoryReportDetail(Request $request): JsonResponse
    {
        $data =Product::find($request->id);
        return response()->json($data);
    }
}
