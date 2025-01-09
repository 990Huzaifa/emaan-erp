<?php


use App\Http\Controllers\DeliveryNoteController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\InventoryDetailController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\PurchaseVoucherController;
use App\Http\Controllers\SaleQuotationController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GRNController;
use App\Http\Controllers\COAController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\SaleOrderController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\MeasureUnitController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\PurchaseQuotationController;
use App\Http\Controllers\ProductSubCategoryController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::get('/optimize-clear', function () {
    Artisan::call('optimize:clear');
    return 'Optimization cache cleared!';
});

// Auth Routes

Route::post('setup/{code}', [AuthController::class, 'setup'])->name('setup-account');
Route::post('login',[AuthController::class,'login']);
Route::post('forgot-password',[AuthController::class,'forgotPassword']);
Route::post('reset-password',[AuthController::class,'resetPassword']);

//global 

Route::get('/cities',[CityController::class,'index']);
Route::get('list/measurement-unit',[MeasureUnitController::class,'list']);
Route::apiResource('permission',PermissionController::class)->only('index');

Route::middleware(['admin.auth'])->group(function () {});

Route::middleware(['auth:sanctum'])->group(function () {
    
    Route::get('/notifications', [NotificationController::class, 'getNotifications']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    
    
    Route::apiResource('business', BusinessController::class)->only(['index', 'store', 'show', 'update']);
    Route::get('business-list',[BusinessController::class, 'list']);
    // user
    Route::apiResource('user',UserController::class)->only('index','store','show','destroy');
    Route::post('user-update/{id}',[UserController::class,'update']);
    Route::put('status/{id}/user',[UserController::class,'updateStatus']);
    Route::get('wait-list/user',[UserController::class,'waitList']);
    Route::put('verify/{id}/user',[UserController::class,'verify']);
    Route::get('invite-list/user',[UserController::class,'inviteList']);
    Route::post('invite-update/{id}/user',[UserController::class,'updateInvite']);
    Route::post('setup/{id}/user',[UserController::class,'sendSetupMail']);


    // partners 
    
    Route::get('partner',[UserController::class,'waitPartnerList']);
    Route::post('partner',[UserController::class,'createPartner']);


    Route::get('login/{id}/permissions',[AuthController::class,'loginPermissions']);

    Route::apiResource('customer',CustomerController::class)->only('index','store','show','update');
    Route::post('customer-update/{id}',[CustomerController::class,'update']);
    Route::get('list/customer/',[CustomerController::class,'list']);
    Route::get('/csv/customer', [CustomerController::class, 'csvCustomer']);
    Route::post('/csv/customer/upload', [CustomerController::class, 'importCustomer']);
    
    Route::apiResource('chart-of-account',COAController::class)->only('index','store','show','update');
    
    Route::apiResource('product-category',ProductCategoryController::class)->only('index','store','show','update');
    Route::get('list/product-category',[ProductCategoryController::class,'list']);
    
    Route::apiResource('product-sub-category',ProductSubCategoryController::class)->only('index','store','show','update');
    Route::get('list/sub-category/{id}',[ProductSubCategoryController::class,'list']);
    Route::get('filter/sub-category/{id}',[ProductSubCategoryController::class,'filterIndex']);
    
    Route::apiResource('vendor',VendorController::class)->only('index','store','show','update');
    Route::post('vendor-update/{id}',[VendorController::class,'update']);
    Route::get('list/vendor/',[VendorController::class,'list']);
    Route::get('/csv/vendor', [VendorController::class, 'csvVendor']);
    Route::post('/csv/vendor/upload', [VendorController::class, 'importVendor']);


    Route::apiResource('employee',EmployeeController::class)->only('index','store','show','update');
    Route::post('employee-update/{id}',[EmployeeController::class,'update']);
    Route::get('/csv/employee', [EmployeeController::class, 'csvCustomer']);
    Route::post('/csv/employee/upload', [EmployeeController::class, 'importCustomer']);

    
    Route::apiResource('product',ProductController::class)->only('index','store','show','update');
    Route::put('status/{id}/product',[ProductController::class,'updateStatus']);
    Route::get('list/product/',[ProductController::class,'list']);
    Route::post('product-update/{id}',[ProductController::class,'update']);
    Route::get('/csv/product', [ProductController::class, 'csvProduct']);
    Route::post('/csv/product/upload', [ProductController::class, 'importProduct']);
    
    Route::apiResource('purchase-quotation',PurchaseQuotationController::class)->only('index','store','show','update');
    
    Route::apiResource('purchase-order',PurchaseOrderController::class)->only('index','store','show','update');
    Route::put('status/{id}/purchase-order',[PurchaseOrderController::class,'updateStatus']);
    Route::get('list/purchase-order',[PurchaseOrderController::class,'list']);
    
    Route::apiResource('grn',GRNController::class)->only('index','store','show','update');
    Route::put('status/{id}/grn',[GRNController::class,'updateStatus']);
    Route::get('list/grn',[GRNController::class,'list']);

    Route::apiResource('inventory-detail',InventoryDetailController::class)->only('index','store','show');
    Route::get('inventory-products',[InventoryDetailController::class,'inventoryProduct']);
    Route::get('lots/{product_id}',[InventoryDetailController::class,'lotIndex']);

    Route::apiResource('transaction',TransactionController::class)->only('index','show','update');
    
    // Route::get('ledger',[LedgerController::class,'index']);
    Route::get('ledger/{acc_id}',[LedgerController::class,'list']);
    
    Route::apiResource('purchase-voucher',PurchaseVoucherController::class)->only('index','store','show','update');
    Route::put('status/{id}/purchase-voucher',[PurchaseVoucherController::class,'updateStatus']);
    Route::put('purchase-voucher/{grn_id}/previous',[PurchaseVoucherController::class,'previousData']);

    Route::apiResource('purchase-return',PurchaseReturnController::class)->only('index','store','show','update');


    Route::apiResource('sale-quotation',SaleQuotationController::class)->only('index','store','show','update');
    Route::put('status/{id}/sale-quotation',[SaleQuotationController::class,'updateStatus']);

    Route::apiResource('sale-order',SaleOrderController::class)->only('index','store','show','update');
    Route::put('status/{id}/sale-order',[SaleOrderController::class,'updateStatus']);

    Route::apiResource('delivery-note',DeliveryNoteController::class)->only('index','store','show','update');
    Route::put('status/{id}/delivery-note',[DeliveryNoteController::class,'updateStatus']);


    Route::apiResource('employee',EmployeeController::class)->only('index','store','show','update');
    Route::post('employee-update/{id}',[EmployeeController::class,'update']);

});

