<?php

namespace App\Modules\Onboarding\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Services\SettingsManager;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Onboarding wizard - guia o tenant nos primeiros passos:
 *   1. Conectar WhatsApp (escanear QR)
 *   2. Configurar gateway de pagamento
 *   3. Criar primeiro template
 *   4. Importar/criar primeiro cliente
 *   5. Concluido!
 */
class OnboardingController extends Controller
{
    public function index()
    {
        $tenant = $this->tenant();
        $progress = $this->calculateProgress($tenant);

        return view('onboarding::wizard', compact('tenant', 'progress'));
    }

    public function status(): JsonResponse
    {
        $tenant = $this->tenant();
        return response()->json($this->calculateProgress($tenant));
    }

    public function skip(): JsonResponse
    {
        $user = Auth::user();
        DB::table('users')->where('id', $user->id)->update([
            'onboarding_completed_at' => now(),
        ]);
        return response()->json(['ok' => true]);
    }

    protected function tenant(): Tenant
    {
        $user = Auth::user();
        $tenant = Tenant::where('user_id', $user->id)->first();
        if (!$tenant) abort(403);
        return $tenant;
    }

    protected function calculateProgress(Tenant $tenant): array
    {
        $steps = [
            'whatsapp' => [
                'label'       => 'Conectar WhatsApp',
                'description' => 'Escaneie o QR Code para conectar sua instancia',
                'icon'        => 'bi-whatsapp',
                'completed'   => $tenant->instances()->where('status', 'active')->exists(),
                'url'         => route('tenant.instances'),
            ],
            'gateway' => [
                'label'       => 'Configurar gateway de pagamento',
                'description' => 'Adicione MercadoPago, Efi Pro ou SumUp',
                'icon'        => 'bi-credit-card',
                'completed'   => $tenant->gateways()->where('active', true)->exists(),
                'url'         => route('tenant.gateways'),
            ],
            'template' => [
                'label'       => 'Criar primeiro template',
                'description' => 'Personalize a mensagem de cobranca',
                'icon'        => 'bi-chat-square-text',
                'completed'   => DB::table('whatsapp_templates')
                                ->where('tenant_id', $tenant->id)
                                ->where('active', true)->exists(),
                'url'         => route('tenant.templates'),
            ],
            'customer' => [
                'label'       => 'Adicionar primeiro cliente',
                'description' => 'Vincule clientes aos pacotes e acompanhe as cobrancas',
                'icon'        => 'bi-people',
                'completed'   => DB::table('customers')
                                ->where('tenant_id', $tenant->id)
                                ->exists(),
                'url'         => route('tenant.packages'),
            ],
            'billing' => [
                'label'       => 'Configurar cobranca automatica',
                'description' => 'Defina horarios e dias de aviso',
                'icon'        => 'bi-clock-history',
                'completed'   => $tenant->billingConfig()->exists(),
                'url'         => route('tenant.billing'),
            ],
        ];

        $totalSteps = count($steps);
        $completed = collect($steps)->where('completed', true)->count();
        $percentage = $totalSteps > 0 ? round(($completed / $totalSteps) * 100) : 0;

        return [
            'steps'      => $steps,
            'total'      => $totalSteps,
            'completed'  => $completed,
            'percentage' => $percentage,
            'all_done'   => $completed === $totalSteps,
        ];
    }
}
