<?php

namespace Database\Seeders;

use App\Models\Merchant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MerchantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding test merchants...');

        // Truncate table to avoid duplicates in production
        Merchant::truncate();

        $merchants = [
            [
                'merchant_code' => 'MARCH001',
                'name' => 'Supermarché Dakar Center',
                'description' => 'Supermarché moderne avec tous vos produits essentiels',
                'category' => 'Alimentation',
                'phone' => '221338001234',
                'email' => 'contact@dakarcemer.com',
            ],
            [
                'merchant_code' => 'MARCH002',
                'name' => 'Pharmacie Centrale',
                'description' => 'Pharmacie 24h/24 avec service de livraison',
                'category' => 'Santé',
                'phone' => '221338002345',
                'email' => 'info@pharmaciecentrale.sn',
            ],
            [
                'merchant_code' => 'MARCH003',
                'name' => 'Station Total Liberté',
                'description' => 'Station-service avec lavage auto et boutique',
                'category' => 'Carburant',
                'phone' => '221338003456',
                'email' => 'liberte@total.sn',
            ],
            [
                'merchant_code' => 'MARCH004',
                'name' => 'Restaurant Le Jardin',
                'description' => 'Restaurant gastronomique avec cuisine sénégalaise et internationale',
                'category' => 'Restauration',
                'phone' => '221338004567',
                'email' => 'reservation@lejardin.sn',
            ],
            [
                'merchant_code' => 'MARCH005',
                'name' => 'Boutique Fashion Store',
                'description' => 'Boutique de mode avec les dernières tendances',
                'category' => 'Mode',
                'phone' => '221338005678',
                'email' => 'ventes@fashionstore.sn',
            ],
            [
                'merchant_code' => 'MARCH006',
                'name' => 'École Primaire Excellence',
                'description' => 'École primaire avec programme bilingue',
                'category' => 'Éducation',
                'phone' => '221338006789',
                'email' => 'administration@ecoleexcellence.sn',
            ],
            [
                'merchant_code' => 'MARCH007',
                'name' => 'Hôtel Méridien Dakar',
                'description' => 'Hôtel 5 étoiles avec piscine et spa',
                'category' => 'Hôtellerie',
                'phone' => '221338007890',
                'email' => 'reservation@meridiendakar.sn',
            ],
            [
                'merchant_code' => 'MARCH008',
                'name' => 'Transport Dakar Express',
                'description' => 'Service de transport urbain et interurbain',
                'category' => 'Transport',
                'phone' => '221338008901',
                'email' => 'contact@dakarexpress.sn',
            ],
        ];

        foreach ($merchants as $merchantData) {
            $merchant = Merchant::updateOrCreate(
                ['merchant_code' => $merchantData['merchant_code']],
                array_merge($merchantData, [
                    'qr_code' => 'MERCHANT_' . $merchantData['merchant_code'] . '_' . now()->timestamp,
                    'is_active' => true,
                    'metadata' => [
                        'location' => 'Dakar, Sénégal',
                        'opening_hours' => '08:00-18:00',
                        'accepts_om_pay' => true,
                    ]
                ])
            );

            $this->command->info("Created/Updated merchant: {$merchant->name} (Code: {$merchant->merchant_code})");
        }

        $this->command->info('Test merchants seeded successfully!');
        $this->command->info('');
        $this->command->info('📱 Available Merchant Codes for Testing:');
        $this->command->info('• MARCH001 - Supermarché Dakar Center');
        $this->command->info('• MARCH002 - Pharmacie Centrale');
        $this->command->info('• MARCH003 - Station Total Liberté');
        $this->command->info('• MARCH004 - Restaurant Le Jardin');
        $this->command->info('• MARCH005 - Boutique Fashion Store');
        $this->command->info('• MARCH006 - École Primaire Excellence');
        $this->command->info('• MARCH007 - Hôtel Méridien Dakar');
        $this->command->info('• MARCH008 - Transport Dakar Express');
        $this->command->info('');
        $this->command->info('💳 Use these codes to test merchant payments');
    }
}
