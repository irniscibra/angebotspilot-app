<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;
use App\Models\Company;
use App\Mail\TrialEndingMail;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Trial-Ablauf Warnungs-E-Mails (täglich um 09:00 Uhr) ──
Schedule::call(function () {
    foreach ([3, 1] as $days) {
        $companies = Company::where('plan', 'trial')
            ->whereDate('trial_ends_at', now()->addDays($days)->toDateString())
            ->get();

        foreach ($companies as $company) {
            $owner = $company->users()->where('role', 'owner')->first();
            if ($owner && $owner->email) {
                try {
                    Mail::to($owner->email, $company->name)
                        ->send(new TrialEndingMail($company, $days));
                } catch (\Exception $e) {
                    \Log::error("Trial warning email failed for company {$company->id}: " . $e->getMessage());
                }
            }
        }
    }
})->dailyAt('09:00')->name('trial-warning-emails')->withoutOverlapping();
