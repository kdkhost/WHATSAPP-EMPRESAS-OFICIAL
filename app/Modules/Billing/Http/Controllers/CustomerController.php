<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    /** Retorna tenant_id ativo. Null = superadmin (vê tudo). */
    protected function activeTenantId(): ?int
    {
        if (Auth::guard('web')->check()) {
            return Tenant::where('user_id', Auth::guard('web')->id())->value('id');
        }
        return null;
    }

    public function index(Request $request)
    {
        $tenantId = $this->activeTenantId();

        $q = Customer::with('tenant')
            ->withCount(['installments' => fn ($q) => $q->whereIn('status', ['pending', 'overdue'])])
            ->orderByDesc('id');

        // Isolamento: tenant vê apenas seus clientes; superadmin pode filtrar por tenant_id param
        if ($tenantId !== null) {
            $q->where('tenant_id', $tenantId);
        } else {
            if ($filterTenantId = $request->query('tenant_id')) {
                if ($filterTenantId === 'master') {
                    $q->whereNull('tenant_id');
                } else {
                    $q->where('tenant_id', (int) $filterTenantId);
                }
            } else {
                // Por padrão, superadmin vê os clientes da base principal (master ou tenant 1 da KDKHOST)
                $q->where(fn ($w) => $w->whereNull('tenant_id')->orWhere('tenant_id', 1));
            }
        }

        if ($search = $request->query('q')) {
            $q->where(fn ($w) => $w
                ->where('name', 'like', "%$search%")
                ->orWhere('email', 'like', "%$search%")
                ->orWhere('document', 'like', "%$search%")
                ->orWhere('whatsapp', 'like', "%$search%"));
        }
        $customers = $q->get();

        // Stats escopadas ao mesmo filtro
        if ($tenantId !== null) {
            $base = Customer::where('tenant_id', $tenantId);
        } else {
            if ($filterTenantId = $request->query('tenant_id')) {
                if ($filterTenantId === 'master') {
                    $base = Customer::whereNull('tenant_id');
                } else {
                    $base = Customer::where('tenant_id', (int) $filterTenantId);
                }
            } else {
                $base = Customer::where(fn ($w) => $w->whereNull('tenant_id')->orWhere('tenant_id', 1));
            }
        }

        $stats = [
            'total'             => (clone $base)->count(),
            'with_whatsapp'     => (clone $base)->whereNotNull('whatsapp')->where('whatsapp', '!=', '')->count(),
            'with_email'        => (clone $base)->whereNotNull('email')->count(),
            'new_30d'           => (clone $base)->where('created_at', '>=', now()->subDays(30))->count(),
            'with_tenant'       => (clone $base)->whereNotNull('tenant_id')->count(),
            'with_installments' => (clone $base)->whereHas('installments')->count(),
        ];

        return view('billing::customers.index', compact('customers', 'stats'));
    }

    public function syncWhmcs(): JsonResponse
    {
        $tenantId = $this->activeTenantId();

        $query = \App\Modules\WhmcsIntegration\Models\WhmcsConnection::query()->where('active', true);
        
        if (Auth::guard('admin')->check()) {
            // Master pode ter a conexão salva com tenant_id null ou 1
            $connection = clone $query;
            $connection = $connection->where(function($q) {
                $q->whereNull('tenant_id')->orWhere('tenant_id', 1);
            })->first();
            
            if ($connection) {
                // Força tenant_id nulo na execução atual para que os clientes sejam importados
                // para a base global do superadmin (onde tenant_id IS NULL)
                $connection->tenant_id = null;
            }
        } else {
            $connection = $query->where('tenant_id', $tenantId)->first();
        }

        if (!$connection) {
            return response()->json([
                'ok' => false,
                'error' => 'Nenhuma conexão WHMCS ativa encontrada.',
            ], 422);
        }

        try {
            $client = new \App\Modules\WhmcsIntegration\Services\WhmcsClient($connection);
            $stats = $client->syncCustomers();

            return response()->json([
                'ok' => true,
                'message' => "Sincronização concluída! Foram importados {$stats['imported']} e atualizados {$stats['updated']} clientes.",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'Erro ao sincronizar com WHMCS: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function create()
    {
        $customer = new Customer();
        return view('billing::customers.form', compact('customer'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateCustomer($request);
        $data['active']    = true;
        
        $meta = [];
        if ($request->hasFile('avatar')) {
            $meta['avatar'] = $request->file('avatar')->store('customers/avatars', 'public');
        }
        $data['meta'] = $meta;

        // Associar ao tenant ativo se existir
        $tenantId = $this->activeTenantId();
        if ($tenantId !== null) {
            $data['tenant_id'] = $tenantId;
        }
        $customer = Customer::create($data);

        try {
            if ($tenantId !== null) {
                app(\App\Modules\Notifications\Services\NotificationDispatcher::class)->send([
                    'channels'   => ['in_app'],
                    'group'      => 'registration',
                    'type'       => 'new_customer',
                    'title'      => 'Novo Cliente Cadastrado',
                    'message'    => "Cliente {$customer->name} adicionado com sucesso.",
                    'url'        => route('tenant.customers.index'),
                    'icon'       => 'bi-person-plus-fill',
                    'color'      => 'info',
                    'recipients' => ['tenant_id' => $tenantId],
                ]);
            } else {
                app(\App\Modules\Notifications\Services\NotificationDispatcher::class)->send([
                    'channels'   => ['in_app'],
                    'group'      => 'registration',
                    'type'       => 'new_customer',
                    'title'      => 'Novo Cliente (Master)',
                    'message'    => "Cliente {$customer->name} cadastrado na base principal.",
                    'url'        => route('admin.billing.customers.index'),
                    'icon'       => 'bi-person-plus-fill',
                    'color'      => 'info',
                    'recipients' => ['all_admins' => true],
                ]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[Customer] Falha ao despachar notificacao de novo cliente: ' . $e->getMessage());
        }

        return response()->json([
            'ok'       => true,
            'id'       => $customer->id,
            'redirect' => route('admin.billing.customers.index'),
        ]);
    }

    public function edit(int $id)
    {
        $tenantId = $this->activeTenantId();
        $customer = Customer::when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->findOrFail($id);
        return view('billing::customers.form', compact('customer'));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->activeTenantId();
        $customer = Customer::when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->findOrFail($id);
        $data = $this->validateCustomer($request);
        
        $meta = $customer->meta ?? [];
        if ($request->hasFile('avatar')) {
            $meta['avatar'] = $request->file('avatar')->store('customers/avatars', 'public');
        } elseif ($request->input('remove_avatar')) {
            unset($meta['avatar']);
        }
        $data['meta'] = $meta;

        $customer->update($data);

        // Sincronização bidirecional: Se o cliente veio do WHMCS, atualiza também os campos no WHMCS
        if (!empty($customer->meta['whmcs_id'])) {
            try {
                $whmcsConn = \App\Modules\WhmcsIntegration\Models\WhmcsConnection::where('active', true)
                    ->when($customer->tenant_id, fn($q) => $q->where('tenant_id', $customer->tenant_id))
                    ->first() ?: \App\Modules\WhmcsIntegration\Models\WhmcsConnection::where('active', true)->first();

                if ($whmcsConn) {
                    $whmcsClient = new \App\Modules\WhmcsIntegration\Services\WhmcsClient($whmcsConn);
                    $whmcsClient->pushCustomerToWhmcs($customer);
                }
            } catch (\Throwable) {}
        }

        // Se optin foi revogado, registra no WhatsAppSafetyService
        if (isset($data['whatsapp_optin']) && $data['whatsapp_optin'] === false && ! empty($customer->whatsapp)) {
            try {
                app(\App\Modules\WhatsAppIntegration\Services\WhatsAppSafetyService::class)
                    ->markOptOut($customer->whatsapp, 'Painel Admin');
            } catch (\Throwable) {}
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(int $id): JsonResponse
    {
        $tenantId = $this->activeTenantId();
        $customer = Customer::when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->findOrFail($id);

        if ($customer->invoices()->exists()) {
            return response()->json([
                'ok'    => false,
                'error' => 'Nao e possivel excluir cliente com faturas vinculadas.',
            ], 422);
        }

        $customer->delete();
        return response()->json(['ok' => true]);
    }

    protected function validateCustomer(Request $request): array
    {
        $data = $request->validate([
            'avatar'         => 'nullable|image|max:2048',
            'name'           => 'required|string|max:120',
            'email'          => 'nullable|email|max:255',
            'document'       => 'nullable|string|max:25',
            'cpf'            => 'nullable|string|max:20',
            'cnpj'           => 'nullable|string|max:20',
            'person_type'    => 'nullable|string|max:20',
            'birth_date'     => 'nullable|date',
            'whatsapp_optin' => 'nullable|boolean',
            'phone'          => 'nullable|string|max:25',
            'whatsapp'       => 'nullable|string|max:25',
            'cep'            => 'nullable|string|max:9',
            'logradouro'     => 'nullable|string|max:255',
            'numero'         => 'nullable|string|max:20',
            'complemento'    => 'nullable|string|max:255',
            'bairro'         => 'nullable|string|max:120',
            'cidade'         => 'nullable|string|max:120',
            'uf'             => 'nullable|string|max:2',
        ]);

        if ($request->has('whatsapp_optin')) {
            $data['whatsapp_optin'] = $request->boolean('whatsapp_optin');
        }

        // Sanitização de CPF e CNPJ
        $cleanDoc = !empty($data['document']) ? preg_replace('/\D/', '', $data['document']) : null;
        $cleanCpf = !empty($data['cpf']) ? preg_replace('/\D/', '', $data['cpf']) : null;
        $cleanCnpj = !empty($data['cnpj']) ? preg_replace('/\D/', '', $data['cnpj']) : null;

        if ($cleanCpf) {
            $data['cpf'] = $cleanCpf;
            if (empty($data['document'])) $data['document'] = $cleanCpf;
            if (empty($data['person_type'])) $data['person_type'] = 'PF';
        }

        if ($cleanCnpj) {
            $data['cnpj'] = $cleanCnpj;
            if (empty($data['document'])) $data['document'] = $cleanCnpj;
            if (empty($data['person_type'])) $data['person_type'] = 'PJ';
        }

        if ($cleanDoc && empty($data['cpf']) && empty($data['cnpj'])) {
            if (strlen($cleanDoc) === 11) {
                $data['cpf'] = $cleanDoc;
                if (empty($data['person_type'])) $data['person_type'] = 'PF';
            } elseif (strlen($cleanDoc) === 14) {
                $data['cnpj'] = $cleanDoc;
                if (empty($data['person_type'])) $data['person_type'] = 'PJ';
            }
        }

        return $data;
    }
}
