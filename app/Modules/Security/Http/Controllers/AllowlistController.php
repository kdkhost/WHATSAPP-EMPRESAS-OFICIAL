<?php

namespace App\Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AllowlistController extends Controller
{
    public function index(Request $request)
    {
        $q = DB::table('security_allowlist')->orderByDesc('id');

        if ($type = $request->query('type'))   $q->where('type', $type);
        if ($search = $request->query('q'))    $q->where(function ($w) use ($search) {
            $w->where('value', 'like', '%'.$search.'%')->orWhere('label', 'like', '%'.$search.'%');
        });

        $list = $q->paginate(50)->withQueryString();

        $stats = [
            'total' => DB::table('security_allowlist')->count(),
            'ips'   => DB::table('security_allowlist')->where('type', 'ip')->count(),
            'cidrs' => DB::table('security_allowlist')->where('type', 'cidr')->count(),
            'uas'   => DB::table('security_allowlist')->where('type', 'user_agent')->count(),
        ];

        $byType = DB::table('security_allowlist')
            ->selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        return view('security::allowlist', compact('list', 'stats', 'byType'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type'  => 'required|in:ip,cidr,user_agent',
            'value' => 'required|string|max:200',
            'label' => 'nullable|string|max:120',
        ]);
        DB::table('security_allowlist')->updateOrInsert(
            ['type' => $data['type'], 'value' => $data['value']],
            ['label' => $data['label'] ?? null, 'updated_at' => now(), 'created_at' => now()],
        );
        Cache::forget('security.allowlist');
        return response()->json(['ok' => true]);
    }

    public function bulkAdd(Request $request)
    {
        $data = $request->validate([
            'type'   => 'required|in:ip,cidr,user_agent',
            'values' => 'required|string',
            'label'  => 'nullable|string|max:120',
        ]);

        $raw = preg_split('/[\r\n,]+/', $data['values']);
        $values = array_filter(array_map('trim', $raw));

        $imported = 0;
        $skipped = 0;
        foreach ($values as $value) {
            if (mb_strlen($value) > 200) { $skipped++; continue; }
            DB::table('security_allowlist')->updateOrInsert(
                ['type' => $data['type'], 'value' => $value],
                [
                    'label' => $data['label'] ?? null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
            $imported++;
        }

        Cache::forget('security.allowlist');
        return response()->json(['ok' => true, 'imported' => $imported, 'skipped' => $skipped]);
    }

    public function destroy(int $id)
    {
        DB::table('security_allowlist')->where('id', $id)->delete();
        Cache::forget('security.allowlist');
        return response()->json(['ok' => true]);
    }
}
