<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Models\Video;
use App\Policies\UserPolicy;
use App\Policies\VideoPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Video::class, VideoPolicy::class);
    }
}
