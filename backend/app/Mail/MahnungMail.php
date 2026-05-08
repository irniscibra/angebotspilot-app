<?php

namespace App\Mail;

use App\Models\Mahnung;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;

class MahnungMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Mahnung $mahnung,
        public string $senderName,
        public ?string $replyToEmail = null,
        public ?string $pdfContent = null,
        public ?string $pdfFilename = null,
    ) {}

    public function build()
    {
        $level   = $this->mahnung->level;
        $invoice = $this->mahnung->invoice;

        $subject = match ($level) {
            1 => "Zahlungserinnerung – Rechnung {$invoice->invoice_number}",
            2 => "2. Mahnung – Rechnung {$invoice->invoice_number}",
            3 => "3. und letzte Mahnung – Rechnung {$invoice->invoice_number}",
            default => "Mahnung – Rechnung {$invoice->invoice_number}",
        };

        $mail = $this->view('emails.mahnung')
            ->from(
                config('mail.from.address', 'noreply@angebotspilot.app'),
                $this->senderName
            )
            ->subject($subject);

        if ($this->replyToEmail) {
            $mail->replyTo($this->replyToEmail, $this->senderName);
        }

        if ($this->pdfContent) {
            $mail->attachData(
                $this->pdfContent,
                $this->pdfFilename ?? $this->mahnung->mahnung_number . '.pdf',
                ['mime' => 'application/pdf']
            );
        }

        return $mail;
    }

    public function attachments(): array
    {
        return [];
    }
}
