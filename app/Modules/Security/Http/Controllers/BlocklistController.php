<?php

namespace App\Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BlocklistController extends Controller
{
    public function index(Request $request)
    {
        $q = DB::table('security_blocklist')->orderByDesc('id');

        if ($type = $request->query('type'))   $q->where('type', $type);
        if ($search = $request->query('q'))    $q->where('value', 'like', '%'.$search.'%');
        if ($status = $request->query('status')) {
            if ($status === 'active') {
                $q->where(function ($w) {
                    $w->whereNull('expires_at')->orWhere('expires_at', '>', now());
                });
            } elseif ($status === 'expired') {
                $q->whereNotNull('expires_at')->where('expires_at', '<=', now());
            } elseif ($status === 'permanent') {
                $q->whereNull('expires_at');
            } elseif ($status === 'temporary') {
                $q->whereNotNull('expires_at');
            }
        }

        $list = $q->paginate(50)->withQueryString();

        // KPIs
        $now = now();
        $stats = [
            'total'     => DB::table('security_blocklist')->count(),
            'active'    => DB::table('security_blocklist')
                              ->where(function ($w) use ($now) {
                                  $w->whereNull('expires_at')->orWhere('expires_at', '>', $now);
                              })->count(),
            'expired'   => DB::table('security_blocklist')->whereNotNull('expires_at')->where('expires_at', '<=', $now)->count(),
            'permanent' => DB::table('security_blocklist')->whereNull('expires_at')->count(),
        ];

        // Por tipo
        $byType = DB::table('security_blocklist')
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return view('security::blocklist', compact('list', 'stats', 'byType'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type'   => 'required|in:ip,cidr,user_agent,country',
            'value'  => 'required|string|max:200',
            'reason' => 'nullable|string|max:255',
            'expires_at' => 'nullable|date',
        ]);
        DB::table('security_blocklist')->updateOrInsert(
            ['type' => $data['type'], 'value' => $data['value']],
            [
                'reason' => $data['reason'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
        Cache::forget('security.blocklist');
        return response()->json(['ok' => true]);
    }

    public function bulkAdd(Request $request)
    {
        $data = $request->validate([
            'type'   => 'required|in:ip,cidr,user_agent,country',
            'values' => 'required|string',
            'reason' => 'nullable|string|max:255',
            'expires_at' => 'nullable|date',
        ]);

        // Aceita um valor por linha (ou separado por virgula)
        $raw = preg_split('/[\r\n,]+/', $data['values']);
        $values = array_filter(array_map('trim', $raw));

        $imported = 0;
        $skipped = 0;
        foreach ($values as $value) {
            if (mb_strlen($value) > 200) { $skipped++; continue; }
            DB::table('security_blocklist')->updateOrInsert(
                ['type' => $data['type'], 'value' => $value],
                [
                    'reason' => $data['reason'] ?? 'Importado em massa',
                    'expires_at' => $data['expires_at'] ?? null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
            $imported++;
        }

        Cache::forget('security.blocklist');
        return response()->json(['ok' => true, 'imported' => $imported, 'skipped' => $skipped]);
    }

    public function destroy(int $id)
    {
        DB::table('security_blocklist')->where('id', $id)->delete();
        Cache::forget('security.blocklist');
        return response()->json(['ok' => true]);
    }
}
