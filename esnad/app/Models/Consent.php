<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Consent extends Model
{
    protected $fillable = ['user_id', 'purpose', 'version', 'method', 'scope', 'ip', 'user_agent', 'given_at', 'withdrawn_at'];

    protected function casts(): array
    {
        return ['given_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // A consent row records a fact. The only change allowed is setting
        // withdrawn_at once.
        static::updating(function (Consent $consent) {
            $dirty = array_keys($consent->getDirty());
            if (array_diff($dirty, ['withdrawn_at', 'updated_at']) !== [] || $consent->getOriginal('withdrawn_at') !== null) {
                throw new LogicException('Consent records cannot be edited. Record a new consent instead.');
            }
        });
        static::deleting(fn () => throw new LogicException('Consent records cannot be deleted.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('withdrawn_at');
    }
}
