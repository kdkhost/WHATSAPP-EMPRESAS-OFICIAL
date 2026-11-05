<?php

namespace App\Modules\Contracts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Modules\Contracts\Models\Contract;
use App\Modules\Contracts\Models\ContractTemplate;
use App\Modules\Contracts\Services\ContractAccessService;
use App\Modules\Contracts\Services\ContractLimitService;
use App\Modules\Contracts\Services\ContractService;
use App\Modules\Contracts\Services\ContractTemplateCatalog;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\ManagedAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ContractsAdminController extends Controller
{
    public function __construct(
        protected ContractService $service,
        protected ContractTemplateCatalog $catalog,
        protected ContractLimitService $limitService,
        protected ContractAccessService $accessService,
    ) {}

    public function index(Request $request)
    {
        $query = $this->contractQuery()->with(['customer', 'invoice', 'template'])->orderByDesc('id');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $contracts = $query->paginate(25)->withQueryString();

        return view('contracts::admin.index', $this->viewData(compact('contracts')));
    }

    public function templates()
    {
        $tenant = $this->tenant();
        $this->catalog->ensureDefaultsForTenant($tenant);

        $query = $this->templateQuery()->orderByDesc('id');

        $templates = $query->paginate(20);
        $variables = $this->service->availableVariables();

        return view('contracts::admin.templates', $this->viewData(compact('templates', 'variables')));
    }

    public function createTemplate()
    {
        $template = new ContractTemplate([
            'background_type' => 'none',
            'layout_json' => $this->service->defaultLayout(),
            'active' => true,
        ]);
        $variables = $this->service->availableVariables();

        return view('contracts::admin.template-form', $this->viewData([
            'template' => $template,
            'variables' => $variables,
            'mode' => 'create',
        ]));
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        // Adicionar debug quando validação falhar
        try {
            $data = $request->validate([
            'name' => 'required|string|max:120',
            'category' => 'nullable|string|max:80',
            'description' => 'nullable|string|max:1000',
            'content_html' => 'required|string',
            'background_path' => 'nullable|string|max:1000',
            'background_type' => 'nullable|in:none,image,pdf',
            'layout_json' => 'nullable|string',
            'require_witness' => 'nullable|boolean',
            'active' => 'nullable|boolean',
        ], $this->validationMessages(), trans('validation.attributes'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->validator->errors()->toArray();
            \Log::warning('[ContractTemplate] validation failed', ['errors' => $errors, 'input' => $request->all()]);
            return response()->json(['ok' => false, 'message' => $e->validator->errors()->first(), 'errors' => $errors], 422);
        }

        $payload = $this->service->prepareTemplatePayload($data);
        $payload['tenant_id'] = $this->tenantId();
        $payload['slug'] = Str::slug($payload['name']) . '-' . Str::lower(Str::random(4));

        $template = ContractTemplate::create($payload);

        return response()->json([
            'ok' => true,
            'id' => $template->id,
            'redirect' => route($this->routeName('templates.edit'), $template->id),
        ]);
    }

    public function editTemplate(int $id)
    {
        $template = $this->templateQuery()->findOrFail($id);
        $variables = $this->service->availableVariables();

        return view('contracts::admin.template-form', $this->viewData([
            'template' => $template,
            'variables' => $variables,
            'mode' => 'edit',
        ]));
    }

    public function updateTemplate(Request $request, int $id): JsonResponse
    {
        $template = $this->templateQuery()->findOrFail($id);
        $currentBackground = $template->background_path;

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'category' => 'nullable|string|max:80',
            'description' => 'nullable|string|max:1000',
            'content_html' => 'required|string',
            'background_path' => 'nullable|string|max:1000',
            'background_type' => 'nullable|in:none,image,pdf',
            'layout_json' => 'nullable|string',
            'require_witness' => 'nullable|boolean',
            'active' => 'nullable|boolean',
        ], $this->validationMessages(), trans('validation.attributes'));

        $payload = $this->service->prepareTemplatePayload($data);
        $payload['slug'] = $template->slug ?: Str::slug($payload['name']) . '-' . Str::lower(Str::random(4));
        ManagedAsset::replaceIfChanged($currentBackground, $payload['background_path'] ?? null);
        $template->update($payload);

        return response()->json(['ok' => true]);
    }

    public function create(Request $request)
    {
        $tenant = $this->tenant();
        $this->catalog->ensureDefaultsForTenant($tenant);

        $templates = ContractTemplate::query()
            ->when($tenant?->id && $this->tableHasColumn('contract_templates', 'tenant_id'), fn ($query) => $query->where('tenant_id', $tenant->id))
            ->where('active', true)
            ->orderBy('name')
            ->get();

        // Cota mensal (so relevante no painel tenant)
        $quota = null;
        if ($tenant && $this->isTenantPanel()) {
            $quota = $this->limitService->getQuota($tenant);
        }

        $preferredTemplateId = $request->integer('template') ?: null;
        $selectedTemplate = $preferredTemplateId ? $templates->firstWhere('id', $preferredTemplateId) : null;

        // Timbrado padrão existente no sistema (preserva timbrado já configurado)
        $defaultBackground = ContractTemplate::query()
            ->when($tenant?->id && $this->tableHasColumn('contract_templates', 'tenant_id'), fn ($q) => $q->where('tenant_id', $tenant->id))
            ->whereNotNull('background_path')
            ->where('background_path', '!=', '')
            ->value('background_path')
            ?: Contract::query()
            ->when($tenant?->id && $this->tableHasColumn('contracts', 'tenant_id'), fn ($q) => $q->where('tenant_id', $tenant->id))
            ->whereNotNull('background_path')
            ->where('background_path', '!=', '')
            ->latest('id')
            ->value('background_path')
            ?: null;

        $defaultBackgroundType = $defaultBackground ? (str_ends_with(strtolower($defaultBackground), '.pdf') ? 'pdf' : 'image') : 'none';

        if ($selectedTemplate && empty($selectedTemplate->background_path) && $defaultBackground) {
            $selectedTemplate->background_path = $defaultBackground;
            $selectedTemplate->background_type = $defaultBackgroundType;
        }

        $preferredCustomerId = $request->integer('customer') ?: null;
        $selectedCustomer = $preferredCustomerId ? $this->customerQuery()->find($preferredCustomerId) : null;

        return view('contracts::admin.create', $this->viewData([
            'templates'              => $templates,
            'selectedTemplate'       => $selectedTemplate,
            'selectedCustomer'       => $selectedCustomer,
            'defaultBackground'     => $defaultBackground,
            'defaultBackgroundType' => $defaultBackgroundType,
            'variables'              => $this->service->availableVariables(),
            'preferredTemplateId'    => $preferredTemplateId,
            'preferredCustomerId'    => $preferredCustomerId,
            'quota'                  => $quota,
        ]));
    }

    public function searchCustomers(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $customers = $this->customerQuery()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('document', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('whatsapp', 'like', "%{$q}%");
                });
            })
            ->limit(20)
            ->get(['id', 'name', 'email', 'phone', 'whatsapp', 'document']);

        return response()->json($customers);
    }

    public function customerInvoices(int $customerId): JsonResponse
    {
        $invoices = $this->invoiceQuery()
            ->where('customer_id', $customerId)
            ->orderByDesc('id')
            ->limit(30)
            ->get(['id', 'number', 'amount', 'due_date', 'status', 'description']);

        return response()->json($invoices);
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = $this->tenant();

        // Verificar limite mensal (apenas no painel tenant)
        if ($tenant && $this->isTenantPanel() && $this->limitService->hasReachedLimit($tenant)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Limite mensal de contratos atingido para o seu plano.',
            ], 422);
        }

        // Validar entrada e logar erros para debugging quando falhar
        try {
            $data = $request->validate([
                'template_id'     => 'nullable|integer',
                'customer_id'     => 'required|exists:customers,id',
                'invoice_id'      => 'nullable|exists:invoices,id',
                'title'           => 'required|string|max:255',
                'content_html'    => 'nullable|string',
                'background_path' => 'nullable|string|max:1000',
                'background_type' => 'nullable|in:none,image,pdf',
                'expires_at'      => 'nullable|date',
                'layout_json'     => 'nullable|string',
            ], $this->validationMessages(), trans('validation.attributes'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->validator->errors()->toArray();
            \Log::warning('[ContractCreate] validation failed', ['errors' => $errors, 'input' => $request->all()]);
            return response()->json(['ok' => false, 'message' => $e->validator->errors()->first(), 'errors' => $errors], 422);
        }

        $customer = $this->customerQuery()->findOrFail((int) $data['customer_id']);
        $invoice = ! empty($data['invoice_id']) ? $this->invoiceQuery()->find((int) $data['invoice_id']) : null;
        $template = ! empty($data['template_id']) ? $this->templateQuery()->findOrFail((int) $data['template_id']) : null;

        if ($invoice && (int) $invoice->customer_id !== (int) $customer->id) {
            return response()->json([
                'ok' => false,
                'message' => 'A fatura selecionada nao pertence ao cliente informado.',
            ], 422);
        }

        $payload = $this->service->buildContractPayload($data, $customer, $invoice, $tenant, $template);
        if (! $this->tableHasColumn('contracts', 'tenant_id')) {
            unset($payload['tenant_id']);
        }
        if (! $this->tableHasColumn('contracts', 'invoice_id')) {
            unset($payload['invoice_id']);
        }
        $payload = $this->filterPayloadForTable('contracts', $payload);

        if (trim(strip_tags((string) ($payload['content_html'] ?? ''))) === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Informe o conteúdo do contrato ou selecione um template válido.',
            ], 422);
        }

        $contract = Contract::create($payload);

        if ($contract->content_html !== '') {
            $contract->update([
                'content_html' => $this->service->renderTemplateVariables(
                    $contract->content_html,
                    $this->service->buildContext($customer, $invoice, $tenant, $contract, $contract->title)
                ),
            ]);
        }

        return response()->json([
            'ok' => true,
            'id' => $contract->id,
            'public_url' => $contract->public_url,
        ]);
    }

    public function send(Request $request, int $id): JsonResponse
    {
        $contract = $this->contractQuery()->findOrFail($id);
        $channel = $request->input('channel', 'both');
        $report = $this->service->sendForSigning($contract, $channel);

        return response()->json(['ok' => true, 'report' => $report]);
    }

    public function show(int $id)
    {
        $contract = $this->contractQuery()->with(['customer', 'invoice', 'events', 'template'])->findOrFail($id);

        return view('contracts::admin.show', $this->viewData(compact('contract')));
    }

    public function edit(int $id)
    {
        $contract = $this->contractQuery()->with(['customer', 'invoice', 'template'])->findOrFail($id);

        if ($contract->isSigned()) {
            return redirect()->route($this->isTenantPanel() ? 'tenant.contracts.show' : 'admin.contracts.show', $contract->id)
                ->with('error', 'Contratos já assinados não podem ser editados para preservar a validade jurídica.');
        }

        $tenant = $this->tenant();
        $templates = $this->templateQuery()->where('active', true)->orderBy('name')->get();
        $quota = $tenant ? $this->limitService->getQuotaInfo($tenant) : null;

        // Timbrado padrão existente no sistema (preserva timbrado já configurado)
        $defaultBackground = ContractTemplate::query()
            ->when($tenant?->id && $this->tableHasColumn('contract_templates', 'tenant_id'), fn ($q) => $q->where('tenant_id', $tenant->id))
            ->whereNotNull('background_path')
            ->where('background_path', '!=', '')
            ->value('background_path')
            ?: Contract::query()
            ->when($tenant?->id && $this->tableHasColumn('contracts', 'tenant_id'), fn ($q) => $q->where('tenant_id', $tenant->id))
            ->whereNotNull('background_path')
            ->where('background_path', '!=', '')
            ->latest('id')
            ->value('background_path')
            ?: null;

        $defaultBackgroundType = $defaultBackground ? (str_ends_with(strtolower($defaultBackground), '.pdf') ? 'pdf' : 'image') : 'none';

        return view('contracts::admin.edit', $this->viewData([
            'contract'               => $contract,
            'templates'              => $templates,
            'selectedTemplate'       => $contract->template,
            'selectedCustomer'       => $contract->customer,
            'selectedInvoice'        => $contract->invoice,
            'defaultBackground'     => $defaultBackground,
            'defaultBackgroundType' => $defaultBackgroundType,
            'variables'              => $this->service->availableVariables(),
            'quota'                  => $quota,
        ]));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $contract = $this->contractQuery()->findOrFail($id);

        if ($contract->isSigned()) {
            return response()->json([
                'ok' => false,
                'message' => 'Contratos já assinados não podem ser alterados para preservar a validade jurídica.',
            ], 422);
        }

        try {
            $data = $request->validate([
                'template_id'     => 'nullable|integer',
                'customer_id'     => 'required|exists:customers,id',
                'invoice_id'      => 'nullable|exists:invoices,id',
                'title'           => 'required|string|max:255',
                'content_html'    => 'nullable|string',
                'background_path' => 'nullable|string|max:1000',
                'background_type' => 'nullable|in:none,image,pdf',
                'expires_at'      => 'nullable|date',
                'layout_json'     => 'nullable|string',
            ], $this->validationMessages(), trans('validation.attributes'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->validator->errors()->toArray();
            \Log::warning('[ContractUpdate] validation failed', ['errors' => $errors, 'input' => $request->all()]);
            return response()->json(['ok' => false, 'message' => $e->validator->errors()->first(), 'errors' => $errors], 422);
        }

        $customer = $this->customerQuery()->findOrFail((int) $data['customer_id']);
        $invoice = ! empty($data['invoice_id']) ? $this->invoiceQuery()->find((int) $data['invoice_id']) : null;

        if ($invoice && (int) $invoice->customer_id !== (int) $customer->id) {
            return response()->json([
                'ok' => false,
                'message' => 'A fatura selecionada não pertence ao cliente informado.',
            ], 422);
        }

        $layoutJson = null;
        if (! empty($data['layout_json'])) {
            $decoded = json_decode((string) $data['layout_json'], true);
            $layoutJson = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        $updateData = [
            'customer_id'     => $customer->id,
            'invoice_id'      => $invoice?->id,
            'title'           => $data['title'],
            'content_html'    => $data['content_html'] ?? $contract->content_html,
            'background_path' => $data['background_path'] ?? null,
            'background_type' => $data['background_type'] ?? 'none',
            'expires_at'      => ! empty($data['expires_at']) ? Carbon::parse($data['expires_at']) : null,
        ];

        if (! empty($data['template_id'])) {
            $updateData['template_id'] = (int) $data['template_id'];
        }

        if ($layoutJson !== null) {
            $updateData['layout_json'] = $layoutJson;
        }

        if (! $this->tableHasColumn('contracts', 'invoice_id')) {
            unset($updateData['invoice_id']);
        }

        $updateData = $this->filterPayloadForTable('contracts', $updateData);

        if (trim(strip_tags((string) ($updateData['content_html'] ?? ''))) === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Informe o conteúdo do contrato.',
            ], 422);
        }

        $contract->update($updateData);

        $contract->recordEvent('contract_updated', $request->ip(), $request->userAgent(), [
            'title' => $contract->title,
            'user'  => Auth::user()?->name ?? 'Admin',
        ]);

        return response()->json([
            'ok' => true,
            'id' => $contract->id,
            'public_url' => $contract->public_url,
            'message' => 'Contrato atualizado com sucesso!',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $contract = $this->contractQuery()->findOrFail($id);
        ManagedAsset::deleteByUrl($contract->background_path);
        $contract->delete();

        return response()->json(['ok' => true]);
    }

    protected function tenant(): ?Tenant
    {
        if (! $this->isTenantPanel()) {
            return null;
        }

        // Guard web = tenant logado normalmente
        if (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
            if (! $user) {
                return null;
            }
            $tenant = Tenant::where('user_id', $user->id)->first();
            if (! $tenant && ! empty($user->tenant_id)) {
                $tenant = Tenant::find($user->tenant_id);
            }
            return $tenant;
        }

        // Guard admin em modo assistido também pode operar no painel tenant
        if (Auth::guard('admin')->check()) {
            $admin = Auth::guard('admin')->user();
            // Assistido: tenant_id definido na sessão
            $assistedTenantId = session('assist_tenant_id');
            if ($assistedTenantId) {
                return Tenant::find((int) $assistedTenantId);
            }
            // Admin acessando /painel/* diretamente: usar tenant do admin
            $user = \App\Models\User::where('email', $admin->email)->first();
            if ($user) {
                $tenant = Tenant::where('user_id', $user->id)->first();
                if (! $tenant && ! empty($user->tenant_id)) {
                    $tenant = Tenant::find($user->tenant_id);
                }
                return $tenant;
            }
        }

        return null;
    }

    protected function tenantId(): ?int
    {
        return $this->tenant()?->id;
    }

    protected function templateQuery()
    {
        $tenantId = $this->tenantId();

        // Fail-closed: se no painel tenant mas sem tenant resolvido, nega acesso
        if ($this->isTenantPanel() && $tenantId === null) {
            abort(403, 'Contexto de tenant não identificado.');
        }

        return ContractTemplate::query()->when(
            $tenantId !== null,
            fn ($query) => $query->where('tenant_id', $tenantId)
        );
    }

    protected function contractQuery()
    {
        $tenantId = $this->tenantId();

        if ($this->isTenantPanel() && $tenantId === null) {
            abort(403, 'Contexto de tenant não identificado.');
        }

        return Contract::query()->when(
            $tenantId !== null,
            fn ($query) => $query->where('tenant_id', $tenantId)
        );
    }

    protected function customerQuery()
    {
        $tenantId = $this->tenantId();

        if ($this->isTenantPanel() && $tenantId === null) {
            abort(403, 'Contexto de tenant não identificado.');
        }

        return Customer::query()->when(
            $tenantId !== null,
            fn ($query) => $query->where('tenant_id', $tenantId)
        );
    }

    protected function invoiceQuery()
    {
        $tenantId = $this->tenantId();

        if ($this->isTenantPanel() && $tenantId === null) {
            abort(403, 'Contexto de tenant não identificado.');
        }

        return Invoice::query()->when(
            $tenantId !== null,
            fn ($query) => $query->where('tenant_id', $tenantId)
        );
    }

    protected function tableHasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;

        return $cache[$key] ??= Schema::hasColumn($table, $column);
    }

    protected function filterPayloadForTable(string $table, array $payload): array
    {
        if (! Schema::hasTable($table)) {
            return $payload;
        }

        static $columnsCache = [];
        $columnsCache[$table] ??= Schema::getColumnListing($table);

        return array_filter(
            $payload,
            fn ($value, $key) => in_array($key, $columnsCache[$table], true),
            ARRAY_FILTER_USE_BOTH
        );
    }

    protected function validationMessages(): array
    {
        return [
            'required' => 'O campo :attribute e obrigatorio.',
            'exists' => 'O :attribute selecionado e invalido.',
            'date' => 'O campo :attribute deve ser uma data valida.',
            'integer' => 'O campo :attribute deve ser um numero inteiro.',
            'string' => 'O campo :attribute deve ser um texto valido.',
            'max' => 'O campo :attribute nao pode ser maior que :max caracteres.',
            'in' => 'O :attribute selecionado e invalido.',
            'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
        ];
    }

    protected function isTenantPanel(): bool
    {
        return request()->is('painel*');
    }

    protected function routePrefix(): string
    {
        return $this->isTenantPanel() ? 'tenant.contracts.' : 'admin.contracts.';
    }

    protected function routeName(string $name): string
    {
        return $this->routePrefix() . $name;
    }

    protected function contractBaseUrl(): string
    {
        return url($this->isTenantPanel() ? '/painel/contratos' : '/admin/contracts');
    }

    protected function contractUploadUrl(): string
    {
        return route($this->isTenantPanel() ? 'tenant.uploads.store' : 'admin.uploads.store', 'contracts');
    }

    protected function viewData(array $data = []): array
    {
        return array_merge($data, [
            'contractRoutePrefix' => $this->routePrefix(),
            'contractBaseUrl' => $this->contractBaseUrl(),
            'contractUploadUrl' => $this->contractUploadUrl(),
        ]);
    }
}
