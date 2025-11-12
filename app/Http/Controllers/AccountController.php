<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\OrangeMoneyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Comptes",
 *     description="Gestion des comptes utilisateur"
 * )
 */
class AccountController extends Controller
{
    /**
     * @OA\Get(
     *     path="/accounts/details",
     *     summary="Détails du compte",
     *     tags={"Comptes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Détails du compte",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="account_number", type="string"),
     *                 @OA\Property(property="balance", type="number"),
     *                 @OA\Property(property="currency", type="string"),
     *                 @OA\Property(property="status", type="string"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Compte introuvable")
     * )
     */
    public function getAccountDetails(Request $request): JsonResponse
    {
        $account = $request->user()->account;

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun compte trouvé',
                'error_code' => 'ACCOUNT_NOT_FOUND'
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
                'updated_at' => $account->updated_at,
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/accounts/create",
     *     summary="Créer un compte OM PAY",
     *     tags={"Comptes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone","initial_balance"},
     *             @OA\Property(property="phone", type="string", example="221771234567"),
     *             @OA\Property(property="initial_balance", type="number", example=10000),
     *             @OA\Property(property="currency", type="string", enum={"XOF","EUR","USD"}, default="XOF")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Compte créé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="account_number", type="string"),
     *                 @OA\Property(property="balance", type="number"),
     *                 @OA\Property(property="status", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=409, description="Compte déjà existant"),
     *     @OA\Response(response=422, description="Données invalides")
     * )
     */
    public function createAccount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^[0-9]{9,15}$/',
            'initial_balance' => 'required|numeric|min:0|max:100000',
            'currency' => 'nullable|string|size:3|in:XOF,EUR,USD',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Vérifier si l'utilisateur a déjà un compte
        if ($user->account) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà un compte actif',
                'error_code' => 'ACCOUNT_ALREADY_EXISTS'
            ], 409);
        }

        // Vérifier si le numéro est enregistré dans Orange Money
        $omService = app(OrangeMoneyService::class);
        $omUser = $omService->checkUser($request->phone);

        if (!$omUser) {
            return response()->json([
                'success' => false,
                'message' => 'Numéro non enregistré dans Orange Money',
                'error_code' => 'PHONE_NOT_REGISTERED'
            ], 404);
        }

        try {
            $account = Account::create([
                'user_id' => $user->id,
                'account_number' => 'OM' . $request->phone,
                'balance' => $request->initial_balance,
                'currency' => $request->currency ?? 'XOF',
                'status' => 'active',
                'is_primary' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Compte créé avec succès',
                'data' => [
                    'account_number' => $account->account_number,
                    'balance' => $account->balance,
                    'currency' => $account->currency,
                    'status' => $account->status,
                    'created_at' => $account->created_at,
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du compte',
                'error_code' => 'ACCOUNT_CREATION_ERROR'
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/accounts/{id}",
     *     summary="Supprimer un compte",
     *     tags={"Comptes"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Compte supprimé avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Suppression non autorisée"),
     *     @OA\Response(response=404, description="Compte introuvable")
     * )
     */
    public function deleteAccount(Request $request, int $id): JsonResponse
    {
        $account = Account::find($id);

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Compte introuvable',
                'error_code' => 'ACCOUNT_NOT_FOUND'
            ], 404);
        }

        // Vérifier que le compte appartient à l'utilisateur
        if ($account->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé',
                'error_code' => 'UNAUTHORIZED'
            ], 403);
        }

        // Vérifier que ce n'est pas le compte primaire
        if ($account->is_primary) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de supprimer le compte primaire',
                'error_code' => 'CANNOT_DELETE_PRIMARY_ACCOUNT'
            ], 403);
        }

        // Vérifier qu'il n'y a pas de solde
        if ($account->balance > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Veuillez d\'abord transférer tout le solde',
                'error_code' => 'ACCOUNT_HAS_BALANCE'
            ], 403);
        }

        try {
            $account->delete();

            return response()->json([
                'success' => true,
                'message' => 'Compte supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
                'error_code' => 'ACCOUNT_DELETION_ERROR'
            ], 500);
        }
    }
}
