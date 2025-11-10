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
 *     url="http://localhost:8000/api",
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
        $omUser = app(OrangeMoneyService::class)->checkUser($phone);

        if (!$omUser) {
            return response()->json([
                'success' => false,
                'message' => 'Numéro non enregistré dans Orange Money. Veuillez créer un compte Orange Money d\'abord.',
                'error_code' => 'USER_NOT_FOUND'
            ], 404);
        }

        // Generate OTP
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store OTP in cache for 5 minutes
        $token = Str::random(32);
        Cache::put("otp_{$token}", [
            'phone' => $phone,
            'otp' => $otp,
            'user_data' => $omUser
        ], now()->addMinutes(5));

        // In production, send SMS here
        // For demo, we'll return the OTP in response
        return response()->json([
            'success' => true,
            'message' => 'OTP envoyé avec succès',
            'data' => [
                'token' => $token,
                'otp' => $otp, // Remove this in production
                'expires_in' => 300 // 5 minutes
            ]
        ]);
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
            'token' => 'required|string',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Paramètres invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        $token = $request->token;

        // Get OTP data from cache
        $otpData = Cache::get("otp_{$token}");

        if (!$otpData) {
            return response()->json([
                'success' => false,
                'message' => 'Token expiré ou invalide',
                'error_code' => 'TOKEN_EXPIRED'
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

        // Create or update user in database
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'phone' => $phone,
                'password' => Hash::make('password123'), // Default password
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
                'is_primary' => true, // First account is primary
            ]
        );

        // Generate API token
        $token = $user->createToken('OM PAY API Token')->plainTextToken;

        // Clear OTP from cache
        Cache::forget("otp_{$token}");

        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'data' => [
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
                'token_type' => 'Bearer',
            ]
        ]);
    }
}
