<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Whether the authenticated user can manage (update, delete, promote,
     * demote) the given target user. Scoped to "same location" — a totem's
     * superadmin only manages accounts at their own location.
     *
     * Aborts with a plain 404 (not the framework's default 403) so a
     * mismatched location reads the same as "user not found", same as
     * before this was a policy.
     */
    public function manage(User $actor, User $target): bool
    {
        if ($target->location !== $actor->location) {
            abort(404);
        }

        return true;
    }
}
