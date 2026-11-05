<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationAudience
{
    public static function adminQuery(mixed $admin = null): Builder
    {
        $admin ??= Auth::guard('admin')->user();
        $query = DB::table('admin_notifications');

        if (! $admin) {
            return $query->whereRaw('1 = 0');
        }

        if (self::hasAudienceColumns()) {
            $query->whereNull('tenant_id')->whereNull('user_id');
        }

        return $query->where(function (Builder $scope) use ($admin) {
            $scope->where('admin_user_id', $admin->id);

            if (($admin->role ?? null) === 'superadmin') {
                $scope->orWhereNull('admin_user_id');
            }
        });
    }

    public static function tenantQuery(?int $userId = null, ?int $tenantId = null): Builder
    {
        $userId ??= Auth::guard('web')->id();
        $tenantId ??= self::tenantIdForUser($userId);
        $query = DB::table('admin_notifications');

        if (! $userId || ! self::hasAudienceColumns()) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->whereNull('admin_user_id')
            ->where(function (Builder $scope) use ($userId, $tenantId) {
                $scope->where('user_id', $userId);

                if ($tenantId) {
                    $scope->orWhere('tenant_id', $tenantId);
                }
            });
    }

    public static function tenantIdForUser(?int $userId): ?int
    {
        if (! $userId || ! Schema::hasTable('tenants')) {
            return null;
        }

        return DB::table('tenants')->where('user_id', $userId)->value('id');
    }

    public static function hasAudienceColumns(): bool
    {
        return Schema::hasTable('admin_notifications')
            && Schema::hasColumn('admin_notifications', 'tenant_id')
            && Schema::hasColumn('admin_notifications', 'user_id');
    }
}
