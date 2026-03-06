<?php
use App\Http\Controllers\Product\MergeController;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\AdvanceLedgerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Hr\DepartmentController;
use App\Http\Controllers\Hr\DesignationController;
use App\Http\Controllers\JournalVoucherController;
use App\Http\Controllers\Hr\LoanController;
use App\Http\Controllers\Hr\LoanVoucherController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\Hr\PayPolicyController;
use App\Http\Controllers\Hr\PaySlipController;
use App\Http\Controllers\Purchase\PurchaseReturnVoucherController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\Sale\SaleReturnVoucherController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Purchase\GRNController;
use App\Http\Controllers\COAController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\Product\ProductController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\Hr\EmployeeController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\Sale\SaleOrderController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\Product\MeasureUnitController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Sale\DeliveryNoteController;
use App\Http\Controllers\Purchase\PurchaseOrderController;
use App\Http\Controllers\Sale\SaleQuotationController;
use App\Http\Controllers\Purchase\PurchaseInvoiceController;
use App\Http\Controllers\Product\ProductCategoryController;
use App\Http\Controllers\Purchase\PurchaseVoucherController;
use App\Http\Controllers\Purchase\PurchaseReturnController;
use App\Http\Controllers\InventoryDetailController;
use App\Http\Controllers\Purchase\PurchaseQuotationController;
use App\Http\Controllers\Product\ProductSubCategoryController;
use App\Http\Controllers\Sale\SaleReceiptController;
use App\Http\Controllers\Sale\SaleReturnController;
use App\Http\Controllers\Sale\SaleVoucherController;
use App\Http\Controllers\ExpenseVoucherController;
use App\Http\Controllers\Hr\SalaryVoucherController;

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


// API Routes
Route::get('/', function () {
    echo "<h1>very funny😑, GO to Hell</h1>";
});


Route::get('/optimize-clear', function () {
    Artisan::call('optimize:clear');
    return 'Optimization cache cleared!';
});

Route::get('/run-queue', function () {
    Artisan::call('queue:work');
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
    Route::post('/broadcasting/auth', [NotificationController::class, 'broadcast']);
        
    
    Route::get('profile',[AuthController::class,'profile']);
    
    
    Route::apiResource('business', BusinessController::class)->only(['index', 'store', 'show', 'update']);
    Route::post('business-update/{id}',[BusinessController::class,'updateBusiness']);
    Route::get('business-list',[BusinessController::class, 'list']);
    Route::get('business-accounts',[BusinessController::class,'businessAccounts']);
    Route::get('global-accounts',[BusinessController::class,'globalAccounts']);
    // user
    Route::apiResource('user',UserController::class)->only('index','store','show','destroy');
    Route::post('user-update/{id}',[UserController::class,'update']);
    Route::put('status/{id}/user',[UserController::class,'updateStatus']);
    Route::get('wait-list/user',[UserController::class,'waitList']);
    Route::put('verify/{id}/user',[UserController::class,'verify']);
    Route::get('invite-list/user',[UserController::class,'inviteList']);
    Route::post('invite-update/{id}/user',[UserController::class,'updateInvite']);
    Route::post('setup/{id}/user',[UserController::class,'sendSetupMail']);

    Route::post('update-profile',[UserController::class,'editProfile']);
    
    
    // partners 
    
    Route::apiResource('partner',PartnerController::class)->only(['index', 'store', 'show', 'update']);
    Route::get('list/partner',[PartnerController::class,'list']);
    Route::post('partner-update/{id}',[PartnerController::class,'update']);

    // Merge products
    Route::post('merge-products',[MergeController::class,'store']);


    Route::apiResource('journal-voucher',JournalVoucherController::class)->only('index','store','show','update');
    Route::put('status/{id}/journal-voucher',[JournalVoucherController::class,'updateStatus']);


    Route::get('login/{id}/permissions',[AuthController::class,'loginPermissions']);
    Route::apiResource('customer',CustomerController::class)->only('index','store','show','update');
    Route::post('customer-update/{id}',[CustomerController::class,'update']);
    Route::get('list/customer/',[CustomerController::class,'list']);
    Route::get('/csv/customer', [CustomerController::class, 'csvCustomer']);
    Route::post('/csv/customer/upload', [CustomerController::class, 'importCustomer']);
    Route::get('customer-analytics',[CustomerController::class,'customerAnalytics']);
    
    Route::apiResource('chart-of-account',COAController::class)->only('index','store','show','update');
    
    Route::apiResource('measurement-unit',MeasureUnitController::class)->only('index','store','show','update');
    
    
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
    Route::get('vendor-analytics',[VendorController::class,'vendorAnalytics']);
    
    Route::apiResource('department',DepartmentController::class)->only('index','store','show','update');
    Route::get('list/department',[DepartmentController::class,'list']);
    Route::put('status/{id}/department',[DepartmentController::class,'updateStatus']);
    
    Route::apiResource('designation',DesignationController::class)->only('index','store','show','update');
    Route::get('list/designation/{id}',[DesignationController::class,'list']);
    Route::put('status/{id}/designation',[DesignationController::class,'updateStatus']);
    Route::get('filter/designation/{id}',[DesignationController::class,'filterIndex']);

    Route::apiResource('pay-policy',PayPolicyController::class)->only('index','store','show','update');
    Route::get('list/pay-policy/',[PayPolicyController::class,'list']);
    Route::put('status/{id}/pay-policy',[PayPolicyController::class,'updateStatus']);
    
    Route::apiResource('employee',EmployeeController::class)->only('index','store','show','update');
    Route::post('employee-update/{id}',[EmployeeController::class,'update']);
    Route::get('list/employees',[EmployeeController::class,'list']);
    Route::get('/csv/employee', [EmployeeController::class, 'csvCustomer']);
    Route::post('/csv/employee/upload', [EmployeeController::class, 'importCustomer']);

    Route::apiResource('pay-slip',PaySlipController::class)->only('index','store','show','update');
    Route::put('status/{id}/pay-slip',[PaySlipController::class,'updateStatus']);
    Route::get('filter/pay-slip/{id}',[PaySlipController::class,'filterList']);
    
    Route::apiResource('loan',LoanController::class)->only('index','store','show','update');
    Route::put('status/{id}/loan',[LoanController::class,'updateStatus']);
    Route::get('filter/loan/',[LoanController::class,'filterList']);

    Route::apiResource('loan-voucher',LoanVoucherController::class)->only('index','store','show','update');
    Route::put('status/{id}/loan-voucher',[LoanVoucherController::class,'updateStatus']);

    
    
    Route::apiResource('product',ProductController::class)->only('index','store','show','update');
    Route::put('status/{id}/product',[ProductController::class,'updateStatus']);
    Route::get('list/product/',[ProductController::class,'list']);
    Route::post('product-update/{id}',[ProductController::class,'update']);
    Route::get('/csv/product', [ProductController::class, 'csvProduct']);
    Route::post('/csv/product/upload', [ProductController::class, 'importProduct']);
    
    Route::apiResource('purchase-quotation',PurchaseQuotationController::class)->only('index','store','show','update');
    Route::put('status/{id}/purchase-quotation',[PurchaseQuotationController::class,'updateStatus']);
    
    Route::apiResource('purchase-order',PurchaseOrderController::class)->only('index','store','show','update');
    Route::put('status/{id}/purchase-order',[PurchaseOrderController::class,'updateStatus']);
    Route::get('list/purchase-order',[PurchaseOrderController::class,'list']);
    Route::get('list/vendor-purchase-order',[PurchaseOrderController::class,'list2']);
    
    Route::apiResource('grn',GRNController::class)->only('index','store','show','update');
    Route::put('status/{id}/grn',[GRNController::class,'updateStatus']);
    Route::put('reverse/{id}/grn',[GRNController::class,'reverse']);
    Route::get('list/grn',[GRNController::class,'list']);
    
    Route::apiResource('purchase-invoice',PurchaseInvoiceController::class)->only('index','store','show','update');
    Route::put('status/{id}/purchase-invoice',[PurchaseInvoiceController::class,'updateStatus']);
    Route::get('list/purchase-invoice',[PurchaseInvoiceController::class,'list']);
    Route::get('print/purchase-invoice/{id}',[PurchaseInvoiceController::class,'print']);
    
    Route::apiResource('purchase-voucher',PurchaseVoucherController::class)->only('index','store','show','update');
    Route::put('status/{id}/purchase-voucher',[PurchaseVoucherController::class,'updateStatus']);
    Route::put('purchase-voucher/{grn_id}/previous',[PurchaseVoucherController::class,'previousData']);
    Route::post('purchase-bulk-update',[PurchaseVoucherController::class,'bulkUpdateStatus']);
    
    Route::apiResource('purchase-return',PurchaseReturnController::class)->only('index','store','show','update');
    Route::put('status/{id}/purchase-return',[PurchaseReturnController::class,'updateStatus']);

    Route::apiResource('purchase-return-voucher',PurchaseReturnVoucherController::class)->only('index','store','show','update');
    Route::put('status/{id}/purchase-return-voucher',[PurchaseReturnVoucherController::class,'updateStatus']);
    Route::put('purchase-return-voucher/{grn_id}/previous',[PurchaseReturnVoucherController::class,'previousData']);
    
    
    Route::apiResource('voucher',VoucherController::class)->only('index','store','show','update');
    Route::put('status/{id}/voucher',[VoucherController::class,'updateStatus']);
    
    
    // Sale
    
    Route::apiResource('sale-quotation',SaleQuotationController::class)->only('index','store','show','update');
    Route::put('status/{id}/sale-quotation',[SaleQuotationController::class,'updateStatus']);
    
    Route::apiResource('sale-order',SaleOrderController::class)->only('index','store','show','update');
    Route::put('status/{id}/sale-order',[SaleOrderController::class,'updateStatus']);
    Route::get('list/sale-order',[SaleOrderController::class,'list']);
    Route::get('last-receipt-item/{customerId}',[SaleOrderController::class,'getLastReceiptItem']);
    
    Route::apiResource('delivery-note',DeliveryNoteController::class)->only('index','store','show','update');
    Route::put('status/{id}/delivery-note',[DeliveryNoteController::class,'updateStatus']);
    Route::put('reverse/{id}/delivery-note',[DeliveryNoteController::class,'reverse']);
    Route::get('list/dn',[DeliveryNoteController::class,'list']);
    
    Route::apiResource('sale-receipt',SaleReceiptController::class)->only('index','store','show','update');
    Route::put('status/{id}/sale-receipt',[SaleReceiptController::class,'updateStatus']);
    Route::get('print/sale-receipt/{id}',[SaleReceiptController::class,'print']);
    
    Route::apiResource('sale-voucher',SaleVoucherController::class)->only('index','store','show','update');
    Route::put('status/{id}/sale-voucher',[SaleVoucherController::class,'updateStatus']);
    Route::post('sale-bulk-update',[SaleVoucherController::class,'bulkUpdateStatus']);
    
    Route::apiResource('sale-return',SaleReturnController::class)->only('index','store','show','update');
    Route::put('status/{id}/sale-return',[SaleReturnController::class,'updateStatus']);

    Route::apiResource('sale-return-voucher',SaleReturnVoucherController::class)->only('index','store','show','update');
    Route::put('status/{id}/sale-return-voucher',[SaleReturnVoucherController::class,'updateStatus']);
        
    Route::apiResource('inventory-detail',InventoryDetailController::class)->only('index','store','show');
    Route::get('inventory-products',[InventoryDetailController::class,'inventoryProduct']);
    Route::get('lots/{product_id}',[InventoryDetailController::class,'lotIndex']);
    
    Route::apiResource('transaction',TransactionController::class)->only('index','show','update');
    
    
    // Expense
    
    Route::apiResource('expense-voucher',ExpenseVoucherController::class)->only('index','store','show','update');
    Route::put('status/{id}/expense-voucher',[ExpenseVoucherController::class,'updateStatus']);
    Route::post('expense-bulk-update',[ExpenseVoucherController::class,'bulkUpdateStatus']);

    Route::apiResource('salary-voucher',SalaryVoucherController::class)->only('index','store','show','update');
    Route::put('status/{id}/salary-voucher',[SalaryVoucherController::class,'updateStatus']);
    
    // Route::get('ledger',[LedgerController::class,'index']);
    Route::get('ledger/{acc_id}',[LedgerController::class,'list']);
    Route::get('advance-ledger/{acc_id}/{acc_type}',[AdvanceLedgerController::class,'list']);
    Route::get('fetch-accounts',[LedgerController::class,'listAccounts']);


    // Reports

    Route::get('inventory-report',[ReportsController::class,'inventoryReport']);
    Route::get('inventory-report/{id}/detail',[ReportsController::class,'inventoryReportDetail']);
    
    Route::get('financial-report',[ReportsController::class,'financialReport']);

    Route::get('sale-summary',[ReportsController::class,'salesSummary']);
    Route::get('purchase-summary',[ReportsController::class,'purchaseSummary']);
    Route::get('sale-report',[ReportsController::class,'saleReport']);


    Route::get('sales-chart',[ReportsController::class,'salesChart']);
    Route::get('sales-chart-by-item-and-month',[ReportsController::class,'salesChartByItemAndMonth']);
    Route::get('party-sale-summary',[ReportsController::class,'partySalesSummary']);
    Route::get('party-purchase-summary',[ReportsController::class,'partyPurchaseSummary']);
    Route::get('purchase-report',[ReportsController::class,'purchaseReport']);

    Route::get('balance-sheet',[ReportsController::class,'balanceSheet']);

    Route::get('customer-balances',[ReportsController::class,'customerBalances']);
    Route::get('employee-balances',[ReportsController::class,'employeeBalances']);
    Route::get('vendor-balances',[ReportsController::class,'vendorBalances']);
    Route::get('cnb-balances',[ReportsController::class,'cnbBalances']);



    Route::get('pnl-report',[ReportsController::class,'pnl']);


    // Dashboard

    Route::get('dashboard/low-pay-customer',[DashboardController::class,'nonPaidCustomer']);
    Route::get('dashboard/customer',[DashboardController::class,'lowPayCustomers']);
    Route::get('dashboard/inventory',[DashboardController::class,'inventoryProducts']);
    Route::get('dashboard/sale-analysis',[DashboardController::class,'salesAnalysis']);
    Route::get('dashboard/statistics',[DashboardController::class,'statistics']);
    
    Route::get('dashboard/sale-by-product',[DashboardController::class,'saleByProduct']);
    Route::get('dashboard/sale-by-city',[DashboardController::class,'saleByCity']);

    Route::get('dashboard/purchase-orders',[DashboardController::class,'purchaseOrders']);
    Route::get('dashboard/sale-orders',[DashboardController::class,'saleOrders']);

    Route::get('dashboard/aov',[DashboardController::class,'AOV']);
    Route::get('dashboard/things-to-review',[DashboardController::class,'thingsToReview']);

    Route::get('test/whatsapp',[DashboardController::class,'testWhatsapp']);


});

