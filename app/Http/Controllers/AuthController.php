<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\User;
use App\Services\OrangeMoneyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Passport\Client;

/**
 * @OA\Info(
 *     title="OM PAY API",
 *     version="1.0.0",
 *     description="API de paiement Orange Money pour les transferts et paiements",
 *     @OA\Contact(
 *         email="contact@om-pay.sn"
 *     )
 * )
 *
 * @OA\Server(
 *     url="https://om-pay-api-1.onrender.com/api",
 *     description="Serveur de production"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/auth/initiate-login",
     *     summary="Initier la connexion avec numéro de téléphone",
     *     tags={"Authentification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone"},
     *             @OA\Property(property="phone", type="string", example="221771234567", description="Numéro de téléphone Orange Money")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP envoyé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="token", type="string"),
     *                 @OA\Property(property="otp", type="string"),
     *                 @OA\Property(property="expires_in", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Numéro invalide")
     * )
     */
public function initiateLogin(Request $request): JsonResponse
{
    \Log::info('🔥 [initiate-login] Endpoint called', [
        'input' => $request->all(),
        'ip' => $request->ip(),
        'user_agent' => $request->header('User-Agent')
    ]);
    

    try {
        
        // Étape 1️⃣ : validation du numéro
        $phone = $request->input('phone');

        // Handle phone as array (take first element if array)
        if (is_array($phone)) {
            $phone = $phone[0] ?? null;
        }

        // Ensure phone is string
        $phone = (string) $phone;

        if (!$phone || !preg_match('/^[0-9]{9,15}$/', $phone)) {
            \Log::warning('⚠️ [initiate-login] Invalid phone format', ['phone' => $phone]);
            return response()->json([
                'success' => false,
                'message' => 'Numéro de téléphone invalide'
            ], 422);
        }
        \Log::info('✅ [initiate-login] Phone validated', ['phone' => $phone]);

        // Étape 2️⃣ : vérification utilisateur OM
        \Log::info('🔎 [initiate-login] Checking OrangeMoneyService...');
        $omService = app(OrangeMoneyService::class);

        if (!method_exists($omService, 'checkUser')) {
            \Log::error('❌ [initiate-login] Method checkUser missing in OrangeMoneyService');
            return response()->json([
                'success' => false,
                'message' => 'Erreur interne du serveur (service manquant)'
            ], 500);
        }

        $omUser = $omService->checkUser($phone);
        \Log::info('📞 [initiate-login] OM User response', ['response' => $omUser]);

        if (!$omUser) {
            \Log::warning('🚫 [initiate-login] User not found in OrangeMoneyService', ['phone' => $phone]);
            return response()->json([
                'success' => false,
                'message' => 'Numéro non enregistré dans Orange Money'
            ], 404);
        }

        // Sanitize user data to ensure proper types
        $omUser = $this->sanitizeUserData($omUser);

        // Étape 3️⃣ : génération OTP
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $sessionToken = Str::random(32);
        \Log::info('🔢 [initiate-login] OTP generated', [
            'otp' => $otp,
            'session_token' => $sessionToken
        ]);

        // Étape 4️⃣ : stockage OTP dans le cache
        try {
            Cache::put("otp_{$sessionToken}", [
                'phone' => $phone,
                'otp' => $otp,
                'user_data' => $omUser
            ], now()->addMinutes(5));
            \Log::info('💾 [initiate-login] OTP stored in cache successfully');
        } catch (\Exception $cacheError) {
            \Log::error('❌ [initiate-login] Cache storage failed', ['error' => $cacheError->getMessage()]);
        }

        // Étape 5️⃣ : réponse finale
        \Log::info('✅ [initiate-login] Process completed successfully for phone', ['phone' => $phone]);

        return response()->json([
            'success' => true,
            'message' => 'OTP envoyé avec succès',
            'data' => [
                'session_token' => $sessionToken,
                'otp' => $otp, // à masquer plus tard
                'expires_in' => 300
            ]
        ]);
    } catch (\Exception $e) {
        \Log::error('💥 [initiate-login] Unhandled exception', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Erreur interne du serveur',
            'debug' => config('app.debug') ? $e->getMessage() : null
        ], 500);
    }
}


    /**
     * @OA\Post(
     *     path="/auth/complete-login",
     *     summary="Compléter la connexion avec OTP",
     *     tags={"Authentification"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"token","otp"},
     *             @OA\Property(property="token", type="string", description="Token reçu lors de l'initiation"),
     *             @OA\Property(property="otp", type="string", example="123456", description="Code OTP à 6 chiffres")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Connexion réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="phone", type="string"),
     *                     @OA\Property(property="email", type="string")
     *                 ),
     *                 @OA\Property(property="account", type="object",
     *                     @OA\Property(property="account_number", type="string"),
     *                     @OA\Property(property="balance", type="number"),
     *                     @OA\Property(property="currency", type="string")
     *                 ),
     *                 @OA\Property(property="token", type="string"),
     *                 @OA\Property(property="token_type", type="string", example="Bearer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="OTP invalide ou expiré"),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function completeLogin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_token' => 'required|string',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Paramètres invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        $sessionToken = $request->session_token;

        // Get OTP data from cache
        $otpData = Cache::get("otp_{$sessionToken}");

        if (!$otpData) {
            return response()->json([
                'success' => false,
                'message' => 'Session expirée ou invalide',
                'error_code' => 'SESSION_EXPIRED'
            ], 401);
        }

        if ($otpData['otp'] !== $request->otp) {
            return response()->json([
                'success' => false,
                'message' => 'OTP incorrect',
                'error_code' => 'INVALID_OTP'
            ], 401);
        }

        $phone = $otpData['phone'];
        $userData = $otpData['user_data'];

        // Ensure userData is properly sanitized
        $userData = $this->sanitizeUserData($userData);

        // Create or update user in database
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'phone' => $phone,
                'password' => Hash::make(Str::random(16)), // Random password
                'is_verified' => true,
            ]);
        } else {
            // Update user data if needed
            $user->update([
                'name' => $userData['name'],
                'is_verified' => true,
            ]);
        }

        // Create account if it doesn't exist
        $account = Account::firstOrCreate(
            ['user_id' => $user->id],
            [
                'account_number' => $userData['account_number'],
                'balance' => $userData['balance'],
                'currency' => $userData['currency'],
                'status' => $userData['status'],
                'is_primary' => true,
            ]
        );

        // Create Passport tokens
        $accessToken = $user->createToken('OM PAY Access Token');
        $refreshToken = $user->createToken('OM PAY Refresh Token');

        // Clear OTP from cache
        Cache::forget("otp_{$sessionToken}");

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'data' => [
                'access_token' => $accessToken->accessToken,
                'refresh_token' => $refreshToken->accessToken,
                'token_type' => 'Bearer',
                'expires_in' => 900, // 15 minutes for access token
                'user_id' => $user->id,
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/auth/user",
     *     summary="Informations de l'utilisateur connecté",
     *     tags={"Authentification"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Informations utilisateur",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="phone", type="string"),
     *                     @OA\Property(property="email", type="string"),
     *                     @OA\Property(property="is_verified", type="boolean")
     *                 ),
     *                 @OA\Property(property="account", type="object",
     *                     @OA\Property(property="account_number", type="string"),
     *                     @OA\Property(property="balance", type="number"),
     *                     @OA\Property(property="currency", type="string"),
     *                     @OA\Property(property="status", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function getUser(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = $user->account;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'email' => $user->email,
                    'is_verified' => $user->is_verified,
                ],
                'account' => $account ? [
                    'account_number' => $account->account_number,
                    'balance' => $account->balance,
                    'currency' => $account->currency,
                    'status' => $account->status,
                ] : null,
            ]
        ]);
    }

    /**
     * Issue OAuth2 token (Passport)
     */
    public function issueToken(Request $request): JsonResponse
    {
        $request->validate([
            'grant_type' => 'required|string',
            'client_id' => 'required|integer',
            'client_secret' => 'required|string',
            'username' => 'required_when:grant_type,password',
            'password' => 'required_when:grant_type,password',
            'refresh_token' => 'required_when:grant_type,refresh_token',
        ]);

        // This would normally be handled by Passport routes
        // But we're implementing a custom endpoint for simplicity
        return response()->json([
            'error' => 'invalid_request',
            'message' => 'Use the standard OAuth2 flow'
        ], 400);
    }

    /**
     * @OA\Post(
     *     path="/auth/refresh-token",
     *     summary="Rafraîchir le token d'accès",
     *     tags={"Authentification"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Token rafraîchi avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="access_token", type="string"),
     *                 @OA\Property(property="token_type", type="string", example="Bearer"),
     *                 @OA\Property(property="expires_in", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Token invalide")
     * )
     */
    public function refreshToken(Request $request): JsonResponse
    {
        $user = $request->user();

        // Revoke current token
        $request->user()->token()->revoke();

        // Create new token
        $newToken = $user->createToken('OM PAY Access Token');

        return response()->json([
            'success' => true,
            'message' => 'Token rafraîchi avec succès',
            'data' => [
                'access_token' => $newToken->accessToken,
                'token_type' => 'Bearer',
                'expires_in' => 900, // 15 minutes
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/auth/logout",
     *     summary="Déconnexion de l'utilisateur",
     *     tags={"Authentification"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Déconnexion réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Non authentifié")
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke current token
        $request->user()->token()->revoke();

        return response()->json([
            'success' => true,
            'message' => 'Déconnexion réussie'
        ]);
    }

    /**
     * Sanitize user data to ensure proper types for database operations and caching
     */
    private function sanitizeUserData(array $userData): array
    {
        return [
            'name' => (string) ($userData['name'] ?? 'Unknown User'),
            'email' => (string) ($userData['email'] ?? ''),
            'account_number' => (string) ($userData['account_number'] ?? ''),
            'balance' => (float) ($userData['balance'] ?? 0.00),
            'currency' => (string) ($userData['currency'] ?? 'XOF'),
            'status' => (string) ($userData['status'] ?? 'active'),
            'created_at' => (string) ($userData['created_at'] ?? now()->format('Y-m-d')),
        ];
    }
}
