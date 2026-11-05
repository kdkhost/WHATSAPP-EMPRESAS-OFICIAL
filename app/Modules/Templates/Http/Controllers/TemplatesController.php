<?php

namespace App\Modules\Templates\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Templates\Models\WhatsAppTemplate;
use App\Modules\Templates\Services\TemplateRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TemplatesController extends Controller
{
    public function __construct(protected TemplateRenderer $renderer) {}

    public function index(Request $request)
    {
        $query = WhatsAppTemplate::query();

        if ($request->filled('search')) {
            $s = trim((string) $request->input('search'));
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('slug', 'like', "%{$s}%")
                  ->orWhere('content_text', 'like', "%{$s}%");
            });
        }

        if ($request->filled('category') && $request->input('category') !== 'all') {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('active', $request->input('status') === 'active');
        }

        if ($request->filled('schedule') && $request->input('schedule') !== 'all') {
            if ($request->input('schedule') === 'manual') {
                $query->whereNull('schedule_offset');
            } else {
                $query->whereNotNull('schedule_offset');
            }
        }

        $templates = $query->orderByDesc('is_default')
            ->orderBy('category')
            ->orderBy('schedule_offset')
            ->orderBy('name')
            ->get();

        $allCategories = WhatsAppTemplate::categories();
        $scheduleOffsets = WhatsAppTemplate::scheduleOffsets();

        $stats = [
            'total'     => WhatsAppTemplate::count(),
            'active'    => WhatsAppTemplate::where('active', true)->count(),
            'inactive'  => WhatsAppTemplate::where('active', false)->count(),
            'scheduled' => WhatsAppTemplate::whereNotNull('schedule_offset')->count(),
            'system'    => WhatsAppTemplate::where('is_system', true)->count(),
        ];

        return view('templates::index', compact('templates', 'allCategories', 'scheduleOffsets', 'stats'));
    }

    public function create()
    {
        $template = new WhatsAppTemplate([
            'active'    => true,
            'category'  => 'cobranca',
            'scope'     => 'system',
            'is_system' => true,
        ]);
        $variables = WhatsAppTemplate::availableVariables();
        $categories = WhatsAppTemplate::categories();
        $scheduleOffsets = WhatsAppTemplate::scheduleOffsets();

        return view('templates::form', compact('template', 'variables', 'categories', 'scheduleOffsets'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);
        $slugBase = ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['name']);
        $data['slug'] = $slugBase.'-'.Str::lower(Str::random(4));

        if (empty($data['content_text'])) {
            $data['content_text'] = WhatsAppTemplate::htmlToWhatsApp($data['content_html']);
        }

        $data['scope'] = 'system';
        $data['is_system'] = true;
        $data['is_default'] = $request->boolean('is_default', false);

        $tpl = WhatsAppTemplate::create($data);

        return response()->json([
            'ok'       => true,
            'message'  => 'Template criado com sucesso!',
            'id'       => $tpl->id,
            'redirect' => route('admin.templates.edit', $tpl->id),
        ]);
    }

    public function edit(int $id)
    {
        $template = WhatsAppTemplate::findOrFail($id);
        $variables = WhatsAppTemplate::availableVariables();
        $categories = WhatsAppTemplate::categories();
        $scheduleOffsets = WhatsAppTemplate::scheduleOffsets();

        return view('templates::form', compact('template', 'variables', 'categories', 'scheduleOffsets'));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $template = WhatsAppTemplate::findOrFail($id);
        $data = $this->validateData($request);

        if (empty($data['content_text'])) {
            $data['content_text'] = WhatsAppTemplate::htmlToWhatsApp($data['content_html']);
        }

        if ($request->has('active')) {
            $data['active'] = $request->boolean('active');
        }

        $template->update($data);

        return response()->json([
            'ok'      => true,
            'message' => 'Template atualizado com sucesso!',
        ]);
    }

    public function clone(int $id): JsonResponse
    {
        $original = WhatsAppTemplate::findOrFail($id);
        $clone = $original->replicate();
        $clone->name = $original->name . ' (Cópia)';
        $clone->slug = Str::slug($original->name) . '-copia-' . Str::lower(Str::random(4));
        $clone->is_default = false;
        $clone->save();

        return response()->json([
            'ok'       => true,
            'message'  => 'Template duplicado com sucesso!',
            'id'       => $clone->id,
            'redirect' => route('admin.templates.edit', $clone->id),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $template = WhatsAppTemplate::findOrFail($id);

        if ($template->locked) {
            return response()->json([
                'ok'    => false,
                'error' => 'Este template está bloqueado e não pode ser excluído.',
            ], 422);
        }

        $template->delete();

        return response()->json([
            'ok'      => true,
            'message' => 'Template excluído com sucesso!',
        ]);
    }

    /** Endpoint de preview em tempo real - chamado a cada alteração no editor. */
    public function preview(Request $request): JsonResponse
    {
        $html = (string) $request->input('content_html', '');
        $sample = (array) $request->input('sample', $this->defaultSampleContext());

        return response()->json([
            'html' => $this->renderer->renderHtml($html, $sample),
            'text' => $this->renderer->render(WhatsAppTemplate::htmlToWhatsApp($html), $sample),
        ]);
    }

    protected function validateData(Request $request): array
    {
        $validCategories = implode(',', array_keys(WhatsAppTemplate::categories()));

        return $request->validate([
            'name'            => 'required|string|max:120',
            'slug'            => 'nullable|string|max:80',
            'category'        => 'required|string|in:' . $validCategories,
            'schedule_offset' => 'nullable|string|max:30',
            'content_html'    => 'required|string',
            'content_text'    => 'nullable|string',
            'active'          => 'sometimes|boolean',
        ]);
    }

    protected function defaultSampleContext(): array
    {
        return [
            // Cliente
            'nome'               => 'João Carlos da Silva',
            'primeiro_nome'      => 'João',
            'email'              => 'joao.silva@exemplo.com.br',
            'telefone'           => '(11) 98765-4321',
            'documento'          => '123.456.789-00',

            // Cobrança
            'numero_fatura'      => 'INV-2026-0891',
            'valor'              => 289.90,
            'valor_original'     => 289.90,
            'valor_atualizado'   => 295.40,
            'vencimento'         => now()->addDays(2)->format('d/m/Y'),
            'dias_atraso'        => '3',
            'descricao'          => 'Hospedagem Cloud Dedicada + Backup Diário',
            'link_pagamento'     => 'https://pay.exemplo.com/c/inv-2026-0891',
            'boleto_url'         => 'https://pay.exemplo.com/boleto/20260891.pdf',
            'linha_digitavel'    => '34191.79001 01043.510047 91020.150008 8 98450000028990',
            'metodo_pagamento'   => 'PIX Dinâmico / Boleto',
            'forma_pagamento'    => 'PIX',
            'data_pagamento'     => now()->format('d/m/Y H:i'),

            // Pix e QR Code
            'pix_copia_cola'     => '00020101021226580014br.gov.bcb.pix2536pix.exemplo.com/qr/v2/99c89e5204000053039865802BR5925MINHA EMPRESA TECNOLOGIA6009SAO PAULO62070503***6304E8A2',
            'qrcode_url'         => 'https://api.qrserver.com/v1/create-qr-code/?size=360x360&margin=12&data=00020101021226580014br.gov.bcb.pix',
            'qrcode_pix'         => 'https://api.qrserver.com/v1/create-qr-code/?size=360x360&margin=12&data=00020101021226580014br.gov.bcb.pix',
            'qrcode_base64'      => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            'qrcode_instrucao'   => 'Também enviamos a imagem do QR Code Pix para agilizar o pagamento.',

            // Serviços e Pacotes
            'nome_servico'       => 'Plano Corporativo Plus',
            'servico_principal'  => 'Plano Corporativo Plus',
            'servicos'           => 'Plano Corporativo Plus, IP Dedicado',
            'servicos_ativos'    => 'Plano Corporativo Plus',
            'status_servico'     => 'Ativo',
            'parcela_atual'      => '2',
            'parcelas_total'     => '12',
            'resumo_parcelas'    => '2/12',
            'proximo_vencimento' => now()->addMonth()->format('d/m/Y'),

            // Contratos
            'contrato_numero'    => 'CTR-2026/044',
            'contrato_titulo'    => 'Prestação de Serviços de TI',
            'link_contrato'      => 'https://sistema.exemplo.com/contratos/ctr-2026-044',
            'link_assinatura'    => 'https://sistema.exemplo.com/assinar/ctr-2026-044?token=abc123xyz',
            'data_assinatura'    => now()->format('d/m/Y H:i'),

            // Notificações
            'titulo'             => 'Manutenção Programada na Infraestrutura',
            'mensagem'           => 'Informamos que realizaremos melhorias no datacenter no próximo domingo das 02:00 às 04:00.',

            // Empresa e Suporte
            'empresa'            => config('app.name', 'Minha Empresa'),
            'whatsapp_suporte'   => '(11) 98888-7777',
            'email_suporte'      => 'suporte@exemplo.com.br',
            'telefone_suporte'   => '0800 123 4567',
        ];
    }
}
