<?php

namespace App\Modules\Chatbot\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Services\SettingsManager;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatbotAdminController extends Controller
{
    protected function activeTenantId(): ?int
    {
        if (Auth::guard('web')->check()) {
            return Tenant::where('user_id', Auth::guard('web')->id())->value('id');
        }
        return null;
    }

    public function index()
    {
        $tenantId = $this->activeTenantId();
        $stats = ['total_interactions' => 0, 'today' => 0, 'top_intents' => collect()];
        try {
            $base = DB::table('chatbot_interactions')
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId));
            $stats['total_interactions'] = (clone $base)->count();
            $stats['today']              = (clone $base)->whereDate('created_at', today())->count();
            $stats['top_intents']        = (clone $base)
                ->select('intent', DB::raw('COUNT(*) as total'))
                ->groupBy('intent')->orderByDesc('total')->limit(10)->get();
        } catch (\Throwable) {}

        return view('chatbot::admin', compact('stats'));
    }

    public function saveSettings(Request $request, SettingsManager $s): JsonResponse
    {
        $data = $request->validate([
            'enabled'          => 'required|boolean',
            'greeting_message' => 'nullable|string|max:500',
            'fallback_message' => 'nullable|string|max:500',
        ]);
        foreach ($data as $k => $v) {
            $s->set("chatbot.{$k}", $v, $k === 'enabled' ? 'bool' : 'string');
        }
        return response()->json(['ok' => true]);
    }

    public function interactions(Request $request)
    {
        $tenantId = $this->activeTenantId();
        $items    = collect();
        try {
            $q = DB::table('chatbot_interactions')
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->orderByDesc('id');
            if ($intent = $request->query('intent')) $q->where('intent', $intent);
            $items = $q->paginate(50)->withQueryString();
        } catch (\Throwable) {
            $items = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 50);
        }
        return view('chatbot::interactions', compact('items'));
    }
}
