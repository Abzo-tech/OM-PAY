<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class OrangeMoneyService
{
    /**
     * Mock database of OM PAY users
     * In production, this would be replaced with actual Orange Money API calls
     */
    private static array $mockUsers = [
        // Test users with OM PAY accounts
        '221771234567' => [
            'name' => 'Mamadou Diallo',
            'email' => 'mamadou.diallo@email.com',
            'account_number' => 'OM221771234567',
            'balance' => 25000.50,
            'currency' => 'XOF',
            'status' => 'active',
            'created_at' => '2023-01-15',
        ],
        '221772345678' => [
            'name' => 'Fatou Sow',
            'email' => 'fatou.sow@email.com',
            'account_number' => 'OM221772345678',
            'balance' => 15750.75,
            'currency' => 'XOF',
            'status' => 'active',
            'created_at' => '2023-03-22',
        ],
        '221773456789' => [
            'name' => 'Ibrahima Ndiaye',
            'email' => 'ibrahima.ndiaye@email.com',
            'account_number' => 'OM221773456789',
            'balance' => 50000.00,
            'currency' => 'XOF',
            'status' => 'active',
            'created_at' => '2023-05-10',
        ],
        '221774567890' => [
            'name' => 'Aminata Ba',
            'email' => 'aminata.ba@email.com',
            'account_number' => 'OM221774567890',
            'balance' => 8750.25,
            'currency' => 'XOF',
            'status' => 'active',
            'created_at' => '2023-07-08',
        ],
        '221775678901' => [
            'name' => 'Cheikh Faye',
            'email' => 'cheikh.faye@email.com',
            'account_number' => 'OM221775678901',
            'balance' => 32000.00,
            'currency' => 'XOF',
            'status' => 'active',
            'created_at' => '2023-09-14',
        ],
        // Users with suspended accounts
        '221776789012' => [
            'name' => 'Mariama Diop',
            'email' => 'mariama.diop@email.com',
            'account_number' => 'OM221776789012',
            'balance' => 0.00,
            'currency' => 'XOF',
            'status' => 'suspended',
            'created_at' => '2023-11-20',
        ],
    ];

    /**
     * Check if user exists in Orange Money database
     */
    public function checkUser(string $phone): ?array
    {
        // Simulate API delay
        sleep(rand(1, 3));

        // Check if phone exists in mock database
        if (isset(self::$mockUsers[$phone])) {
            $userData = self::$mockUsers[$phone];

            // Simulate real-time balance updates (slight variations)
            if ($userData['status'] === 'active') {
                $userData['balance'] = $this->simulateBalanceUpdate($userData['balance']);
            }

            return $userData;
        }

        return null;
    }

    /**
     * Get user account details
     */
    public function getUserAccount(string $phone): ?array
    {
        $userData = $this->checkUser($phone);

        if (!$userData) {
            return null;
        }

        return [
            'account_number' => $userData['account_number'],
            'balance' => $userData['balance'],
            'currency' => $userData['currency'],
            'status' => $userData['status'],
            'created_at' => $userData['created_at'],
        ];
    }

    /**
     * Update user balance (for transactions)
     */
    public function updateBalance(string $phone, float $newBalance): bool
    {
        if (!isset(self::$mockUsers[$phone])) {
            return false;
        }

        // In a real implementation, this would update the Orange Money database
        self::$mockUsers[$phone]['balance'] = $newBalance;

        return true;
    }

    /**
     * Simulate balance updates for testing
     */
    private function simulateBalanceUpdate(float $currentBalance): float
    {
        // Simulate small balance changes (±5%)
        $change = $currentBalance * (mt_rand(-50, 50) / 1000); // -5% to +5%
        $newBalance = $currentBalance + $change;

        return max(0, round($newBalance, 2)); // Ensure balance doesn't go negative
    }

    /**
     * Generate mock account number for new users
     */
    public static function generateAccountNumber(string $phone): string
    {
        return 'OM' . $phone;
    }

    /**
     * Add a new user to the mock database (for testing)
     */
    public static function addMockUser(string $phone, array $userData): void
    {
        self::$mockUsers[$phone] = array_merge([
            'name' => 'Test User',
            'email' => $phone . '@test.om',
            'account_number' => self::generateAccountNumber($phone),
            'balance' => 0.00,
            'currency' => 'XOF',
            'status' => 'active',
            'created_at' => now()->format('Y-m-d'),
        ], $userData);
    }

    /**
     * Get all mock users (for testing/debugging)
     */
    public static function getAllMockUsers(): array
    {
        return self::$mockUsers;
    }
}