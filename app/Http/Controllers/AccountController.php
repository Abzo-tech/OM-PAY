<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AccountController extends Controller
{
    public function createAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if user already has an account
        if ($user->account) {
            return response()->json([
                'success' => false,
                'message' => 'Vous avez déjà un compte principal',
                'data' => [
                    'account' => $user->account
                ]
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'account_number' => 'required|string|unique:accounts,account_number',
            'initial_balance' => 'nullable|numeric|min:0|max:100000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create account
        $account = Account::create([
            'user_id' => $user->id,
            'account_number' => $request->account_number,
            'balance' => $request->initial_balance ?? 0,
            'currency' => 'XOF',
            'status' => 'active',
            'is_primary' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Compte créé avec succès',
            'data' => [
                'account' => [
                    'account_number' => $account->account_number,
                    'balance' => $account->balance,
                    'currency' => $account->currency,
                    'status' => $account->status,
                    'is_primary' => $account->is_primary,
                ]
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
                'message' => 'Aucun compte trouvé. Veuillez créer un compte d\'abord.',
                'error_code' => 'NO_ACCOUNT'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'account' => [
                    'id' => $account->id,
                    'account_number' => $account->account_number,
                    'balance' => $account->balance,
                    'currency' => $account->currency,
                    'status' => $account->status,
                    'is_primary' => $account->is_primary,
                    'created_at' => $account->created_at,
                ]
            ]
        ]);
    }
}
