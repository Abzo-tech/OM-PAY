<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Account;
use App\Services\OrangeMoneyService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * @OA\Info(
 *     title="OM PAY API",
 *     version="1.0.0",
 *     description="API de paiement Orange Money pour les transactions sécurisées",
 *     @OA\Contact(
 *         email="support@orange-money.sn"
 *     )
 * )
 *
 * @OA\Server(
 *     url="http://localhost:8000/api/v1",
 *     description="Serveur de développement"
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
    private OrangeMoneyService $omService;
    private SmsService $smsService;

    public function __construct()
    {
        $this->omService = new OrangeMoneyService();
        $this->smsService = new SmsService();
    }

    /**
     * @OA\Post(
     *     path="/auth/initiate-login",
     *     summary="Initier la connexion OM PAY",
     *     description="Vérifie si l'utilisateur a un compte Orange Money et génère un lien de connexion",
     *     operationId="initiateLogin",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone"},
     *             @OA\Property(property="phone", type="string", example="221771234567", description="Numéro de téléphone au format international")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lien de connexion généré avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="login_link", type="string", example="https://ompay.gos.orange.com/om-links/auth/abc123def456"),
     *             @OA\Property(property="has_om_account", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Utilisateur sans compte OM",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="has_om_account", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Erreur de validation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function initiateLogin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^[0-9]{9,15}$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Numéro de téléphone invalide',
                'errors' => $validator->errors()
            ], 422);
        }

        $phone = $request->phone;

        // Check if user exists in Orange Money database
        $omUserData = $this->omService->checkUser($phone);

        if (!$omUserData) {
            // Send SMS notification to user
            $this->smsService->sendNoAccountNotification($phone);

            return response()->json([
                'success' => false,
                'message' => 'Vous n\'avez pas de compte Orange Money. Un SMS vous a été envoyé avec les instructions.',
                'has_om_account' => false
            ], 404);
        }

        // Generate login link
        $loginToken = Str::random(32);
        $loginLink = "https://ompay.gos.orange.com/om-links/auth/{$loginToken}";

        // Store login token temporarily (you might want to use Redis/cache for this)
        cache()->put("login_token_{$loginToken}", [
            'phone' => $phone,
            'om_data' => $omUserData,
            'expires_at' => now()->addMinutes(10)
        ], 600); // 10 minutes

        return response()->json([
            'success' => true,
            'message' => 'Lien de connexion généré avec succès',
            'login_link' => $loginLink,
            'has_om_account' => true
        ]);
    }

    /**
     * @OA\Get(
     *     path="/auth/complete-login/{token}",
     *     summary="Finaliser la connexion OM PAY",
     *     description="Valide le token de connexion et synchronise les données utilisateur",
     *     operationId="completeLogin",
     *     tags={"Authentication"},
     *     @OA\Parameter(
     *         name="token",
     *         in="path",
     *         required=true,
     *         description="Token de connexion généré",
     *         @OA\Schema(type="string", example="abc123def456")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Connexion réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="phone", type="string"),
     *                 @OA\Property(property="email", type="string")
     *             ),
     *             @OA\Property(property="account", type="object",
     *                 @OA\Property(property="account_number", type="string"),
     *                 @OA\Property(property="balance", type="number", format="float"),
     *                 @OA\Property(property="currency", type="string"),
     *                 @OA\Property(property="status", type="string")
     *             ),
     *             @OA\Property(property="token", type="string"),
     *             @OA\Property(property="token_type", type="string", example="Bearer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Token invalide ou expiré",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function completeLogin(Request $request, string $token): JsonResponse
    {
        // Verify login token
        $tokenData = cache()->get("login_token_{$token}");

        if (!$tokenData || now()->isAfter($tokenData['expires_at'])) {
            return response()->json([
                'success' => false,
                'message' => 'Lien de connexion expiré ou invalide'
            ], 401);
        }

        $phone = $tokenData['phone'];
        $omUserData = $tokenData['om_data'];

        // Create or update user in our database
        $user = User::updateOrCreate(
            ['phone' => $phone],
            [
                'name' => $omUserData['name'] ?? 'Utilisateur OM',
                'email' => $omUserData['email'] ?? "{$phone}@om.temp",
                'is_verified' => true,
            ]
        );

        // Create or update account with OM data
        $account = Account::updateOrCreate(
            ['user_id' => $user->id],
            [
                'account_number' => $omUserData['account_number'],
                'balance' => $omUserData['balance'] ?? 0,
                'currency' => $omUserData['currency'] ?? 'XOF',
                'status' => 'active',
                'is_primary' => true,
            ]
        );

        // Generate API token
        $token = $user->createToken('OM Pay API Token')->plainTextToken;

        // Clear the login token
        cache()->forget("login_token_{$token}");

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'email' => $user->email,
            ],
            'account' => [
                'account_number' => $account->account_number,
                'balance' => $account->balance,
                'currency' => $account->currency,
                'status' => $account->status,
            ],
            'token' => $token,
            'token_type' => 'Bearer'
        ]);
    }

}
