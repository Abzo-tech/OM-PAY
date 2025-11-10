<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes v1
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Authentication Routes (public)
Route::post('/auth/initiate-login', [AuthController::class, 'initiateLogin']);
Route::get('/auth/complete-login/{token}', [AuthController::class, 'completeLogin']);

// Account API Routes
Route::middleware('auth:sanctum')->group(function () {
    // Account management
    Route::post('/accounts/create', [AccountController::class, 'createAccount']);
    Route::get('/accounts/details', [AccountController::class, 'getAccountDetails']);
    Route::get('/accounts/status', [AccountController::class, 'checkAccountStatus']);

    // Payment operations (require active account)
    Route::post('/payments/initiate', [PaymentController::class, 'initiatePayment']);
    Route::get('/payments/status/{reference}', [PaymentController::class, 'checkPaymentStatus']);

    // Account operations
    Route::get('/account/balance', [PaymentController::class, 'getAccountBalance']);

    // Transaction history
    Route::get('/transactions', [PaymentController::class, 'getTransactionHistory']);
});