<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsAppInstance;
use App\Modules\Tenancy\Models\Tenant;

class WhatsAppInstancePolicy
{
    public function view(User $user, WhatsAppInstance $instance): bool
    {
        return $this->ownsTenant($user, $instance);
    }

    public function create(User $user): bool
    {
        return Tenant::where('user_id', $user->id)->whereIn('status', ['trial', 'active'])->exists();
    }

    public function update(User $user, WhatsAppInstance $instance): bool
    {
        return $this->ownsTenant($user, $instance);
    }

    public function delete(User $user, WhatsAppInstance $instance): bool
    {
        return $this->ownsTenant($user, $instance);
    }

    protected function ownsTenant(User $user, WhatsAppInstance $instance): bool
    {
        return Tenant::where('user_id', $user->id)->where('id', $instance->tenant_id)->exists();
    }
}
