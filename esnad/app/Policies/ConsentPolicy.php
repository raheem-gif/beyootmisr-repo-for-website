<?php

namespace App\Policies;

use App\Models\Consent;
use App\Models\User;

/** Staff read the register. Only the user gives or withdraws a consent. */
class ConsentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('consents.view');
    }

    public function view(User $user, Consent $consent): bool
    {
        return $user->can('consents.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Consent $consent): bool
    {
        return false;
    }

    public function delete(User $user, Consent $consent): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
