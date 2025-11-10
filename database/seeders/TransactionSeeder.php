<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding sample transactions...');

        // Get all accounts
        $accounts = Account::where('status', 'active')->get();

        if ($accounts->isEmpty()) {
            $this->command->warn('No active accounts found. Run MockOrangeMoneyUsersSeeder first.');
            return;
        }

        // Create sample transactions between accounts
        $transactionData = [
            [
                'sender_account' => 'OM221771234567', // Mamadou Diallo
                'receiver_account' => 'OM221772345678', // Fatou Sow
                'amount' => 5000.00,
                'description' => 'Paiement pour services',
                'status' => 'completed',
                'created_days_ago' => 7,
            ],
            [
                'sender_account' => 'OM221773456789', // Ibrahima Ndiaye
                'receiver_account' => 'OM221774567890', // Aminata Ba
                'amount' => 2500.00,
                'description' => 'Transfert familial',
                'status' => 'completed',
                'created_days_ago' => 5,
            ],
            [
                'sender_account' => 'OM221775678901', // Cheikh Faye
                'receiver_account' => 'OM221771234567', // Mamadou Diallo
                'amount' => 10000.00,
                'description' => 'Remboursement dette',
                'status' => 'completed',
                'created_days_ago' => 3,
            ],
            [
                'sender_account' => 'OM221772345678', // Fatou Sow
                'receiver_account' => 'OM221773456789', // Ibrahima Ndiaye
                'amount' => 7500.00,
                'description' => 'Paiement loyer',
                'status' => 'completed',
                'created_days_ago' => 2,
            ],
            [
                'sender_account' => 'OM221774567890', // Aminata Ba
                'receiver_account' => 'OM221775678901', // Cheikh Faye
                'amount' => 3000.00,
                'description' => 'Achat en ligne',
                'status' => 'completed',
                'created_days_ago' => 1,
            ],
            [
                'sender_account' => 'OM221771234567', // Mamadou Diallo
                'receiver_account' => 'OM221773456789', // Ibrahima Ndiaye
                'amount' => 15000.00,
                'description' => 'Transfert d\'argent',
                'status' => 'pending',
                'created_days_ago' => 0,
            ],
        ];

        foreach ($transactionData as $data) {
            $senderAccount = Account::where('account_number', $data['sender_account'])->first();
            $receiverAccount = Account::where('account_number', $data['receiver_account'])->first();

            if (!$senderAccount || !$receiverAccount) {
                $this->command->warn("Account not found: {$data['sender_account']} or {$data['receiver_account']}");
                continue;
            }

            // Create debit transaction
            $debitTransaction = Transaction::create([
                'user_id' => $senderAccount->user_id,
                'reference' => 'TXN_' . Str::upper(Str::random(12)),
                'amount' => $data['amount'],
                'currency' => 'XOF',
                'type' => 'debit',
                'status' => $data['status'],
                'description' => $data['description'],
                'metadata' => json_encode([
                    'recipient_account' => $receiverAccount->account_number,
                    'type' => 'transfer'
                ]),
                'processed_at' => $data['status'] === 'completed' ? now()->subDays($data['created_days_ago']) : null,
                'created_at' => now()->subDays($data['created_days_ago']),
                'updated_at' => now()->subDays($data['created_days_ago']),
            ]);

            // Create credit transaction
            $creditTransaction = Transaction::create([
                'user_id' => $receiverAccount->user_id,
                'reference' => 'TXN_' . Str::upper(Str::random(12)),
                'amount' => $data['amount'],
                'currency' => 'XOF',
                'type' => 'credit',
                'status' => $data['status'],
                'description' => $data['description'],
                'metadata' => json_encode([
                    'sender_account' => $senderAccount->account_number,
                    'type' => 'transfer'
                ]),
                'processed_at' => $data['status'] === 'completed' ? now()->subDays($data['created_days_ago']) : null,
                'created_at' => now()->subDays($data['created_days_ago']),
                'updated_at' => now()->subDays($data['created_days_ago']),
            ]);

            $this->command->info("Created transaction: {$debitTransaction->reference} - {$data['amount']} XOF from {$senderAccount->account_number} to {$receiverAccount->account_number}");
        }

        $this->command->info('Sample transactions seeded successfully!');
    }
}
