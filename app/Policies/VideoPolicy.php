<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Video;

class VideoPolicy
{
    public function manage(User $actor, Video $video): bool
    {
        if ($video->location !== $actor->location) {
            abort(404);
        }

        return true;
    }
}
