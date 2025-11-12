<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use App\Services\OrangeMoneyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Transactions",
 *     description="Gestion des paiements et transferts"
 * )
 */
class TransactionController extends Controller
{
    /**
     * @OA\Post(
     *     path="/transactions",
     *     summary="Effectuer une transaction (paiement ou transfert)",
     *     tags={"Transactions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type","amount","currency"},
     *             @OA\Property(property="type", type="string", enum={"payment","transfer"}, example="payment"),
     *             @OA\Property(property="merchant_id", type="string", example="1", description="Requis pour les paiements"),
     *             @OA\Property(property="recipient_account", type="string", example="OM221772345678", description="Requis pour les transferts"),
     *             @OA\Property(property="amount", type="number", example=5000),
     *             @OA\Property(property="currency", type="string", enum={"XOF","EUR","USD"}),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Transaction effectuée avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="transaction_id", type="integer"),
     *                 @OA\Property(property="reference", type="string"),
     *                 @OA\Property(property="amount", type="number"),
     *                 @OA\Property(property="status", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, description="Solde insuffisant"),
     *     @OA\Response(response=422, description="Données invalides")
     * )
     */
    public function createTransaction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:payment,transfer',
            'amount' => 'required|numeric|min:0.01|max:1000000',
            'currency' => 'required|string|size:3|in:XOF,EUR,USD',
            'description' => 'nullable|string|max:255',
        ]);

        // Validation spécifique selon le type
        if ($request->type === 'payment') {
            $validator->addRules(['merchant_id' => 'required|exists:merchants,id']);
        } elseif ($request->type === 'transfer') {
            $validator->addRules(['recipient_account' => 'required|string']);
        }

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $account = $user->account;

        if (!$account || $account->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Compte non actif',
                'error_code' => 'ACCOUNT_INACTIVE'
            ], 403);
        }

        if (!$account->hasSufficientBalance($request->amount)) {
            return response()->json([
                'success' => false,
                'message' => 'Solde insuffisant',
                'error_code' => 'INSUFFICIENT_BALANCE'
            ], 400);
        }

        DB::beginTransaction();
        try {
            if ($request->type === 'payment') {
                // PAIEMENT MARCHAND
                $account->debit($request->amount);

                $transaction = Transaction::create([
                    'user_id' => $user->id,
                    'reference' => 'PAY_' . Str::upper(Str::random(12)),
                    'amount' => $request->amount,
                    'currency' => $request->currency,
                    'type' => 'debit',
                    'status' => 'completed',
                    'description' => $request->description,
                    'metadata' => json_encode([
                        'merchant_id' => $request->merchant_id,
                        'transaction_type' => 'payment'
                    ]),
                    'processed_at' => now(),
                ]);

                $result = [
                    'transaction_id' => $transaction->id,
                    'reference' => $transaction->reference,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'status' => $transaction->status,
                    'merchant_id' => $request->merchant_id,
                    'processed_at' => $transaction->processed_at,
                ];

                $message = 'Paiement effectué avec succès';

            } elseif ($request->type === 'transfer') {
                // TRANSFERT
                $recipientAccount = Account::where('account_number', $request->recipient_account)->first();

                if (!$recipientAccount) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Compte destinataire introuvable',
                        'error_code' => 'RECIPIENT_NOT_FOUND'
                    ], 404);
                }

                $account->debit($request->amount);
                $recipientAccount->credit($request->amount);

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
                        'transaction_type' => 'transfer'
                    ]),
                    'processed_at' => now(),
                ]);

                // Transaction crédit pour le destinataire
                Transaction::create([
                    'user_id' => $recipientAccount->user_id,
                    'reference' => 'TXN_' . Str::upper(Str::random(12)),
                    'amount' => $request->amount,
                    'currency' => $request->currency,
                    'type' => 'credit',
                    'status' => 'completed',
                    'description' => $request->description,
                    'metadata' => json_encode([
                        'sender_account' => $account->account_number,
                        'transaction_type' => 'transfer'
                    ]),
                    'processed_at' => now(),
                ]);

                $result = [
                    'transaction_id' => $debitTransaction->id,
                    'reference' => $debitTransaction->reference,
                    'amount' => $debitTransaction->amount,
                    'currency' => $debitTransaction->currency,
                    'recipient' => $recipientAccount->user->name,
                    'recipient_account' => $recipientAccount->account_number,
                ];

                $message = 'Transfert effectué avec succès';
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $result
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la transaction',
                'error_code' => 'TRANSACTION_ERROR'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/transactions",
     *     summary="Lister les transactions",
     *     tags={"Transactions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         @OA\Schema(type="string", enum={"debit","credit","payment","transfer"})
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         @OA\Schema(type="string", enum={"pending","completed","failed","cancelled"})
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=20)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         @OA\Schema(type="integer", minimum=1, default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des transactions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function listTransactions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'nullable|in:debit,credit,payment,transfer',
            'status' => 'nullable|in:pending,completed,failed,cancelled',
            'limit' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Paramètres invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Transaction::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc');

        if ($request->type) {
            if ($request->type === 'payment') {
                $query->where('metadata->type', 'payment');
            } elseif ($request->type === 'transfer') {
                $query->where('metadata->type', 'transfer');
            } else {
                $query->where('type', $request->type);
            }
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $limit = $request->get('limit', 20);
        $page = $request->get('page', 1);

        $transactions = $query->paginate($limit, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'last_page' => $transactions->lastPage(),
            ]
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/transactions/{id}",
     *     summary="Annuler une transaction",
     *     tags={"Transactions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transaction annulée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Transaction introuvable"),
     *     @OA\Response(response=403, description="Transaction non annulable")
     * )
     */
    public function cancelTransaction(Request $request, int $id): JsonResponse
    {
        $transaction = Transaction::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction introuvable',
                'error_code' => 'TRANSACTION_NOT_FOUND'
            ], 404);
        }

        // Vérifier si la transaction peut être annulée
        if ($transaction->status !== 'pending' || $transaction->created_at->diffInMinutes(now()) > 5) {
            return response()->json([
                'success' => false,
                'message' => 'Cette transaction ne peut pas être annulée',
                'error_code' => 'TRANSACTION_NOT_CANCELLABLE'
            ], 403);
        }

        DB::beginTransaction();
        try {
            // Annuler la transaction
            $transaction->update(['status' => 'cancelled']);

            // Remettre les fonds si c'était un débit
            if ($transaction->type === 'debit') {
                $account = $request->user()->account;
                if ($account) {
                    $account->credit($transaction->amount);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaction annulée avec succès'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'annulation',
                'error_code' => 'CANCEL_ERROR'
            ], 500);
        }
    }
}