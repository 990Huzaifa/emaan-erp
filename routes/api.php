<?php

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

// Auth Routes

Route::post('login',[AuthController::class,'login']);
Route::get('/cities',[CityController::class,'index']);

Route::apiResource('business', BusinessController::class)->only(['index', 'store', 'show', 'update']);


Route::middleware('auth:sanctum')->group(function () {

    Route::apiResource('user',UserController::class)->only('index','store','show','update');
    Route::apiResource('customer',CustomerController::class)->only('index','store','show','update');
    Route::apiResource('permission',PermissionController::class)->only('index','store');
    
});




// Route::apiResource('role',RoleController::class)->only('index','store');

// Route::get('role-has-permissions/{id}',[RoleController::class,'addPermissionToRole']);
// Route::post('role/{id}/permission',[RoleController::class,'syncPermission']);
