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
        'trial_ends_at',
        'subscription_started_at',
        'cancelled_at',
        'access_until',
    ];

    protected $casts = [
        'default_vat_rate' => 'decimal:2',
        'default_hourly_rate' => 'decimal:2',
        'trial_ends_at' => 'datetime',
        'is_small_business'    => 'boolean',
        'mahnung_fee_level1'   => 'decimal:2',
        'mahnung_fee_level2'   => 'decimal:2',
        'mahnung_fee_level3'   => 'decimal:2',
        'mahnung_interest_rate' => 'decimal:2',
        'trial_ends_at' => 'datetime',
        'subscription_started_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'access_until' => 'datetime',
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

    public function customers()
    {
        return $this->hasMany(Customer::class);
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

    return in_array($this->plan, ['starter', 'professional', 'enterprise']) || $this->isTrialActive();
}

}