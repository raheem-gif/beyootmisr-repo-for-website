<?php

namespace App\Services;

use App\Models\Consent;
use App\Models\User;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Consent register (section 4.4). Four purposes, each recorded separately
 * with time, version, method and scope, and each can be withdrawn.
 */
class ConsentService
{
    public static function purposes(): array
    {
        return config('esnad.consent_purposes');
    }

    public static function give(User $user, string $purpose, string $method = 'web_checkbox', ?string $scope = null): Consent
    {
        self::assertPurpose($purpose);

        $existing = self::current($user, $purpose);
        if ($existing && $existing->version === config('esnad.consent_version') && $existing->scope === $scope) {
            return $existing;
        }

        return Consent::create([
            'user_id' => $user->id,
            'purpose' => $purpose,
            'version' => config('esnad.consent_version'),
            'method' => $method,
            'scope' => $scope,
            'ip' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 255),
            'given_at' => now(),
        ]);
    }

    public static function withdraw(User $user, string $purpose): int
    {
        self::assertPurpose($purpose);
        $count = 0;
        foreach ($user->consents()->where('purpose', $purpose)->whereNull('withdrawn_at')->get() as $consent) {
            $consent->withdrawn_at = now();
            $consent->save();
            $count++;
        }
        if ($count > 0) {
            Audit::record('consent.withdrawn', $user, null, ['purpose' => $purpose]);
        }

        return $count;
    }

    public static function has(User $user, string $purpose): bool
    {
        return self::current($user, $purpose) !== null;
    }

    public static function current(User $user, string $purpose): ?Consent
    {
        return $user->consents()->where('purpose', $purpose)->whereNull('withdrawn_at')->latest('given_at')->latest('id')->first();
    }

    /** @return Collection<int, Consent> */
    public static function history(User $user): Collection
    {
        return $user->consents()->orderByDesc('given_at')->orderByDesc('id')->get();
    }

    private static function assertPurpose(string $purpose): void
    {
        if (! in_array($purpose, self::purposes(), true)) {
            throw new InvalidArgumentException("Unknown consent purpose [$purpose].");
        }
    }
}
