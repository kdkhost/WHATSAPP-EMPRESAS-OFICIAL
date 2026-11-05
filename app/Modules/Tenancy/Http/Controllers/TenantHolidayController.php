<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Holidays\Services\HolidayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Auth;

class TenantHolidayController extends Controller
{
    protected function tenant(): Tenant
    {
        $user = Auth::user();
        $tenant = Tenant::where('user_id', $user->id)->first();
        if (! $tenant) {
            abort(403, 'Voce nao possui uma assinatura ativa.');
        }
        if (! $tenant->isActive() && $tenant->isTrialExpired()) {
            abort(403, 'Sua assinatura expirou. Renove para continuar.');
        }

        return $tenant;
    }

    public function index()
    {
        return redirect()->route('tenant.calendar');
    }

    public function calendarEvents(Request $request, HolidayService $svc): JsonResponse
    {
        $year = (int) $request->query('year', now()->year);
        // O HolidayService ja vai puxar de acordo com o tenant_id logado
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
        
        DB::table('holidays')->insert($data + [
            'tenant_id' => $this->tenant()->id,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $this->clearCache();
        return response()->json(['ok' => true]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->tenant()->id;
        
        $holiday = DB::table('holidays')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();
            
        if (!$holiday) {
            return response()->json(['error' => 'Feriado nao encontrado ou voce nao tem permissao para edita-lo. Feriados nacionais sao apenas leitura.'], 403);
        }

        $data = $request->validate([
            'date'             => 'sometimes|date',
            'name'             => 'sometimes|string|max:120',
            'type'             => 'sometimes|in:national,state,municipal,custom',
            'uf'               => 'nullable|string|size:2',
            'block_billing'    => 'sometimes|boolean',
            'block_dispatch'   => 'sometimes|boolean',
            'block_suspension' => 'sometimes|boolean',
            'color'            => 'nullable|string|max:7',
            'active'           => 'sometimes|boolean',
        ]);

        DB::table('holidays')->where('id', $id)->update($data + ['updated_at' => now()]);
        
        $this->clearCache();
        return response()->json(['ok' => true]);
    }

    public function destroy(int $id): JsonResponse
    {
        $tenantId = $this->tenant()->id;
        
        $deleted = DB::table('holidays')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->delete();
            
        if (!$deleted) {
            return response()->json(['error' => 'Feriado nao encontrado ou protegido (global).'], 403);
        }

        $this->clearCache();
        return response()->json(['ok' => true]);
    }

    protected function clearCache(): void
    {
        $tenantId = $this->tenant()->id;
        $years = [now()->year, now()->addYear()->year];
        foreach ($years as $y) {
            Cache::forget("holidays.{$tenantId}.{$y}");
        }
    }
}
