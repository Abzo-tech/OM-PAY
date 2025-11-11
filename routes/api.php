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

// Debug/Health check route (temporary)
Route::get('/debug/health', function () {
    try {
        // Test database connection
        \DB::connection()->getPdo();
        $dbStatus = 'OK';

        // Test cache
        \Cache::put('test', 'value', 10);
        $cacheStatus = \Cache::get('test') === 'value' ? 'OK' : 'FAILED';

        // Test OrangeMoney service
        $omService = app(\App\Services\OrangeMoneyService::class);
        $testUser = $omService->checkUser('221771234567');
        $omStatus = $testUser ? 'OK' : 'FAILED';

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
        return response()->json([
            'status' => 'ERROR',
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => config('app.debug') ? $e->getTraceAsString() : null
        ], 500);
    }
});

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
    Route::post('/transfers/initiate', [PaymentController::class, 'initiateTransfer']);
    Route::get('/payments/status/{reference}', [PaymentController::class, 'checkPaymentStatus']);

    // Account operations
    Route::get('/account/balance', [PaymentController::class, 'getAccountBalance']);
    Route::get('/account/details', [AccountController::class, 'getAccountDetails']);
    Route::post('/accounts/create', [AccountController::class, 'createAccount']);

    // Transaction history
    Route::get('/transactions', [PaymentController::class, 'getTransactionHistory']);
});

// API Documentation routes (public)
Route::get('/docs', function () {
    return view('swagger.index');
})->name('api.docs');

Route::get('/docs.json', function () {
    return response()->json([
        "openapi" => "3.0.3",
        "info" => [
            "title" => "OM Pay API",
            "description" => "Orange Money Payment API - Transferts et paiements sécurisés",
            "version" => "1.0.0",
            "contact" => [
                "name" => "OM Pay Support",
                "email" => "support@om-pay.com"
            ]
        ],
        "servers" => [
            [
                "url" => "http://localhost:8000/api",
                "description" => "Serveur de développement"
            ]
        ],
        "security" => [
            [
                "bearerAuth" => []
            ]
        ],
        "tags" => [
            [
                "name" => "Authentification",
                "description" => "Opérations d'authentification OTP"
            ],
            [
                "name" => "Paiements",
                "description" => "Paiements vers marchands externes"
            ],
            [
                "name" => "Transferts",
                "description" => "Transferts entre comptes OM PAY"
            ],
            [
                "name" => "Comptes",
                "description" => "Gestion des comptes utilisateurs"
            ],
            [
                "name" => "Transactions",
                "description" => "Historique et statut des transactions"
            ],
            [
                "name" => "User",
                "description" => "Informations utilisateur"
            ]
        ],
        "components" => [
            "securitySchemes" => [
                "bearerAuth" => [
                    "type" => "http",
                    "scheme" => "bearer",
                    "bearerFormat" => "JWT"
                ]
            ],
            "schemas" => [
                "User" => [
                    "type" => "object",
                    "properties" => [
                        "id" => ["type" => "integer"],
                        "name" => ["type" => "string"],
                        "email" => ["type" => "string"],
                        "phone" => ["type" => "string"],
                        "is_verified" => ["type" => "boolean"],
                        "created_at" => ["type" => "string", "format" => "date-time"],
                        "updated_at" => ["type" => "string", "format" => "date-time"]
                    ]
                ],
                "Account" => [
                    "type" => "object",
                    "properties" => [
                        "id" => ["type" => "integer"],
                        "account_number" => ["type" => "string"],
                        "balance" => ["type" => "number", "format" => "float"],
                        "currency" => ["type" => "string"],
                        "status" => ["type" => "string", "enum" => ["active", "suspended", "closed"]],
                        "is_primary" => ["type" => "boolean"],
                        "created_at" => ["type" => "string", "format" => "date-time"],
                        "updated_at" => ["type" => "string", "format" => "date-time"]
                    ]
                ],
                "Transaction" => [
                    "type" => "object",
                    "properties" => [
                        "id" => ["type" => "integer"],
                        "reference" => ["type" => "string"],
                        "amount" => ["type" => "number", "format" => "float"],
                        "currency" => ["type" => "string"],
                        "type" => ["type" => "string", "enum" => ["debit", "credit"]],
                        "status" => ["type" => "string", "enum" => ["pending", "completed", "failed", "cancelled"]],
                        "description" => ["type" => "string"],
                        "created_at" => ["type" => "string", "format" => "date-time"],
                        "processed_at" => ["type" => "string", "format" => "date-time", "nullable" => true]
                    ]
                ],
                "PaymentRequest" => [
                    "type" => "object",
                    "required" => ["amount", "currency", "recipient_account"],
                    "properties" => [
                        "amount" => ["type" => "number", "format" => "float", "minimum" => 0.01, "maximum" => 1000000],
                        "currency" => ["type" => "string", "enum" => ["XOF", "EUR", "USD"]],
                        "description" => ["type" => "string", "maxLength" => 255],
                        "recipient_account" => ["type" => "string"]
                    ]
                ],
                "ApiResponse" => [
                    "type" => "object",
                    "properties" => [
                        "success" => ["type" => "boolean"],
                        "message" => ["type" => "string"],
                        "data" => ["type" => "object"],
                        "errors" => ["type" => "object"]
                    ]
                ]
            ]
        ],
        "paths" => [
            "/auth/initiate-login" => [
                "post" => [
                    "summary" => "Initier la connexion",
                    "description" => "Envoie un code OTP pour l'authentification",
                    "tags" => ["Authentification"],
                    "requestBody" => [
                        "required" => true,
                        "content" => [
                            "application/json" => [
                                "schema" => [
                                    "type" => "object",
                                    "required" => ["phone"],
                                    "properties" => [
                                        "phone" => ["type" => "string"]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    "responses" => [
                        "200" => [
                            "description" => "Code OTP envoyé",
                            "content" => [
                                "application/json" => [
                                    "schema" => [
                                        "\$ref" => "#/components/schemas/ApiResponse"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "/auth/complete-login" => [
                "post" => [
                    "summary" => "Compléter la connexion",
                    "description" => "Valide le code OTP et retourne le token d'accès",
                    "tags" => ["Authentification"],
                    "requestBody" => [
                        "required" => true,
                        "content" => [
                            "application/json" => [
                                "schema" => [
                                    "type" => "object",
                                    "required" => ["phone", "otp_code"],
                                    "properties" => [
                                        "phone" => ["type" => "string"],
                                        "otp_code" => ["type" => "string"]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    "responses" => [
                        "200" => [
                            "description" => "Connexion réussie",
                            "content" => [
                                "application/json" => [
                                    "schema" => [
                                        "\$ref" => "#/components/schemas/ApiResponse"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "/user" => [
                "get" => [
                    "summary" => "Informations utilisateur",
                    "description" => "Récupère les informations de l'utilisateur connecté",
                    "tags" => ["User"],
                    "security" => [["bearerAuth" => []]],
                    "responses" => [
                        "200" => [
                            "description" => "Informations utilisateur",
                            "content" => [
                                "application/json" => [
                                    "schema" => [
                                        "\$ref" => "#/components/schemas/User"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "/payments/initiate" => [
                "post" => [
                    "summary" => "Initier un paiement marchand",
                    "description" => "Effectue un paiement vers un marchand",
                    "tags" => ["Paiements"],
                    "security" => [["bearerAuth" => []]],
                    "requestBody" => [
                        "required" => true,
                        "content" => [
                            "application/json" => [
                                "schema" => [
                                    "type" => "object",
                                    "required" => ["amount", "currency", "merchant_id"],
                                    "properties" => [
                                        "amount" => ["type" => "number", "example" => 5000],
                                        "currency" => ["type" => "string", "enum" => ["XOF", "EUR", "USD"]],
                                        "merchant_id" => ["type" => "string", "example" => "M001"],
                                        "description" => ["type" => "string"]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    "responses" => [
                        "201" => [
                            "description" => "Paiement effectué",
                            "content" => [
                                "application/json" => [
                                    "schema" => [
                                        "\$ref" => "#/components/schemas/ApiResponse"
                                    ]
                                ]
                            ]
                        ],
                        "422" => [
                            "description" => "Erreur de validation",
                            "content" => [
                                "application/json" => [
                                    "schema" => [
                                        "\$ref" => "#/components/schemas/ApiResponse"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "/transfers/initiate" => [
                "post" => [
                    "summary" => "Initier un transfert",
                    "description" => "Effectue un transfert vers un autre compte OM PAY",
                    "tags" => ["Transferts"],
                    "security" => [["bearerAuth" => []]],
                    "requestBody" => [
                        "required" => true,
                        "content" => [
                            "application/json" => [
                                "schema" => [
                                    "type" => "object",
                                    "required" => ["amount", "currency", "recipient_account"],
                                    "properties" => [
                                        "amount" => ["type" => "number", "example" => 5000],
                                        "currency" => ["type" => "string", "enum" => ["XOF", "EUR", "USD"]],
                                        "recipient_account" => ["type" => "string", "example" => "OM221772345678"],
                                        "description" => ["type" => "string"]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    "responses" => [
                        "201" => [
                            "description" => "Transfert effectué",
                            "content" => [
                                "application/json" => [
                                    "schema" => [
                                        "\$ref" => "#/components/schemas/ApiResponse"
                                    ]
                                ]
                            ]
                        ],
                        "422" => [
                            "description" => "Erreur de validation",
                            "content" => [
                                "application/json" => [
                                    "schema" => [
                                        "\$ref" => "#/components/schemas/ApiResponse"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "/payments/status/{reference}" => [
                "get" => [
                    "summary" => "Statut de la transaction",
                    "description" => "Vérifie le statut d'une transaction (paiement ou transfert)",
                    "tags" => ["Transactions"],
                    "security" => [["bearerAuth" => []]],
                    "parameters" => [
                        [
                            "name" => "reference",
                            "in" => "path",
                            "required" => true,
                            "schema" => ["type" => "string"],
                            "description" => "Référence de la transaction"
                        ]
                    ],
                    "responses" => [
                        "200" => [
                            "description" => "Statut de la transaction",
                            "content" => [
                                "application/json" => [
                                    "schema" => [
                                        "\$ref" => "#/components/schemas/ApiResponse"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "/account/balance" => [
                "get" => [
                    "summary" => "Solde du compte",
                    "description" => "Récupère le solde du compte principal",
                    "tags" => ["Comptes"],
                    "security" => [["bearerAuth" => []]],
                    "responses" => [
                        "200" => [
                            "description" => "Solde du compte",
                            "content" => [
                                "application/json" => [
                                    "schema" => [
                                        "\$ref" => "#/components/schemas/ApiResponse"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "/account/details" => [
                "get" => [
                    "summary" => "Détails du compte",
                    "description" => "Récupère les détails complets du compte",
                    "tags" => ["Comptes"],
                    "security" => [["bearerAuth" => []]],
                    "responses" => [
                        "200" => [
                            "description" => "Détails du compte",
                            "content" => [
                                "application/json" => [
                                    "schema" => [
                                        "\$ref" => "#/components/schemas/ApiResponse"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "/accounts/create" => [
                "post" => [
                    "summary" => "Créer un compte",
                    "description" => "Crée un nouveau compte pour l'utilisateur",
                    "tags" => ["Comptes"],
                    "security" => [["bearerAuth" => []]],
                    "responses" => [
                        "201" => [
                            "description" => "Compte créé",
                            "content" => [
                                "application/json" => [
                                    "schema" => [
                                        "\$ref" => "#/components/schemas/ApiResponse"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "/transactions" => [
                "get" => [
                    "summary" => "Historique des transactions",
                    "description" => "Récupère l'historique des transactions avec pagination",
                    "tags" => ["Transactions"],
                    "security" => [["bearerAuth" => []]],
                    "parameters" => [
                        [
                            "name" => "limit",
                            "in" => "query",
                            "schema" => ["type" => "integer", "minimum" => 1, "maximum" => 100],
                            "description" => "Nombre d'éléments par page"
                        ],
                        [
                            "name" => "offset",
                            "in" => "query",
                            "schema" => ["type" => "integer", "minimum" => 0],
                            "description" => "Décalage pour la pagination"
                        ],
                        [
                            "name" => "status",
                            "in" => "query",
                            "schema" => ["type" => "string", "enum" => ["pending", "completed", "failed", "cancelled"]],
                            "description" => "Filtrer par statut"
                        ],
                        [
                            "name" => "type",
                            "in" => "query",
                            "schema" => ["type" => "string", "enum" => ["debit", "credit"]],
                            "description" => "Filtrer par type"
                        ]
                    ],
                    "responses" => [
                        "200" => [
                            "description" => "Historique des transactions",
                            "content" => [
                                "application/json" => [
                                    "schema" => [
                                        "\$ref" => "#/components/schemas/ApiResponse"
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ]);
})->name('api.docs.json');
