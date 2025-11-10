<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting OM PAY database seeding...');

        // Seed mock Orange Money users first
        $this->call(MockOrangeMoneyUsersSeeder::class);

        // Seed merchants for testing
        $this->call(MerchantSeeder::class);

        // Then seed sample transactions
        $this->call(TransactionSeeder::class);

        $this->command->info('✅ OM PAY database seeding completed!');
        $this->command->info('');
        $this->command->info('📱 Test Accounts Available:');
        $this->command->info('• Mamadou Diallo: 221771234567 (25,000.50 XOF)');
        $this->command->info('• Fatou Sow: 221772345678 (15,750.75 XOF)');
        $this->command->info('• Ibrahima Ndiaye: 221773456789 (50,000.00 XOF)');
        $this->command->info('• Aminata Ba: 221774567890 (8,750.25 XOF)');
        $this->command->info('• Cheikh Faye: 221775678901 (32,000.00 XOF)');
        $this->command->info('• Mariama Diop: 221776789012 (Suspended)');
        $this->command->info('');
        $this->command->info('🔐 Default password: password123');
        $this->command->info('📊 Sample transactions have been created');
    }
}
