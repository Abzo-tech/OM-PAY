<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Account Management",
 *     description="Création et gestion des comptes OM PAY"
 * )
 */

class AccountController extends Controller
{
    /**
     * @OA\Post(
     *     path="/accounts/create",
     *     summary="Créer un compte OM PAY",
     *     description="Crée un nouveau compte Orange Money pour l'utilisateur authentifié",
     *     operationId="createAccount",
     *     tags={"Account Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="phone", type="string", example="221771234567", description="Numéro de téléphone (optionnel si déjà défini)"),
     *             @OA\Property(property="currency", type="string", enum={"XOF", "EUR", "USD"}, example="XOF", description="Devise préférée")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Compte créé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="account_number", type="string"),
     *                 @OA\Property(property="balance", type="number", format="float"),
     *                 @OA\Property(property="currency", type="string"),
     *                 @OA\Property(property="status", type="string"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Compte déjà existant",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function createAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if user already has an account
        if ($user->account) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà un compte Orange Money actif.'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^[0-9]{9,15}$/|unique:users,phone',
            'currency' => 'nullable|string|size:3|in:XOF,EUR,USD',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation échouée',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update user phone if provided
        if ($request->has('phone')) {
            $user->update(['phone' => $request->phone]);
        }

        // Generate unique account number
        do {
            $accountNumber = 'OM' . strtoupper(Str::random(10));
        } while (Account::where('account_number', $accountNumber)->exists());

        // Create account
        $account = Account::create([
            'user_id' => $user->id,
            'account_number' => $accountNumber,
            'balance' => 0,
            'currency' => $request->get('currency', 'XOF'),
            'status' => 'active',
            'is_primary' => true, // Keep for backward compatibility
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Votre compte Orange Money a été créé avec succès.',
            'data' => [
                'account_number' => $account->account_number,
                'balance' => $account->balance,
                'currency' => $account->currency,
                'status' => $account->status,
                'created_at' => $account->created_at
            ]
        ], 201);
    }

    public function getAccountDetails(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = $user->account;

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun compte trouvé. Veuillez créer un compte Orange Money.',
                'action_required' => 'create_account'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'account_number' => $account->account_number,
                'balance' => $account->balance,
                'currency' => $account->currency,
                'status' => $account->status,
                'created_at' => $account->created_at,
                'updated_at' => $account->updated_at
            ]
        ]);
    }

    public function checkAccountStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $hasAccount = $user->account ? true : false;

        return response()->json([
            'success' => true,
            'data' => [
                'has_account' => $hasAccount,
                'account_status' => $hasAccount ? $user->account->status : null,
                'account_number' => $hasAccount ? $user->account->account_number : null,
                'needs_account_creation' => !$hasAccount
            ]
        ]);
    }
}
