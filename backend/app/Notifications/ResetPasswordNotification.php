<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token)
    {
    }

    /**
     * Baut die Reset-URL zum FRONTEND (nicht zur API), da der Nutzer
     * dort ein neues Passwort eingeben muss - analog zur bestehenden
     * FRONTEND_URL-Konvention, die ihr vermutlich schon nutzt.
     */
    protected function resetUrl(mixed $notifiable): string
    {
               $frontendUrl = env('APP_FRONTEND_URL', 'http://localhost:9000');

                return $frontendUrl . '/#/auth/reset-password?token=' . $this->token
            . '&email=' . urlencode($notifiable->getEmailForPasswordReset());
    }

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);

        return (new MailMessage)
            ->subject('Passwort zurücksetzen – AngebotsPilot')
            ->view('emails.reset-password', [
                'url' => $url,
                'name' => $notifiable->name,
            ]);
    }
}