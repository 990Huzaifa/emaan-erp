<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            // Fetch all permissions
            $permissions = Permission::select('id', 'name')->get();
    
            // Define the structure for organizing permissions
            $groupedPermissions = [
                'Users' => ['list users', 'view users', 'create users', 'edit users', 'delete users'],
                'Transaction' => ['list transaction', 'view transaction'],
                'Inventory Detail' => ['list inventory detail', 'view inventory detail'],
                'Customers' => ['list customers', 'view customers', 'create customers', 'edit customers', 'delete customers'],
                'Vendors' => ['list vendors', 'view vendors', 'create vendors', 'edit vendors', 'delete vendors'],
                'Products' => ['list products', 'view products', 'create products', 'edit products', 'delete products'],
                'Chart of Account' => ['create chart of account', 'view chart of account', 'edit chart of account', 'list chart of account'],
                'Product Category' => [
                    'list product category',
                    'create product category',
                    'view product category',
                    'delete product category',
                    'edit product category'
                ],
                'Product Sub Category' => [
                    'list product sub category',
                    'create product sub category',
                    'view product sub category',
                    'delete product sub category',
                    'edit product sub category'
                ],
                'Purchase Quotations' => [
                    'list purchase quotations',
                    'view purchase quotations',
                    'create purchase quotations',
                    'edit purchase quotations',
                    'approve purchase quotations'
                ],                
                'Purchase Order' => [
                    'list purchase orders',
                    'view purchase orders',
                    'create purchase orders',
                    'edit purchase orders',
                    'approve purchase orders',
                    'delete purchase orders'
                ],
                'Good Received Note' => [
                    'list goods received notes',
                    'view goods received notes',
                    'create goods received notes',
                    'edit goods received notes',
                    'approve goods received notes'
                ],               
                "Purchase Return" => [
                    'create purchase return',
                    'view purchase return',
                    'list purchase return',
                    'edit purchase return',
                    'approve purchase return',                    
                ],
                'Sale Quotation' => [
                    'list sale quotations',
                    'view sale quotations',
                    'create sale quotations',
                    'edit sale quotations',
                    'approve sale quotations',
                ],
                'Sale Order' => [
                    'list sale orders',
                    'view sale orders',
                    'create sale orders',
                    'edit sale orders',
                    'approve sale orders',
                    'delete sale orders'
                ],
                "Delivery Note" => [
                    'list delivery notes',
                    'view delivery notes',
                    'create delivery notes',
                    'edit delivery notes',
                    'approve delivery notes'
                ],
                "Sale Return" =>[
                    'create sale return',
                    'view sale return',
                    'list sale return',
                    'edit sale return',
                    'approve sale return',
                ],                
                'Employee' => [
                    'list employee',
                    'view employee',
                    'create employee',
                    'edit employee',
                    'approve employee'
                ],
                'Purchase Voucher' =>['create purchase voucher','view purchase voucher','list purchase voucher','edit purchase voucher','approve purchase voucher'],
                'Sale Voucher'=>['create sale voucher', 'view sale voucher', 'list sale voucher', 'edit sale voucher', 'approve sale voucher'],
                'Ledger' => ['list ledger'],
                'Measurement Unit' => ['list measurement unit', 'view measurement unit', 'create measurement unit', 'edit measurement unit',],
                'Purchase Invoice' =>[
                    'create purchase invoice',
                    'view purchase invoice',
                    'list purchase invoice',
                    'edit purchase invoice',
                    'approve purchase invoice'
                ],
                'Sales Invoice' =>[
                    'create sales invoice',
                    'view sales invoice',
                    'list sales invoice',
                    'edit sales invoice',
                    'approve sales invoice'
                ],
            ];
    
            // Prepare an empty array to store structured permissions
            $structuredPermissions = [];
    
            // Loop through each permission group
            foreach ($groupedPermissions as $group => $permissionsList) {
                $structuredPermissions[$group] = $permissions
                    ->whereIn('name', $permissionsList)
                    ->map(function ($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                        ];
                    })
                    ->toArray();
            }
    
            // Return the structured permissions as JSON
            return response()->json($structuredPermissions, 200);
    
        } catch (QueryException $e) {
            return response()->json(['DB error' => $e->getMessage()], 400);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }


    public function store(Request $request):JsonResponse
    {
        try{
            $validator = Validator::make(
                $request->all(),[
                    'name'=>'required|string|max:115|unique:permissions'

            ],[
                'name.required'=>'Name is Required',
                'name.string'=>'Name is must be a string',
                'name.max'=>'Name cannot exceed 115 characters.',
            ]);
            if ($validator->fails()) throw new Exception($validator->errors()->first(), 400);
            $permission = Permission::create([
                'name'=>$request->name
            ]);

            return response()->json($permission);
        }catch(QueryException $e){
            return response()->json(['DB error' => $e->getMessage()], 400);

        }catch(Exception $e){
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

}
