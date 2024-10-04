<?php

use App\Http\Controllers\COAController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\VendorController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\PermissionController;


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
Route::get('/cities',[CityController::class,'index']);
Route::post('forget-password',[AuthController::class,'forgetPassword']);
Route::post('reset-password',[AuthController::class,'resetPassword']);

Route::middleware(['admin.auth'])->group(function () {});

Route::middleware(['auth:sanctum'])->group(function () {
    
    Route::apiResource('business', BusinessController::class)->only(['index', 'store', 'show', 'update']);
    // user
    Route::apiResource('user',UserController::class)->only('index','store','show','update');
    Route::get('status/{id}/user',[UserController::class,'updateStatus']);
    Route::post('verify/{id}/user',[UserController::class,'verify']);
    Route::get('invite-list/user',[UserController::class,'inviteList']);
    Route::get('setup/{id}/user',[UserController::class,'sendSetupMail']);


    Route::get('login/{id}/permissions',[AuthController::class,'loginPermissions']);
    Route::apiResource('customer',CustomerController::class)->only('index','store','show','update');
    Route::apiResource('chart-of-account',COAController::class)->only('index','store','show','update');
    Route::apiResource('product-category',ProductCategoryController::class)->only('index','store','show','update');
    Route::apiResource('vendor',VendorController::class)->only('index','store','show','update');
    Route::apiResource('permission',PermissionController::class)->only('index','store');
    
});
