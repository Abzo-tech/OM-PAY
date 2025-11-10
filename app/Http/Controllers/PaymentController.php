<?php

namespace App\Http\Controllers;

use App\Http\Requests\InitiatePaymentRequest;
use App\Models\Account;
use App\Models\Transaction;
use App\Notifications\AccountRequiredNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Payments",
 *     description="Opérations de paiement et transactions"
 * )
 * @OA\Tag(
 *     name="Accounts",
 *     description="Gestion des comptes utilisateur"
 * )
 * @OA\Tag(
 *     name="Transactions",
 *     description="Historique et détails des transactions"
 * )
 */

class PaymentController extends Controller
{
    /**
     * @OA\Post(
     *     path="/payments/initiate",
     *     summary="Initier un paiement",
     *     description="Effectue un transfert d'argent vers un autre compte OM PAY",
     *     operationId="initiatePayment",
     *     tags={"Payments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount", "currency", "recipient_account"},
     *             @OA\Property(property="amount", type="number", format="float", example=5000.00, description="Montant à transférer"),
     *             @OA\Property(property="currency", type="string", enum={"XOF", "EUR", "USD"}, example="XOF", description="Devise"),
     *             @OA\Property(property="description", type="string", example="Paiement pour services", description="Description optionnelle"),
     *             @OA\Property(property="recipient_account", type="string", example="OM221772345678", description="Numéro de compte destinataire")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Paiement initié avec succès",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="transaction_id", type="integer"),
     *                 @OA\Property(property="reference", type="string"),
     *                 @OA\Property(property="amount", type="number", format="float"),
     *                 @OA\Property(property="currency", type="string"),
     *                 @OA\Property(property="status", type="string"),
     *                 @OA\Property(property="processed_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Compte requis ou inactif",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="action_required", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Solde insuffisant",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Insufficient balance")
     *         )
     *     )
     * )
     */
    public function initiatePayment(InitiatePaymentRequest $request): JsonResponse
    {

        $user = $request->user();
        $account = $user->account;

        if (!$account) {
            // Send notification to user
            $user->notify(new AccountRequiredNotification('create_account'));

            return response()->json([
                'success' => false,
                'message' => 'Vous devez avoir un compte Orange Money actif pour utiliser cette fonctionnalité. Un email de notification vous a été envoyé.',
                'action_required' => 'create_account'
            ], 403);
        }

        if ($account->status !== 'active') {
            // Send notification to user
            $user->notify(new AccountRequiredNotification('contact_support'));

            return response()->json([
                'success' => false,
                'message' => 'Votre compte n\'est pas actif. Veuillez contacter le support. Un email de notification vous a été envoyé.',
                'action_required' => 'contact_support'
            ], 403);
        }

        $recipientAccount = Account::where('account_number', $request->recipient_account)->first();

        if (!$recipientAccount) {
            return response()->json([
                'success' => false,
                'message' => 'Recipient account not found'
            ], 404);
        }

        if (!$account->hasSufficientBalance($request->amount)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Create debit transaction
            $debitTransaction = Transaction::create([
                'user_id' => $user->id,
                'reference' => 'TXN_' . Str::upper(Str::random(12)),
                'amount' => $request->amount,
                'currency' => $request->currency,
                'type' => 'debit',
                'status' => 'pending',
                'description' => $request->description,
                'metadata' => [
                    'recipient_account' => $request->recipient_account,
                    'type' => 'transfer'
                ]
            ]);

            // Create credit transaction for recipient
            $creditTransaction = Transaction::create([
                'user_id' => $recipientAccount->user_id,
                'reference' => 'TXN_' . Str::upper(Str::random(12)),
                'amount' => $request->amount,
                'currency' => $request->currency,
                'type' => 'credit',
                'status' => 'pending',
                'description' => $request->description,
                'metadata' => [
                    'sender_account' => $account->account_number,
                    'type' => 'transfer'
                ]
            ]);

            // Process the transfer
            $account->debit($request->amount);
            $recipientAccount->credit($request->amount);

            // Update transaction statuses
            $debitTransaction->update(['status' => 'completed', 'processed_at' => now()]);
            $creditTransaction->update(['status' => 'completed', 'processed_at' => now()]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment initiated successfully',
                'data' => [
                    'transaction_id' => $debitTransaction->id,
                    'reference' => $debitTransaction->reference,
                    'amount' => $debitTransaction->amount,
                    'currency' => $debitTransaction->currency,
                    'status' => $debitTransaction->status,
                    'processed_at' => $debitTransaction->processed_at
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/payments/status/{reference}",
     *     summary="Vérifier le statut d'une transaction",
     *     description="Récupère les détails et le statut d'une transaction par sa référence",
     *     operationId="checkPaymentStatus",
     *     tags={"Payments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="reference",
     *         in="path",
     *         required=true,
     *         description="Référence de la transaction",
     *         @OA\Schema(type="string", example="TXN_ABC123DEF456")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Détails de la transaction",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="reference", type="string"),
     *                 @OA\Property(property="amount", type="number", format="float"),
     *                 @OA\Property(property="currency", type="string"),
     *                 @OA\Property(property="status", type="string", enum={"pending", "completed", "failed", "cancelled"}),
     *                 @OA\Property(property="type", type="string", enum={"debit", "credit"}),
     *                 @OA\Property(property="description", type="string"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="processed_at", type="string", format="date-time", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Transaction non trouvée",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function checkPaymentStatus(Request $request, string $reference): JsonResponse
    {
        $transaction = Transaction::where('reference', $reference)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
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

    /**
     * @OA\Get(
     *     path="/transactions",
     *     summary="Historique des transactions",
     *     description="Récupère l'historique paginé des transactions de l'utilisateur",
     *     operationId="getTransactionHistory",
     *     tags={"Transactions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Nombre d'éléments par page (max 100)",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=20)
     *     ),
     *     @OA\Parameter(
     *         name="offset",
     *         in="query",
     *         description="Offset pour la pagination",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=0, default=0)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filtrer par statut",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending", "completed", "failed", "cancelled"})
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filtrer par type",
     *         required=false,
     *         @OA\Schema(type="string", enum={"debit", "credit"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Liste des transactions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="reference", type="string"),
     *                     @OA\Property(property="amount", type="number", format="float"),
     *                     @OA\Property(property="currency", type="string"),
     *                     @OA\Property(property="type", type="string"),
     *                     @OA\Property(property="status", type="string"),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="processed_at", type="string", format="date-time", nullable=true)
     *                 )
     *             ),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="limit", type="integer"),
     *                 @OA\Property(property="offset", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function getTransactionHistory(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if user has account
        $account = $user->account;
        if (!$account) {
            // Send notification to user
            $user->notify(new AccountRequiredNotification('create_account'));

            return response()->json([
                'success' => false,
                'message' => 'Vous devez avoir un compte Orange Money actif pour consulter votre historique de transactions. Un email de notification vous a été envoyé.',
                'action_required' => 'create_account'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:0',
            'status' => ['nullable', 'string', Rule::in(['pending', 'completed', 'failed', 'cancelled'])],
            'type' => ['nullable', 'string', Rule::in(['debit', 'credit'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Transaction::where('user_id', $user->id)
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

    /**
     * @OA\Get(
     *     path="/account/balance",
     *     summary="Consulter le solde du compte",
     *     description="Récupère le solde actuel et les informations du compte",
     *     operationId="getAccountBalance",
     *     tags={"Accounts"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Informations du compte",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="account_number", type="string"),
     *                 @OA\Property(property="balance", type="number", format="float"),
     *                 @OA\Property(property="currency", type="string"),
     *                 @OA\Property(property="status", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Compte requis",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="action_required", type="string")
     *         )
     *     )
     * )
     */
    public function getAccountBalance(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = $user->account;

        if (!$account) {
            // Send notification to user
            $request->user()->notify(new AccountRequiredNotification('create_account'));

            return response()->json([
                'success' => false,
                'message' => 'Vous devez avoir un compte Orange Money actif pour consulter votre solde. Un email de notification vous a été envoyé.',
                'action_required' => 'create_account'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'account_number' => $account->account_number,
                'balance' => $account->balance,
                'currency' => $account->currency,
                'status' => $account->status
            ]
        ]);
    }
}
