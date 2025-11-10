<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountRequiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $action;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $action = 'create_account')
    {
        $this->action = $action;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Action requise : Création de compte Orange Money')
            ->greeting('Bonjour ' . $notifiable->name . ',');

        switch ($this->action) {
            case 'create_account':
                $message->line('Vous devez créer un compte Orange Money pour utiliser nos services de paiement.')
                    ->line('Veuillez vous connecter à votre application et suivre les étapes de création de compte.')
                    ->action('Créer mon compte', url('/account/create'))
                    ->line('Une fois votre compte créé, vous pourrez effectuer des paiements et transferts.');
                break;

            case 'contact_support':
                $message->line('Votre compte Orange Money nécessite une vérification.')
                    ->line('Veuillez contacter notre support client pour activer votre compte.')
                    ->action('Contacter le support', url('/support'))
                    ->line('Nous vous répondrons dans les plus brefs délais.');
                break;

            default:
                $message->line('Une action est requise sur votre compte Orange Money.')
                    ->action('Voir les détails', url('/account'));
        }

        return $message->salutation('Cordialement,')
            ->salutation('L\'équipe Orange Money');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Action requise sur votre compte',
            'message' => $this->getMessageText(),
            'action' => $this->action,
            'type' => 'account_requirement',
            'action_url' => $this->getActionUrl(),
        ];
    }

    protected function getMessageText(): string
    {
        switch ($this->action) {
            case 'create_account':
                return 'Vous devez créer un compte Orange Money pour utiliser nos services.';
            case 'contact_support':
                return 'Votre compte nécessite une vérification. Contactez le support.';
            default:
                return 'Une action est requise sur votre compte Orange Money.';
        }
    }

    protected function getActionUrl(): string
    {
        switch ($this->action) {
            case 'create_account':
                return '/account/create';
            case 'contact_support':
                return '/support';
            default:
                return '/account';
        }
    }
}
