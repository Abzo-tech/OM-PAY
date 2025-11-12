<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TransactionController;
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

// Ultra basic test route (no dependencies)
Route::get('/test', function () {
    return response()->json(['status' => 'OK', 'message' => 'Basic test works']);
});

// Even more basic test (no response helper)
Route::get('/ping', function () {
    return 'pong';
});

// Debug route to test if routes are working
Route::post('/debug-test', function (Illuminate\Http\Request $request) {
    \Log::info('Debug test route hit', [
        'method' => $request->method(),
        'path' => $request->path(),
        'data' => $request->all()
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Debug route works',
        'data' => $request->all(),
        'timestamp' => now()->toISOString()
    ]);
});

// Test if controller can be instantiated
Route::get('/debug-controller', function () {
    try {
        $controller = app(\App\Http\Controllers\AuthController::class);
        return response()->json([
            'success' => true,
            'message' => 'AuthController instantiated successfully',
            'controller_class' => get_class($controller)
        ]);
    } catch (\Exception $e) {
        \Log::error('Controller instantiation failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json([
            'success' => false,
            'message' => 'Controller instantiation failed',
            'error' => $e->getMessage()
        ], 500);
    }
});

// Debug/Health check route (temporary)
Route::get('/debug/health', function () {
    // Force debug mode for this request
    config(['app.debug' => true]);

    \Log::info('Debug health check called', [
        'environment' => app()->environment(),
        'debug_mode' => config('app.debug'),
        'timestamp' => now()
    ]);

    try {
        \Log::info('Testing PDO extension');
        // Test if PDO PGSQL extension is loaded
        if (!extension_loaded('pdo_pgsql')) {
            throw new \Exception('PDO PostgreSQL extension not loaded');
        }
        \Log::info('PDO PGSQL extension loaded');

        \Log::info('Testing database connection');
        // Test database connection
        \DB::connection()->getPdo();
        $dbStatus = 'OK';
        \Log::info('Database connection OK');

        \Log::info('Testing cache');
        // Test cache
        \Cache::put('test', 'value', 10);
        $cacheStatus = \Cache::get('test') === 'value' ? 'OK' : 'FAILED';
        \Log::info('Cache test result', ['status' => $cacheStatus]);

        \Log::info('Testing OrangeMoney service');
        // Test OrangeMoney service
        $omService = app(\App\Services\OrangeMoneyService::class);
        $testUser = $omService->checkUser('221771234567');
        $omStatus = $testUser ? 'OK' : 'FAILED';
        \Log::info('OrangeMoney test result', ['status' => $omStatus, 'user_found' => $testUser ? true : false]);

        \Log::info('All tests passed, returning success response');
        return response()->json([
            'status' => 'OK',
            'timestamp' => now(),
            'checks' => [
                'database' => $dbStatus,
                'cache' => $cacheStatus,
                'orange_money' => $omStatus,
                'environment' => app()->environment(),
                'debug_mode' => config('app.debug'),
            ],
            'server_info' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ]
        ]);
    } catch (\Exception $e) {
        \Log::error('Exception in debug health check', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'status' => 'ERROR',
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => config('app.debug') ? $e->getTraceAsString() : 'Debug disabled',
            'debug_forced' => true
        ], 500);
    }
});

// Authentication routes (public)
Route::post('/auth/initiate-login', [AuthController::class, 'initiateLogin']);
Route::post('/auth/complete-login', [AuthController::class, 'completeLogin']);

// Protected routes (require authentication)
Route::middleware(['auth:api'])->group(function () {
    // Authentication
    Route::get('/auth/user', [AuthController::class, 'getUser']);
    Route::post('/auth/refresh-token', [AuthController::class, 'refreshToken']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Transactions
    Route::post('/transactions', [TransactionController::class, 'createTransaction']);
    Route::get('/transactions', [TransactionController::class, 'listTransactions']);
    Route::delete('/transactions/{id}', [TransactionController::class, 'cancelTransaction']);

    // Accounts
    Route::get('/accounts/details', [AccountController::class, 'getAccountDetails']);
    Route::post('/accounts/create', [AccountController::class, 'createAccount']);
    Route::delete('/accounts/{id}', [AccountController::class, 'deleteAccount']);
});

// API Documentation routes (public) - Auto-generated by L5-Swagger
Route::get('/docs', function () {
    return view('swagger.index');
})->name('api.docs');

// L5-Swagger will auto-generate the JSON documentation from controller annotations
