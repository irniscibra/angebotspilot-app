<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TrialEndingMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Company $company,
        public int $daysLeft,
    ) {}

    public function build()
    {
        return $this->view('emails.trial-ending')
            ->subject("Ihr AngebotsPilot-Testzeitraum endet in {$this->daysLeft} " . ($this->daysLeft === 1 ? 'Tag' : 'Tagen'));
    }
}
