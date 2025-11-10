<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Send SMS notification
     */
    public function sendSms(string $phone, string $message): bool
    {
        try {
            // In development, just log the SMS
            Log::info("SMS sent to {$phone}: {$message}");

            // Simulate SMS sending delay
            sleep(rand(1, 2));

            // For testing, you can uncomment this to simulate failures
            // if (rand(1, 10) === 1) { // 10% failure rate
            //     throw new \Exception('SMS sending failed');
            // }

            return true;

        } catch (\Exception $e) {
            Log::error('SMS sending error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification for users without OM account
     */
    public function sendNoAccountNotification(string $phone): bool
    {
        $message = "Vous n'avez pas de compte Orange Money. " .
                  "Rendez-vous dans une agence Orange ou téléchargez l'app " .
                  "Orange Money pour créer votre compte et profiter de nos services.";

        return $this->sendSms($phone, $message);
    }

    /**
     * Send account suspension notification
     */
    public function sendAccountSuspendedNotification(string $phone): bool
    {
        $message = "Votre compte Orange Money est suspendu. " .
                  "Veuillez contacter le support client pour réactiver votre compte.";

        return $this->sendSms($phone, $message);
    }

    /**
     * Send transaction confirmation
     */
    public function sendTransactionConfirmation(string $phone, array $transactionData): bool
    {
        $amount = number_format($transactionData['amount'], 2, ',', ' ');
        $message = "Transaction OM PAY: {$amount} {$transactionData['currency']} " .
                  "effectuée avec succès. Référence: {$transactionData['reference']}";

        return $this->sendSms($phone, $message);
    }

    /**
     * Send low balance alert
     */
    public function sendLowBalanceAlert(string $phone, float $balance): bool
    {
        $formattedBalance = number_format($balance, 2, ',', ' ');
        $message = "Votre solde OM PAY est faible: {$formattedBalance} XOF. " .
                  "Rechargez votre compte pour continuer à utiliser nos services.";

        return $this->sendSms($phone, $message);
    }

    /**
     * Get SMS sending statistics (for monitoring)
     */
    public function getSmsStats(): array
    {
        // In a real implementation, this would query SMS provider stats
        return [
            'total_sent_today' => rand(50, 200),
            'success_rate' => 0.98,
            'average_delivery_time' => '2.5s',
        ];
    }
}