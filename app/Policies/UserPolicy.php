<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function manage(User $actor, User $target): bool
    {
        if ($target->location !== $actor->location) {
            abort(404);
        }

        return true;
    }
}
