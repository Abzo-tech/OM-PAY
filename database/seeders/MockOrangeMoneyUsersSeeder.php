<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use App\Services\OrangeMoneyService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MockOrangeMoneyUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding mock Orange Money users...');

        // Get all mock users from OrangeMoneyService
        $mockUsers = OrangeMoneyService::getAllMockUsers();

        foreach ($mockUsers as $phone => $userData) {
            // Create or update user in database
            $user = User::updateOrCreate(
                ['phone' => $phone],
                [
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'password' => Hash::make('password123'), // Required password
                    'is_verified' => true, // Mock users are verified
                ]
            );

            // Create or update account
            $account = Account::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'account_number' => $userData['account_number'],
                    'balance' => $userData['balance'],
                    'currency' => $userData['currency'],
                    'status' => $userData['status'],
                    'is_primary' => true, // All mock accounts are primary
                ]
            );

            $this->command->info("Created user: {$user->name} ({$phone}) - Account: {$account->account_number}");
        }

        $this->command->info('Mock Orange Money users seeded successfully!');
        $this->command->info('Available test phone numbers:');
        $this->command->info('• 221771234567 - 221775678901 (active accounts)');
        $this->command->info('• 221776789012 (suspended account)');
        $this->command->info('• Other numbers will trigger SMS notifications');
        $this->command->info('• Default password: password123');
    }
}
