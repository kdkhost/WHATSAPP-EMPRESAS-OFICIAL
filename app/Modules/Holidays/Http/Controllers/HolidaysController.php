<?php

namespace App\Modules\Holidays\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Holidays\Services\HolidayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HolidaysController extends Controller
{
    public function index()
    {
        $holidays = DB::table('holidays')->orderBy('date')->paginate(50);
        return view('holidays::index', compact('holidays'));
    }

    public function calendarEvents(Request $request, HolidayService $svc): JsonResponse
    {
        $year = (int) $request->query('year', now()->year);
        return response()->json($svc->calendarEvents($year));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date'             => 'required|date',
            'name'             => 'required|string|max:120',
            'type'             => 'required|in:national,state,municipal,custom',
            'uf'               => 'nullable|string|size:2',
            'block_billing'    => 'required|boolean',
            'block_dispatch'   => 'required|boolean',
            'block_suspension' => 'required|boolean',
            'color'            => 'nullable|string|max:7',
        ]);
        DB::table('holidays')->insert($data + ['active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->clearCache();
        return response()->json(['ok' => true]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'date'             => 'sometimes|date',
            'name'             => 'sometimes|string|max:120',
            'type'             => 'sometimes|in:national,state,municipal,custom',
            'uf'               => 'nullable|string|size:2',
            'block_billing'    => 'sometimes|boolean',
            'block_dispatch'   => 'sometimes|boolean',
            'block_suspension' => 'sometimes|boolean',
            'color'            => 'sometimes|string|max:7',
            'active'           => 'sometimes|boolean',
        ]);
        DB::table('holidays')->where('id', $id)->update($data + ['updated_at' => now()]);
        $this->clearCache();
        return response()->json(['ok' => true]);
    }

    public function destroy(int $id): JsonResponse
    {
        DB::table('holidays')->where('id', $id)->delete();
        $this->clearCache();
        return response()->json(['ok' => true]);
    }

    public function importBrazilian(HolidayService $svc): JsonResponse
    {
        $count = 0;
        $startYear = now()->year;
        for ($y = $startYear; $y <= $startYear + 2; $y++) {
            foreach (HolidayService::nationalHolidays() as $h) {
                $iso = "{$y}-{$h['date']}";
                $exists = DB::table('holidays')->where('date', $iso)->exists();
                if ($exists) continue;
                DB::table('holidays')->insert([
                    'date' => $iso, 'name' => $h['name'], 'type' => 'national',
                    'block_billing' => true, 'block_dispatch' => true, 'block_suspension' => true,
                    'color' => '#dc3545', 'active' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $count++;
            }
        }
        $this->clearCache();
        return response()->json(['ok' => true, 'imported' => $count]);
    }

    protected function clearCache(): void
    {
        for ($y = now()->year - 1; $y <= now()->year + 3; $y++) {
            Cache::forget("holidays.{$y}");
        }
    }
}
