<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Every admin resource checks a permission on the server (section 5).
 * Set $viewPermission and $managePermission; read-only resources set
 * $readOnly = true.
 */
trait GuardedByPermission
{
    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can(static::$viewPermission);
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canCreate(): bool
    {
        return ! static::$readOnly && (bool) auth()->user()?->can(static::$managePermission);
    }

    public static function canEdit(Model $record): bool
    {
        return ! static::$readOnly && (bool) auth()->user()?->can(static::$managePermission);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
