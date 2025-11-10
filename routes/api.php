<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\AccountController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

// Authentication routes (public)
Route::post('/auth/initiate-login', [AuthController::class, 'initiateLogin']);
Route::post('/auth/complete-login', [AuthController::class, 'completeLogin']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // User info
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Payment operations
    Route::post('/payments/initiate', [PaymentController::class, 'initiatePayment']);
    Route::get('/payments/status/{reference}', [PaymentController::class, 'checkPaymentStatus']);

    // Account operations
    Route::get('/account/balance', [PaymentController::class, 'getAccountBalance']);
    Route::get('/account/details', [AccountController::class, 'getAccountDetails']);
    Route::post('/accounts/create', [AccountController::class, 'createAccount']);

    // Transaction history
    Route::get('/transactions', [PaymentController::class, 'getTransactionHistory']);
});
