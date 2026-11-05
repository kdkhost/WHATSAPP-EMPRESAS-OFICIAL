<?php

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Services\NotificationDispatcher;
use App\Modules\Settings\Services\SettingsManager;
use App\Support\NotificationAudience;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationsAdminController extends Controller
{
    public function index()
    {
        $stats = [
            'total_sent'    => DB::table('notification_queue')->where('status', 'sent')->count(),
            'pending'       => DB::table('notification_queue')->where('status', 'pending')->count(),
            'failed'        => DB::table('notification_queue')->where('status', 'failed')->count(),
            'in_app_unread' => NotificationAudience::adminQuery()->whereNull('read_at')->count(),
        ];
        return view('notifications::index', compact('stats'));
    }

    public function channels(SettingsManager $s)
    {
        $channels = [
            'in_app'   => ['enabled' => true, 'label' => 'In-App (sininho)', 'configurable' => false],
            'push'     => ['enabled' => (bool) $s->get('notifications.push_enabled', false), 'label' => 'Push (WebPush/PWA)'],
            'email'    => ['enabled' => (bool) $s->get('notifications.email_enabled', true), 'label' => 'E-mail (SMTP)'],
            'whatsapp' => ['enabled' => (bool) $s->get('notifications.whatsapp_enabled', false), 'label' => 'WhatsApp (Evolution)'],
        ];
        return response()->json($channels);
    }

    public function updateChannels(Request $request, SettingsManager $s): JsonResponse
    {
        $data = $request->validate([
            'push_enabled'     => 'required|boolean',
            'email_enabled'    => 'required|boolean',
            'whatsapp_enabled' => 'required|boolean',
        ]);
        foreach ($data as $k => $v) {
            $s->set("notifications.{$k}", $v, 'bool');
        }
        return response()->json(['ok' => true]);
    }

    public function history(Request $request)
    {
        $q = DB::table('notification_queue')->orderByDesc('id');
        if ($ch = $request->query('channel')) $q->where('channel', $ch);
        if ($st = $request->query('status'))  $q->where('status', $st);
        $items = $q->paginate(50)->withQueryString();
        return view('notifications::history', compact('items'));
    }

    public function sendManual(Request $request, NotificationDispatcher $dispatcher): JsonResponse
    {
        $data = $request->validate([
            'channels'   => 'required|array',
            'channels.*' => 'in:in_app,push,email,whatsapp',
            'title'      => 'required|string|max:255',
            'message'    => 'required|string',
            'recipients' => 'nullable|string',
        ]);

        $recipients = match ($data['recipients'] ?? 'all_admins') {
            'all_admins' => ['all_admins'],
            default      => explode(',', $data['recipients']),
        };

        $dispatcher->send([
            'channels'   => $data['channels'],
            'group'      => 'system',
            'type'       => 'manual',
            'title'      => $data['title'],
            'message'    => $data['message'],
            'recipients' => $recipients,
        ]);

        return response()->json(['ok' => true]);
    }

    public function templates()
    {
        $tpls = DB::table('notification_templates')->orderByDesc('id')->get();
        return response()->json($tpls);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:120',
            'channel'  => 'required|in:push,email,whatsapp',
            'subject'  => 'nullable|string|max:255',
            'body'     => 'required|string',
        ]);

        $id = DB::table('notification_templates')->insertGetId($data + [
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $id]);
    }
}
