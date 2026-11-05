<?php

namespace App\Providers;

use App\Models\WhatsAppInstance;
use App\Policies\WhatsAppInstancePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        WhatsAppInstance::class => WhatsAppInstancePolicy::class,
    ];

    public function boot(): void
    {
        // Super-admin bypass
        Gate::before(function ($user, $ability) {
            if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                return true;
            }

            return null;
        });
    }
}
