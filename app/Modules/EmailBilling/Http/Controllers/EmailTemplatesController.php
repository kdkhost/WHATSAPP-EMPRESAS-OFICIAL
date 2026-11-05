<?php

namespace App\Modules\EmailBilling\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\EmailBilling\Models\EmailTemplate;
use App\Modules\EmailBilling\Services\EmailBillingTenantResolver;
use App\Modules\EmailBilling\Services\EmailLayoutRenderer;
use App\Modules\EmailBilling\Services\EmailTemplateRenderer;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Services\TenantTemplateResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Editor de templates de email do tenant.
 * Preview em tempo real durante a digitacao (igual templates de WhatsApp).
 */
class EmailTemplatesController extends Controller
{
    public function __construct(
        protected EmailTemplateRenderer $renderer,
        protected EmailBillingTenantResolver $tenantResolver,
        protected EmailLayoutRenderer $layoutRenderer,
        protected TenantTemplateResolver $templateResolver
    ) {}

    public function index(Request $request)
    {
        $tenant = $this->tenant($request);
        $templates = EmailTemplate::where('tenant_id', $tenant->id)
            ->orderByDesc('id')->paginate(20);
        $routeNames = $this->routeNames($request);
        $templateBaseUrl = $this->templateBaseUrl($request);
        $preferredTemplateIds = $this->templateResolver->preferenceIds($tenant, 'email');

        return view('email-billing::templates.index', compact('templates', 'routeNames', 'templateBaseUrl', 'preferredTemplateIds'));
    }

    public function create(Request $request)
    {
        $tenant = $this->tenant($request);
        $template = new EmailTemplate(['tenant_id' => $tenant->id, 'active' => true]);
        $variables = EmailTemplate::availableVariables();
        $routeNames = $this->routeNames($request);
        $templateBaseUrl = $this->templateBaseUrl($request);
        $previewDefaults = $this->previewDefaults($tenant);

        return view('email-billing::templates.form', compact('template', 'variables', 'routeNames', 'templateBaseUrl', 'previewDefaults'));
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $data = $this->validateData($request);
        $setDefault = (bool) ($data['set_default'] ?? false);
        unset($data['set_default']);
        $data['tenant_id'] = $tenant->id;
        $data['slug'] = Str::slug($data['name']) . '-' . Str::random(4);
        $data['scope'] = 'tenant';
        $data['is_system'] = false;
        $data['is_default'] = false;
        $data['locked'] = false;

        $tpl = EmailTemplate::create($data);
        if ($setDefault) {
            $this->templateResolver->setDefault($tenant, 'email', $tpl);
        }

        return response()->json([
            'ok'       => true,
            'id'       => $tpl->id,
            'redirect' => route($this->routeNames($request)['templatesEdit'], $tpl->id),
        ]);
    }

    public function edit(Request $request, int $id)
    {
        $tenant = $this->tenant($request);
        $template = EmailTemplate::where('tenant_id', $tenant->id)->findOrFail($id);
        $variables = EmailTemplate::availableVariables();
        $routeNames = $this->routeNames($request);
        $templateBaseUrl = $this->templateBaseUrl($request);
        $previewDefaults = $this->previewDefaults($tenant);

        return view('email-billing::templates.form', compact('template', 'variables', 'routeNames', 'templateBaseUrl', 'previewDefaults'));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenant = $this->tenant($request);
        $template = EmailTemplate::where('tenant_id', $tenant->id)->findOrFail($id);
        if ($template->locked || $template->is_system) {
            return response()->json(['ok' => false, 'error' => 'Templates do sistema nao podem ser alterados pelo tenant.'], 403);
        }
        $data = $this->validateData($request);
        unset($data['set_default']);
        $template->update($data);
        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenant = $this->tenant($request);
        $template = EmailTemplate::where('tenant_id', $tenant->id)->findOrFail($id);
        if ($template->locked || $template->is_system) {
            return response()->json(['ok' => false, 'error' => 'Templates do sistema nao podem ser excluidos.'], 403);
        }
        $template->delete();
        return response()->json(['ok' => true]);
    }

    public function setDefault(Request $request, int $id): JsonResponse
    {
        $tenant = $this->tenant($request);
        $template = EmailTemplate::where('tenant_id', $tenant->id)
            ->where('active', true)
            ->findOrFail($id);

        $this->templateResolver->setDefault($tenant, 'email', $template);

        return response()->json(['ok' => true]);
    }

    /** Preview AJAX em tempo real. */
    public function preview(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);
        $subject = (string) $request->input('subject', '');
        $bodyHtml = (string) $request->input('body_html', '');
        $sample = (array) $request->input('sample', []);
        $branding = $this->layoutRenderer->brandingFromTenantConfig($tenant->emailConfig, $tenant);
        $sample['empresa'] = $sample['empresa'] ?? $branding['brand_name'];
        $sample['logo_empresa'] = $sample['logo_empresa'] ?? $branding['logo_url'];
        $sample['email_branding'] = array_merge($branding, (array) ($sample['email_branding'] ?? []));

        return response()->json($this->renderer->preview($subject, $bodyHtml, $sample));
    }

    protected function previewDefaults(Tenant $tenant): array
    {
        $branding = $this->layoutRenderer->brandingFromTenantConfig($tenant->emailConfig, $tenant);

        return [
            'brand_name' => $branding['brand_name'],
            'logo_url' => $branding['logo_url'],
            'primary_color' => $branding['primary_color'],
            'accent_color' => $branding['accent_color'],
            'footer_signature' => $branding['footer_signature'],
        ];
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'name'            => 'required|string|max:120',
            'category'        => 'required|in:cobranca,lembrete,atraso,agradecimento,custom',
            'schedule_offset' => 'nullable|string|max:30',
            'subject'         => 'required|string|max:255',
            'body_html'       => 'required|string',
            'body_text'       => 'nullable|string',
            'active'          => 'sometimes|boolean',
            'set_default'     => 'sometimes|boolean',
        ]);
    }

    protected function tenant(Request $request): Tenant
    {
        return $this->tenantResolver->resolve($request);
    }

    protected function routeNames(Request $request): array
    {
        $prefix = $request->routeIs('admin.email.*') ? 'admin.email.' : 'tenant.email.';

        return [
            'templates' => $prefix.'templates',
            'templatesCreate' => $prefix.'templates.create',
            'templatesStore' => $prefix.'templates.store',
            'templatesEdit' => $prefix.'templates.edit',
            'templatesPreview' => $prefix.'templates.preview',
        ];
    }

    protected function templateBaseUrl(Request $request): string
    {
        return $request->routeIs('admin.email.*')
            ? url('/admin/email/templates')
            : url('/painel/email/templates');
    }
}
