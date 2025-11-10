<?php

namespace App\Http\Controllers;

use App\Http\Requests\InitiatePaymentRequest;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function initiatePayment(InitiatePaymentRequest $request): JsonResponse
    {
        $user = $request->user();
        $account = $user->account;

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez avoir un compte principal pour effectuer des paiements. Veuillez contacter le support.',
                'error_code' => 'ACCOUNT_REQUIRED'
            ], 403);
        }

        if ($account->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Votre compte n\'est pas actif.',
                'error_code' => 'ACCOUNT_INACTIVE'
            ], 403);
        }

        $recipientAccount = Account::where('account_number', $request->recipient_account)->first();

        if (!$recipientAccount) {
            return response()->json([
                'success' => false,
                'message' => 'Le compte destinataire n\'existe pas.',
                'error_code' => 'RECIPIENT_NOT_FOUND'
            ], 404);
        }

        if (!$account->hasSufficientBalance($request->amount)) {
            return response()->json([
                'success' => false,
                'message' => 'Solde insuffisant.',
                'error_code' => 'INSUFFICIENT_BALANCE'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Process the transfer first
            $account->debit($request->amount);
            $recipientAccount->credit($request->amount);

            // Create debit transaction
            $debitTransaction = Transaction::create([
                'user_id' => $user->id,
                'reference' => 'TXN_' . Str::upper(Str::random(12)),
                'amount' => $request->amount,
                'currency' => $request->currency,
                'type' => 'debit',
                'status' => 'completed',
                'description' => $request->description,
                'metadata' => json_encode([
                    'recipient_account' => $request->recipient_account,
                    'type' => 'transfer'
                ]),
                'processed_at' => now(),
            ]);

            // Create credit transaction for recipient
            $creditTransaction = Transaction::create([
                'user_id' => $recipientAccount->user_id,
                'reference' => 'TXN_' . Str::upper(Str::random(12)),
                'amount' => $request->amount,
                'currency' => $request->currency,
                'type' => 'credit',
                'status' => 'completed',
                'description' => $request->description,
                'metadata' => json_encode([
                    'sender_account' => $account->account_number,
                    'type' => 'transfer'
                ]),
                'processed_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Paiement effectué avec succès',
                'data' => [
                    'transaction_id' => $debitTransaction->id,
                    'reference' => $debitTransaction->reference,
                    'amount' => $debitTransaction->amount,
                    'currency' => $debitTransaction->currency,
                    'status' => $debitTransaction->status,
                    'processed_at' => $debitTransaction->processed_at,
                    'recipient' => $recipientAccount->user->name
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du traitement du paiement',
                'error_code' => 'PAYMENT_PROCESSING_ERROR'
            ], 500);
        }
    }

    public function checkPaymentStatus(Request $request, string $reference): JsonResponse
    {
        $transaction = Transaction::where('reference', $reference)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction introuvable',
                'error_code' => 'TRANSACTION_NOT_FOUND'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'reference' => $transaction->reference,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'status' => $transaction->status,
                'type' => $transaction->type,
                'description' => $transaction->description,
                'created_at' => $transaction->created_at,
                'processed_at' => $transaction->processed_at
            ]
        ]);
    }

    public function getTransactionHistory(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:0',
            'status' => ['nullable', 'string', Rule::in(['pending', 'completed', 'failed', 'cancelled'])],
            'type' => ['nullable', 'string', Rule::in(['debit', 'credit'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Paramètres de requête invalides',
                'errors' => $validator->errors(),
                'error_code' => 'INVALID_PARAMETERS'
            ], 422);
        }

        $query = Transaction::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $limit = $request->get('limit', 20);
        $offset = $request->get('offset', 0);

        $transactions = $query->skip($offset)->take($limit)->get();

        return response()->json([
            'success' => true,
            'data' => $transactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'type' => $transaction->type,
                    'status' => $transaction->status,
                    'description' => $transaction->description,
                    'created_at' => $transaction->created_at,
                    'processed_at' => $transaction->processed_at
                ];
            }),
            'meta' => [
                'total' => $query->count(),
                'limit' => $limit,
                'offset' => $offset
            ]
        ]);
    }

    public function getAccountBalance(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = $user->account;

        if (!$account) {
            return response()->json([
                'success' => false,
                'message' => 'Vous devez avoir un compte principal pour consulter votre solde. Veuillez contacter le support.',
                'error_code' => 'ACCOUNT_REQUIRED'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'account_number' => $account->account_number,
                'balance' => $account->balance,
                'currency' => $account->currency,
                'status' => $account->status,
                'last_updated' => $account->updated_at
            ]
        ]);
    }
}
