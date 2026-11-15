<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Support\Phone;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasName
{
    /** @use HasFactory<UserFactory> */
    use Audited, HasFactory, HasRoles, HasUlids, Notifiable, SoftDeletes;

    /** Roles that work inside the admin backend (section 5). */
    public const STAFF_ROLES = [
        'support_agent', 'verification_officer', 'dispatch_operator', 'finance_officer',
        'content_editor', 'data_protection_officer', 'super_admin',
    ];

    /** Roles that must use two-factor sign-in (section 18.1). */
    public const TWO_FACTOR_ROLES = [...self::STAFF_ROLES, 'lawyer', 'firm_admin'];

    protected $attributes = ['status' => 'active', 'locale' => 'ar'];

    protected $fillable = [
        'phone', 'email', 'name_ar', 'name_en', 'address_form', 'locale', 'governorate_id', 'national_id', 'status', 'password',
    ];

    protected $hidden = [
        'password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes', 'national_id',
    ];

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'national_id' => 'encrypted',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    /** Internal IDs stay numeric; public URLs use the ULID column. */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    public function governorate(): BelongsTo
    {
        return $this->belongsTo(Governorate::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    public function displayName(): string
    {
        $name = app()->getLocale() === 'en' ? ($this->name_en ?: $this->name_ar) : ($this->name_ar ?: $this->name_en);

        return $name ?: ($this->phone ? Phone::mask($this->phone) : (string) $this->email);
    }

    public function getFilamentName(): string
    {
        return $this->displayName();
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole(self::STAFF_ROLES);
    }

    public function requiresTwoFactor(): bool
    {
        return $this->hasAnyRole(self::TWO_FACTOR_ROLES);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->status === 'active' && $this->isStaff();
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email ?: (string) $this->phone;
    }

    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }
}
