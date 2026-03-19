<?php

namespace App\Mail;

use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuoteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Quote $quote,
        public string $recipientName,
        public string $senderName,
        public ?string $customMessage = null,
        public ?string $replyToEmail = null,
        public ?string $pdfContent = null,
        public ?string $pdfFilename = null,
    ) {}

    public function envelope(): Envelope
    {
        $envelope = new Envelope(
            subject: $this->quote->quote_number . ' – Angebot: ' . $this->quote->project_title,
            from: new Address(
                config('mail.from.address', 'noreply@angebotspilot.app'),
                $this->senderName
            ),
        );

        return $envelope;
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.quote',
        );
    }

    /**
     * Reply-To setzen damit Antworten an den Handwerker gehen.
     */
    public function build()
    {
        $mail = $this->view('emails.quote')
            ->subject($this->quote->quote_number . ' – Angebot: ' . $this->quote->project_title);

        if ($this->replyToEmail) {
            $mail->replyTo($this->replyToEmail, $this->senderName);
        }

        if ($this->pdfContent) {
            $mail->attachData(
                $this->pdfContent,
                $this->pdfFilename ?? $this->quote->quote_number . '.pdf',
                ['mime' => 'application/pdf']
            );
        }

        return $mail;
    }

    /**
     * Nicht envelope/content nutzen, sondern build() für volle Kontrolle.
     */
    public function attachments(): array
    {
        return [];
    }
}