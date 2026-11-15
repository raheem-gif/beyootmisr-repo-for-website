<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Append-only audit log of admin actions and staff views of personal data
 * (sections 5 and 13). Each row carries a hash chained to the previous row,
 * so a removed or altered row breaks the chain.
 */
class Audit
{
    public static function record(string $action, ?Model $target = null, ?array $before = null, ?array $after = null, ?string $reason = null): AuditLog
    {
        $request = request();
        $actor = auth()->user();

        return DB::transaction(function () use ($action, $target, $before, $after, $reason, $request, $actor) {
            $previous = AuditLog::query()->lockForUpdate()->latest('id')->value('chain_hash') ?? str_repeat('0', 64);
            $row = [
                'actor_id' => $actor?->getKey(),
                'actor_type' => $actor ? 'user' : 'system',
                'action' => $action,
                'target_type' => $target ? $target->getMorphClass() : null,
                'target_id' => $target?->getKey(),
                'before' => $before ? self::redact($before) : null,
                'after' => $after ? self::redact($after) : null,
                'reason' => $reason,
                'ip' => $request?->ip(),
                'user_agent' => $request ? substr((string) $request->userAgent(), 0, 255) : null,
                'created_at' => now(),
            ];
            $row['chain_hash'] = hash('sha256', $previous.json_encode(array_merge($row, ['created_at' => $row['created_at']->toIso8601String()])));

            return AuditLog::create($row);
        });
    }

    public static function actorIsStaff(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->isStaff();
    }

    /** Records that a staff member opened a record holding personal data. */
    public static function viewed(Model $target, ?string $reason = null): void
    {
        self::record('personal_data.viewed', $target, reason: $reason);
    }

    /** Recomputes the chain. Returns the ID of the first broken row, or null. */
    public static function verifyChain(): ?int
    {
        $previous = str_repeat('0', 64);
        foreach (AuditLog::query()->orderBy('id')->cursor() as $log) {
            $row = $log->getAttributes();
            unset($row['id'], $row['chain_hash']);
            $row['before'] = $log->before;
            $row['after'] = $log->after;
            $row['created_at'] = $log->created_at->toIso8601String();
            $ordered = array_merge(array_flip(['actor_id', 'actor_type', 'action', 'target_type', 'target_id', 'before', 'after', 'reason', 'ip', 'user_agent', 'created_at']), $row);
            if (! hash_equals($log->chain_hash, hash('sha256', $previous.json_encode($ordered)))) {
                return $log->id;
            }
            $previous = $log->chain_hash;
        }

        return null;
    }

    private static function redact(array $values): array
    {
        foreach (['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes', 'code_hash'] as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = '[redacted]';
            }
        }

        return $values;
    }
}
