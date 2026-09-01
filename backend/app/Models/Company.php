<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'trade',
        'address_street',
        'address_zip',
        'address_city',
        'phone',
        'email',
        'website',
        'tax_id',
        'trade_register',
        'logo_path',
        'primary_color',
        'default_vat_rate',
        'default_hourly_rate',
        'currency',
        'quote_validity_days',
        'quote_prefix',
        'next_quote_number',
        'invoice_prefix',
        'next_invoice_number',
        'default_payment_days',
        'mahnung_prefix',
        'next_mahnung_number',
        'mahnung_fee_level1',
        'mahnung_fee_level2',
        'mahnung_fee_level3',
        'mahnung_interest_rate',
        'mahnung_days_level1',
        'mahnung_days_level2',
        'mahnung_days_level3',
        'bank_name',
        'bank_iban',
        'bank_bic',
        'bank_account_holder',
        'is_small_business',
        'plan',
        'employee_seats_purchased',
        'feedback_widget_enabled',
        'trial_ends_at',
        'subscription_started_at',
        'cancelled_at',
        'access_until',
        'trial_quotes_used',
       'stripe_customer_id',
        'stripe_subscription_id',
        'current_period_end',
    ];

    protected $casts = [
        'default_vat_rate' => 'decimal:2',
        'default_hourly_rate' => 'decimal:2',
        'trial_ends_at' => 'datetime',
        'is_small_business'    => 'boolean',
        'feedback_widget_enabled' => 'boolean',
        'mahnung_fee_level1'   => 'decimal:2',
        'mahnung_fee_level2'   => 'decimal:2',
        'mahnung_fee_level3'   => 'decimal:2',
        'mahnung_interest_rate' => 'decimal:2',
        'trial_ends_at' => 'datetime',
        'subscription_started_at' => 'datetime',
        'cancelled_at' => 'datetime',
         'access_until' => 'datetime',
        'current_period_end' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Company $company) {
            if (empty($company->slug)) {
                $company->slug = Str::slug($company->name);
            }
            if (empty($company->trial_ends_at)) {
                $company->trial_ends_at = now()->addDays(14);
            }
        });
    }

    // ---- Relationships ----

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function owner()
    {
        return $this->users()->where('role', 'owner')->first();
    }

    public function employees()
    {
        return $this->users()->where('role', 'employee');
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function materials()
    {
        return $this->hasMany(Material::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class);
    }

    public function quotes()
    {
        return $this->hasMany(Quote::class);
    }

    public function serviceTemplates()
    {
        return $this->hasMany(ServiceTemplate::class);
    }

    public function acceptanceProtocols()
    {
        return $this->hasMany(AcceptanceProtocol::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function mahnungen()
    {
        return $this->hasMany(\App\Models\Mahnung::class);
    }

    public function feedback()
    {
        return $this->hasMany(Feedback::class);
    }

    // ---- Business Logic ----

    public function generateQuoteNumber(): string
    {
        $number = $this->next_quote_number;
        $this->increment('next_quote_number');

        return $this->quote_prefix . '-' . date('Y') . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    public function isTrialActive(): bool
    {
        return $this->plan === 'trial' && $this->trial_ends_at?->isFuture();
    }
    
const TRIAL_QUOTE_LIMIT = 5;

public function trialQuotesRemaining(): int
{
    return max(0, self::TRIAL_QUOTE_LIMIT - $this->trial_quotes_used);
}

public function canGenerateQuote(): bool
{
    if (in_array($this->plan, ['starter', 'professional', 'enterprise', 'pro'])) {
        return true;
    }

    return $this->isTrialActive() && $this->trial_quotes_used < self::TRIAL_QUOTE_LIMIT;
}

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }
   public function isAccessLocked(): bool
{
    // Gesperrt, wenn gekündigt UND die Zugriffsfrist abgelaufen ist
    return $this->isCancelled() && $this->access_until && $this->access_until->isPast();
}

public function hasActiveSubscription(): bool
{
    if ($this->isAccessLocked()) {
        return false;
    }

    return in_array($this->plan, ['starter', 'professional', 'enterprise', 'pro']) || $this->isTrialActive();
}

    /**
     * Prüft, ob die Firma Zugriff auf Pro-Features hat (z.B. LV-Import).
     * Nur der aktive "pro"-Plan berechtigt dazu - Trial und Starter nicht.
     */
    public function hasProAccess(): bool
    {
        if ($this->isAccessLocked()) {
            return false;
        }

        return $this->plan === 'pro';
    }

    /**
     * Mitarbeiter-Sitzplaetze, die im jeweiligen Plan inkludiert sind
     * (analog zu Plancraft: guenstige Mitarbeiter-Sitzplaetze getrennt von
     * Owner/Admin-Zugaengen). Starter enthaelt bewusst 0 - das ist die
     * aktuelle Vorgabe, dass der 39-EUR-Plan ohne Zukauf keine Mitarbeiter
     * zulaesst.
     */
    public const EMPLOYEE_SEATS_INCLUDED = [
        'trial' => 0,
        'starter' => 0,
        'professional' => 2,
        'enterprise' => 5,
        'pro' => 2,
    ];

    public function employeeSeatLimit(): int
    {
        $included = self::EMPLOYEE_SEATS_INCLUDED[$this->plan] ?? 0;

        return $included + (int) $this->employee_seats_purchased;
    }

    public function activeEmployeeCount(): int
    {
        return $this->users()->where('role', 'employee')->whereNull('deactivated_at')->count();
    }

    public function canAddEmployee(): bool
    {
        return $this->activeEmployeeCount() < $this->employeeSeatLimit();
    }

}