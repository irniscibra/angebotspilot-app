<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class EmployeeInviteNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $companyName,
        public string $invitedByName,
    ) {
    }

    /**
     * Baut die Annahme-URL zum FRONTEND (analog zum bestehenden
     * Passwort-Reset-Flow): die eigentliche Signatur-Pruefung passiert
     * serverseitig ueber eine signierte Laravel-Route, aber die
     * interaktive Seite (Passwort setzen) muss im Frontend laufen. Wir
     * generieren daher den signierten API-Pfad, extrahieren dessen
     * Query-String (expires + signature) und haengen ihn an die
     * Frontend-Annahme-Seite an.
     *
     * WICHTIG: absolute=true (Default) lassen! Laravels 'signed'-
     * Middleware prueft per Default gegen die volle absolute URL
     * (inkl. Schema+Host) - wurde hier mit absolute=false relativ
     * signiert, wuerde die Signaturpruefung beim Aufruf IMMER mit 403
     * fehlschlagen, weil signierter und gepruefter String nicht
     * uebereinstimmen.
     */
    protected function inviteUrl(mixed $notifiable): string
    {
        $signedUrl = URL::temporarySignedRoute(
            'team.invite.show',
            Carbon::now()->addDays(7),
            ['user' => $notifiable->getKey()],
        );

        $queryString = parse_url($signedUrl, PHP_URL_QUERY);
        $frontendUrl = env('APP_FRONTEND_URL', 'http://localhost:9000');

        return $frontendUrl . '/#/accept-invite?user=' . $notifiable->getKey() . '&' . $queryString;
    }

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $url = $this->inviteUrl($notifiable);

        return (new MailMessage)
            ->subject('Sie wurden zu ' . $this->companyName . ' auf AngebotsPilot eingeladen')
            ->view('emails.employee-invite', [
                'url' => $url,
                'name' => $notifiable->name,
                'companyName' => $this->companyName,
                'invitedByName' => $this->invitedByName,
            ]);
    }
}
