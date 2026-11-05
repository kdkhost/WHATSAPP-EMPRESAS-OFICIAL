<?php

namespace App\Modules\Marketing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Marketing\Models\MarketingLead;
use App\Modules\Marketing\Models\MarketingVisit;
use App\Modules\Marketing\Services\MarketingService;
use App\Modules\Tenancy\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminMarketingController extends Controller
{
    public function __construct(protected MarketingService $marketing) {}

    /**
     * Dashboard administrativo de marketing, rotas e leads.
     */
    public function index(Request $request)
    {
        $analytics = $this->marketing->getAnalytics();
        $campaign = $this->marketing->getSettings();

        // Carrega planos disponíveis para seleção na campanha promocional
        $allPlans = Plan::query()
            ->where('active', true)
            ->where('is_demo', false)
            ->orderBy('sort')
            ->orderBy('price')
            ->get();

        if ($allPlans->isEmpty()) {
            $allPlans = Plan::query()->where('active', true)->orderBy('sort')->get();
        }

        // Carrega os leads para renderização e filtragem instantânea via DataTable
        $leads = MarketingLead::query()->orderByDesc('id')->get();

        // Últimas 200 visitas com rota de origem e referer para o DataTable
        $recentVisits = MarketingVisit::orderByDesc('id')->limit(200)->get();

        return view('admin.marketing.index', compact(
            'analytics',
            'campaign',
            'allPlans',
            'leads',
            'recentVisits'
        ));
    }

    /**
     * Salva as configuracoes da pagina de marketing via AJAX.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled'          => 'nullable|boolean',
            'headline'         => 'required|string|max:255',
            'subtitle'         => 'nullable|string|max:500',
            'discount_badge'   => 'nullable|string|max:100',
            'cta_text'         => 'nullable|string|max:80',
            'whatsapp'         => 'nullable|string|max:30',
            'countdown_hours'  => 'nullable|integer|min:1|max:72',
            'banner_alert'     => 'nullable|string|max:255',
            'featured_plans'   => 'nullable|array|max:3',
            'featured_plans.*' => 'integer',
            'discount_percent' => 'nullable|integer|min:1|max:99',
            'pricing_mode'     => 'nullable|string|in:apply_discount,nominal_is_promo',
        ], [
            'featured_plans.max' => 'Você pode selecionar no máximo 3 planos para exibição nesta página promocional.',
        ]);

        $data['enabled'] = $request->boolean('enabled');
        $this->marketing->saveSettings($data);

        return response()->json([
            'ok'      => true,
            'message' => 'Configurações da campanha de marketing atualizadas com sucesso!',
        ]);
    }

    /**
     * Atualiza o status do lead (novo, contatado, convertido, perdido) via AJAX.
     */
    public function updateLeadStatus(Request $request, int $id): JsonResponse
    {
        $status = $request->validate([
            'status' => 'required|in:new,contacted,converted,lost',
            'notes'  => 'nullable|string|max:1000',
        ])['status'];

        $lead = MarketingLead::findOrFail($id);
        $lead->status = $status;
        if ($request->has('notes')) {
            $lead->notes = $request->input('notes');
        }
        $lead->save();

        return response()->json([
            'ok'           => true,
            'message'      => "Status do lead atualizado para: {$lead->status_label}.",
            'status_label' => $lead->status_label,
            'status_badge' => $lead->status_badge,
        ]);
    }

    /**
     * Remove todos os leads capturados (com confirmação SweetAlert2 no front).
     */
    public function clearAllLeads(): JsonResponse
    {
        $count = MarketingLead::count();
        MarketingLead::truncate();

        return response()->json([
            'ok'      => true,
            'message' => "Todos os {$count} leads foram excluídos com sucesso.",
        ]);
    }

    /**
     * Exclui um lead.
     */
    public function deleteLead(int $id): JsonResponse
    {
        $lead = MarketingLead::findOrFail($id);
        $lead->delete();

        return response()->json([
            'ok'      => true,
            'message' => 'Lead removido com sucesso.',
        ]);
    }

    /**
     * Exporta os leads para CSV.
     */
    public function exportLeads(): StreamedResponse
    {
        $fileName = 'leads-marketing-' . date('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF"); // UTF-8 BOM
            fputcsv($handle, ['ID', 'Nome', 'WhatsApp', 'E-mail', 'Interesse', 'Origem/Plataforma', 'Referer', 'UTM Source', 'UTM Campaign', 'Status', 'Data Cadastro'], ';');

            MarketingLead::chunk(200, function ($leads) use ($handle) {
                foreach ($leads as $lead) {
                    fputcsv($handle, [
                        $lead->id,
                        $lead->name,
                        $lead->whatsapp,
                        $lead->email ?: '',
                        $lead->interest ?: '',
                        $lead->origin_platform,
                        $lead->referer ?: '',
                        $lead->utm_source ?: '',
                        $lead->utm_campaign ?: '',
                        $lead->status_label,
                        $lead->created_at?->format('d/m/Y H:i') ?? '',
                    ], ';');
                }
            });

            fclose($handle);
        }, $fileName, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ]);
    }
}
