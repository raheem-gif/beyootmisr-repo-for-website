<?php

namespace App\Models\Concerns;

use App\Services\Audit;

/** Writes an audit entry for every create, update and delete made by a signed-in user. */
trait Audited
{
    public static function bootAudited(): void
    {
        static::created(function ($model) {
            if (Audit::actorIsStaff()) {
                Audit::record($model->getTable().'.created', $model, null, $model->attributesToArray());
            }
        });
        static::updated(function ($model) {
            if (Audit::actorIsStaff() && $model->getChanges() !== []) {
                $changes = array_diff_key($model->getChanges(), ['updated_at' => 1]);
                $before = array_intersect_key($model->getOriginal(), $changes);
                Audit::record($model->getTable().'.updated', $model, $before, $changes);
            }
        });
        static::deleted(function ($model) {
            if (Audit::actorIsStaff()) {
                Audit::record($model->getTable().'.deleted', $model, $model->attributesToArray());
            }
        });
    }
}
