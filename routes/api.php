<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\COAController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CityController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProductCategoryController;
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
Route::apiResource('permission',PermissionController::class)->only('index');

Route::middleware(['admin.auth'])->group(function () {});

Route::middleware(['auth:sanctum'])->group(function () {
    
    Route::apiResource('business', BusinessController::class)->only(['index', 'store', 'show', 'update']);
    Route::get('business-list',[BusinessController::class, 'list']);
    // user
    Route::apiResource('user',UserController::class)->only('index','store','show','destroy');
    Route::post('user-update/{id}',[UserController::class,'update']);
    Route::get('status/{id}/user',[UserController::class,'updateStatus']);
    Route::post('verify/{id}/user',[UserController::class,'verify']);
    Route::get('invite-list/user',[UserController::class,'inviteList']);
    Route::post('invite-update/{id}/user',[UserController::class,'updateInvite']);
    Route::post('setup/{id}/user',[UserController::class,'sendSetupMail']);


    Route::get('login/{id}/permissions',[AuthController::class,'loginPermissions']);
    Route::apiResource('customer',CustomerController::class)->only('index','store','show','update');
    Route::apiResource('chart-of-account',COAController::class)->only('index','store','show','update');
    Route::apiResource('product-category',ProductCategoryController::class)->only('index','store','show','update');
    Route::apiResource('product-sub-category',ProductSubCategoryController::class)->only('index','store','show','update');
    Route::apiResource('vendor',VendorController::class)->only('index','store','show','update');
    Route::apiResource('product',ProductController::class)->only('index','store','show','update');
});

