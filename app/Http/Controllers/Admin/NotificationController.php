<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\NotificationAudience;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return $this->renderIndex($request, NotificationAudience::adminQuery(), false);
    }

    public function feed()
    {
        return response()->json($this->feedPayload(
            NotificationAudience::adminQuery(Auth::guard('admin')->user()),
            false
        ))
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma', 'no-cache');
    }

    public function readAll()
    {
        NotificationAudience::adminQuery(Auth::guard('admin')->user())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function sound(Request $request)
    {
        $enabled = $request->boolean('enabled');
        $user = Auth::guard('admin')->user();

        if ($user && Schema::hasColumn('admin_users', 'notification_sound_enabled')) {
            DB::table('admin_users')->where('id', $user->id)->update(['notification_sound_enabled' => $enabled]);
            $user->forceFill(['notification_sound_enabled' => $enabled]);
        }

        return response()->json(['ok' => true, 'enabled' => $enabled]);
    }

    public function show(Request $request, int $notification)
    {
        return $this->renderIndex($request, NotificationAudience::adminQuery(), false, $notification);
    }

    public function read(int $notification)
    {
        return response()->json($this->markNotificationAsRead(
            NotificationAudience::adminQuery(Auth::guard('admin')->user()),
            $notification,
            false
        ));
    }

    public function tenantIndex(Request $request)
    {
        return $this->renderIndex($request, NotificationAudience::tenantQuery(Auth::id()), true);
    }

    public function tenantFeed()
    {
        return response()->json($this->feedPayload(
            NotificationAudience::tenantQuery(Auth::id()),
            true
        ))
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma', 'no-cache');
    }

    public function tenantReadAll()
    {
        NotificationAudience::tenantQuery(Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function tenantSound(Request $request)
    {
        $enabled = $request->boolean('enabled');
        $user = Auth::guard('web')->user();

        if ($user && Schema::hasColumn('users', 'notification_sound_enabled')) {
            DB::table('users')->where('id', $user->id)->update(['notification_sound_enabled' => $enabled]);
            $user->forceFill(['notification_sound_enabled' => $enabled]);
        }

        return response()->json(['ok' => true, 'enabled' => $enabled]);
    }

    public function tenantShow(Request $request, int $notification)
    {
        return $this->renderIndex($request, NotificationAudience::tenantQuery(Auth::id()), true, $notification);
    }

    public function tenantRead(int $notification)
    {
        return response()->json($this->markNotificationAsRead(
            NotificationAudience::tenantQuery(Auth::id()),
            $notification,
            true
        ));
    }

    protected function feedPayload($base, bool $isTenantPanel): array
    {
        $unreadTotal = (clone $base)->whereNull('read_at')->count();
        $rows = (clone $base)
            ->whereNull('read_at')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $indexUrl = $isTenantPanel ? route('tenant.notifications') : route('admin.notifications');

        return [
            'ok' => true,
            'unread' => $unreadTotal,
            'latest_id' => (int) ($rows->max('id') ?? 0),
            'updated_at' => now()->format('H:i:s'),
            'latest_notification' => $rows->first() ? $this->notificationPayload($rows->first(), $isTenantPanel) : null,
            'html' => view('admin.partials.bell-items', [
                'grouped' => $rows->groupBy('group'),
                'indexUrl' => $indexUrl,
                'isTenantPanel' => $isTenantPanel,
            ])->render(),
        ];
    }

    protected function renderIndex(Request $request, $base, bool $isTenantPanel, ?int $selectedId = null)
    {
        $q = (clone $base)->orderByDesc('created_at');
        if ($g = $request->query('group')) {
            $q->where('group', $g);
        }

        $notifications = $q->paginate(50)->withQueryString();
        $selectedId ??= (int) $request->query('notification', 0);
        $selectedNotification = $selectedId > 0
            ? (clone $base)->where('id', $selectedId)->first()
            : null;

        if ($selectedNotification && ! $selectedNotification->read_at) {
            DB::table('admin_notifications')->where('id', $selectedNotification->id)->update(['read_at' => now()]);
            $selectedNotification = (clone $base)->where('id', $selectedNotification->id)->first();
        }

        $stats = [
            'total'  => (clone $base)->count(),
            'unread' => (clone $base)->whereNull('read_at')->count(),
            'today'  => (clone $base)->whereDate('created_at', today())->count(),
        ];

        $groups = (clone $base)
            ->selectRaw('`group`, COUNT(*) as total')
            ->groupBy('group')
            ->pluck('total', 'group');

        return view('admin.notifications.index', compact('notifications', 'stats', 'groups', 'selectedNotification'));
    }

    protected function markNotificationAsRead($base, int $notificationId, bool $isTenantPanel): array
    {
        $row = (clone $base)->where('id', $notificationId)->first();
        abort_unless($row, 404);

        if (! $row->read_at) {
            DB::table('admin_notifications')->where('id', $row->id)->update(['read_at' => now()]);
            $row = (clone $base)->where('id', $row->id)->first();
        }

        return [
            'ok' => true,
            'notification' => $this->notificationPayload($row, $isTenantPanel),
            'unread' => (clone $base)->whereNull('read_at')->count(),
        ];
    }

    protected function notificationPayload(object $notification, bool $isTenantPanel): array
    {
        $detailUrl = $isTenantPanel
            ? route('tenant.notifications.show', $notification->id)
            : route('admin.notifications.show', $notification->id);

        $relatedUrl = $notification->url ?: null;
        if ($isTenantPanel && $relatedUrl && str_starts_with($relatedUrl, '/admin')) {
            $relatedUrl = null;
        }

        return [
            'id' => (int) $notification->id,
            'title' => (string) $notification->title,
            'message' => (string) ($notification->message ?? ''),
            'group' => (string) $notification->group,
            'type' => (string) $notification->type,
            'url' => $detailUrl,
            'detail_url' => $detailUrl,
            'related_url' => $relatedUrl,
            'read_at' => $notification->read_at,
        ];
    }
}
