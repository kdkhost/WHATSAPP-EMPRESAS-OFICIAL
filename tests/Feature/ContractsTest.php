<?php

use App\Models\AdminUser;
use App\Models\Customer;
use App\Models\User;
use App\Modules\Contracts\Models\Contract;
use App\Modules\Contracts\Models\ContractEvent;
use App\Modules\Contracts\Models\ContractTemplate;
use App\Modules\Contracts\Services\ContractService;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInstance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('registra assinatura e gera artefato final do contrato', function () {
    Storage::fake('local');

    $customer = Customer::create([
        'name' => 'Cliente Contrato',
        'email' => 'cliente-contrato@example.com',
        'document' => '12345678901',
    ]);

    $template = ContractTemplate::create([
        'name' => 'Prestação de Serviços',
        'slug' => 'prestacao-servicos-teste',
        'category' => 'Serviços',
        'content_html' => '<p>Contrato de teste para {nome} em {data_atual}.</p>',
        'variables' => ['{nome}', '{data_atual}'],
        'background_type' => 'none',
        'layout_json' => app(ContractService::class)->defaultLayout(),
        'active' => true,
    ]);

    $contract = Contract::create([
        'template_id' => $template->id,
        'customer_id' => $customer->id,
        'title' => 'Contrato de Teste',
        'content_html' => '<p>Contrato pronto para assinatura.</p>',
        'background_type' => 'none',
        'layout_json' => app(ContractService::class)->defaultLayout(),
        'status' => 'sent',
    ]);

    $response = $this->postJson('/contrato/' . $contract->access_token . '/assinar', [
        'signer_name' => 'Cliente Contrato',
        'signer_email' => 'cliente-contrato@example.com',
        'signer_document' => '123.456.789-01',
        'signature_image' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgN6lJ1EAAAAASUVORK5CYII=',
        'return_channel' => 'email',
        'accept_terms' => '1',
    ]);

    $response->assertOk()->assertJson(['ok' => true]);

    $contract->refresh();

    expect($contract->status)->toBe('signed');
    expect($contract->signed_html_path)->not->toBeNull();
    expect(Storage::disk('local')->exists($contract->signed_html_path))->toBeTrue();

    if (extension_loaded('gd')) {
        expect($contract->signed_pdf_path)->not->toBeNull();
        expect(Storage::disk('local')->exists($contract->signed_pdf_path))->toBeTrue();
    } else {
        expect($contract->signed_pdf_path)->toBeNull();
    }
});

it('exibe editor premium de template com upload drag and drop de timbrado', function () {
    $plan = Plan::create([
        'name' => 'Plano Contratos',
        'slug' => 'plano-contratos',
        'price' => 0,
        'billing_cycle' => 'monthly',
        'trial_days' => 7,
        'max_instances' => 1,
        'max_customers' => 100,
        'max_invoices_month' => 100,
        'max_messages_month' => 100,
        'custom_gateway' => true,
        'custom_templates' => true,
        'installment_enabled' => true,
        'fee_passthrough' => false,
        'active' => true,
    ]);

    $user = User::create([
        'name' => 'Tenant Contratos',
        'email' => 'tenant-contratos@example.com',
        'password' => 'secret',
    ]);

    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Tenant Contratos',
        'slug' => 'tenant-contratos',
        'status' => 'active',
        'current_period_start' => now()->toDateString(),
        'current_period_end' => now()->addMonth()->toDateString(),
    ]);

    $template = ContractTemplate::create([
        'tenant_id' => $tenant->id,
        'name' => 'Contrato Premium',
        'slug' => 'contrato-premium',
        'category' => 'Servicos',
        'content_html' => '<p>Contrato de teste.</p>',
        'variables' => ['{nome}'],
        'background_type' => 'none',
        'layout_json' => app(ContractService::class)->defaultLayout(),
        'active' => true,
    ]);

    $this->actingAs($user, 'web')
        ->get(route('tenant.contracts.templates.edit', $template->id))
        ->assertOk()
        ->assertSee('contract-premium-hero', false)
        ->assertSee('contract-letterhead-upload', false)
        ->assertSee(route('tenant.uploads.store', 'contracts'), false)
        ->assertSee('note-editor', false)
        ->assertSee('pdfjs-dist@3.11.174', false)
        ->assertSee('.note-editor .note-editing-area .note-editable *', false);
});

it('impede tenant de criar contrato para cliente de outro tenant', function () {
    $plan = Plan::create([
        'name' => 'Plano Isolamento Contratos',
        'slug' => 'plano-isolamento-contratos',
        'price' => 0,
        'billing_cycle' => 'monthly',
        'trial_days' => 7,
        'max_instances' => 1,
        'max_customers' => 100,
        'max_invoices_month' => 100,
        'max_messages_month' => 100,
        'custom_gateway' => true,
        'custom_templates' => true,
        'installment_enabled' => true,
        'fee_passthrough' => false,
        'active' => true,
    ]);

    $owner = User::create([
        'name' => 'Tenant Dono',
        'email' => 'tenant-dono-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);

    $otherOwner = User::create([
        'name' => 'Tenant Outro',
        'email' => 'tenant-outro-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);

    $tenant = Tenant::create([
        'user_id' => $owner->id,
        'plan_id' => $plan->id,
        'company_name' => 'Tenant Dono',
        'slug' => 'tenant-dono-'.uniqid(),
        'status' => 'active',
        'current_period_start' => now()->toDateString(),
        'current_period_end' => now()->addMonth()->toDateString(),
    ]);

    $otherTenant = Tenant::create([
        'user_id' => $otherOwner->id,
        'plan_id' => $plan->id,
        'company_name' => 'Tenant Outro',
        'slug' => 'tenant-outro-'.uniqid(),
        'status' => 'active',
        'current_period_start' => now()->toDateString(),
        'current_period_end' => now()->addMonth()->toDateString(),
    ]);

    $foreignCustomer = Customer::create([
        'tenant_id' => $otherTenant->id,
        'name' => 'Cliente de Outro Tenant',
        'email' => 'cliente-outro-'.uniqid().'@example.com',
    ]);

    $this->actingAs($owner)
        ->postJson(route('tenant.contracts.store'), [
            'customer_id' => $foreignCustomer->id,
            'title' => 'Contrato indevido',
            'content_html' => '<p>Contrato nao deve ser criado.</p>',
        ])
        ->assertNotFound();

    expect(Contract::where('tenant_id', $tenant->id)->where('title', 'Contrato indevido')->exists())->toBeFalse();
});

it('abre contratos pelo painel superadmin sem cair na rota tenant', function () {
    $admin = AdminUser::create([
        'name' => 'Super Contratos',
        'email' => 'super-contratos@example.com',
        'password' => 'secret',
        'role' => 'superadmin',
        'active' => true,
        'two_factor_enabled' => false,
    ]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.contracts.index'))
        ->assertOk()
        ->assertSee('Contratos Digitais')
        ->assertDontSee('/painel/contratos', false);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(route('admin.contracts.index'), false)
        ->assertDontSee('href="http://localhost/painel/contratos"', false);
});

it('abre detalhe do contrato mesmo com evento antigo sem data', function () {
    $plan = Plan::create([
        'name' => 'Plano Historico Contratos',
        'slug' => 'plano-historico-contratos',
        'price' => 0,
        'billing_cycle' => 'monthly',
        'trial_days' => 7,
        'max_instances' => 1,
        'max_customers' => 100,
        'max_invoices_month' => 100,
        'max_messages_month' => 100,
        'custom_gateway' => true,
        'custom_templates' => true,
        'installment_enabled' => true,
        'fee_passthrough' => false,
        'active' => true,
    ]);

    $user = User::create([
        'name' => 'Tenant Historico',
        'email' => 'tenant-historico-contratos@example.com',
        'password' => 'secret',
    ]);

    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Tenant Historico',
        'slug' => 'tenant-historico-contratos',
        'status' => 'active',
        'current_period_start' => now()->toDateString(),
        'current_period_end' => now()->addMonth()->toDateString(),
    ]);

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cliente Historico',
        'email' => 'cliente-historico@example.com',
    ]);

    $contract = Contract::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'title' => 'Contrato com evento antigo',
        'content_html' => '<p>Contrato para historico.</p>',
        'background_type' => 'none',
        'layout_json' => app(ContractService::class)->defaultLayout(),
        'status' => 'sent',
    ]);

    $event = new ContractEvent([
        'contract_id' => $contract->id,
        'event' => 'legacy_event',
        'ip' => null,
        'user_agent' => null,
        'metadata' => null,
        'created_at' => null,
    ]);

    $contract->setRelation('customer', $customer);
    $contract->setRelation('invoice', null);
    $contract->setRelation('template', null);
    $contract->setRelation('events', collect([$event]));
    request()->server->set('REQUEST_URI', '/painel/contratos/'.$contract->id);

    $this->actingAs($user)
        ->view('contracts::admin.show', [
            'contract' => $contract,
            'contractRoutePrefix' => 'tenant.contracts.',
            'contractBaseUrl' => url('/painel/contratos'),
            'contractUploadUrl' => route('tenant.uploads.store', 'contracts'),
        ])
        ->assertSee('legacy event')
        ->assertSee('Data nao registrada');
});

it('exibe pagina publica tratada quando o contrato esta expirado', function () {
    $customer = Customer::create([
        'name' => 'Cliente Expirado',
        'email' => 'cliente-expirado@example.com',
        'document' => '12345678901',
    ]);

    $contract = Contract::create([
        'customer_id' => $customer->id,
        'title' => 'Contrato Expirado',
        'content_html' => '<p>Contrato fora do prazo.</p>',
        'background_type' => 'none',
        'layout_json' => app(ContractService::class)->defaultLayout(),
        'status' => 'sent',
        'expires_at' => now()->subMinute(),
    ]);

    $this->get(route('public.contracts.show', $contract->access_token))
        ->assertStatus(410)
        ->assertSee('Este contrato expirou')
        ->assertSee($contract->number);
});

it('renderiza timbrado em pdf como base do documento sem iframe de navegador', function () {
    $customer = Customer::create([
        'name' => 'Cliente Timbrado PDF',
        'email' => 'cliente-timbrado-pdf@example.com',
        'document' => '12345678901',
    ]);

    $contract = Contract::create([
        'customer_id' => $customer->id,
        'title' => 'Contrato com Timbrado PDF',
        'content_html' => '<p>Conteudo sobre o timbrado.</p>',
        'background_type' => 'pdf',
        'background_path' => 'contracts/timbrado.pdf',
        'layout_json' => app(ContractService::class)->defaultLayout(),
        'status' => 'sent',
    ]);

    $html = app(ContractService::class)->renderDocumentHtml($contract);

    expect($html)
        ->toContain('contract-sheet-base-style')
        ->toContain('contract-sheet-pdf-canvas')
        ->toContain('data-contract-pdf-bg')
        ->toContain('pdfjs-dist@3.11.174')
        ->not->toContain('<iframe');
});

it('envia contrato por whatsapp usando a instancia padrao do tenant', function () {
    config()->set('whatsapp_anti_ban.business_hours.enabled', false);
    config()->set('whatsapp_anti_ban.human_delay.enabled', false);

    Http::fake([
        'https://evo.test/send/text' => Http::response(['key' => ['id' => 'ctr-msg-1']], 201),
    ]);

    $plan = Plan::create([
        'name' => 'Plano Contrato WhatsApp',
        'slug' => 'plano-contrato-whatsapp-'.uniqid(),
        'price' => 0,
        'billing_cycle' => 'monthly',
        'trial_days' => 7,
        'max_instances' => 1,
        'max_customers' => 100,
        'max_invoices_month' => 100,
        'max_messages_month' => 100,
        'custom_gateway' => true,
        'custom_templates' => true,
        'installment_enabled' => true,
        'fee_passthrough' => false,
        'active' => true,
    ]);

    $user = User::create([
        'name' => 'Tenant Contrato WhatsApp',
        'email' => 'tenant-contrato-whatsapp-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);

    $tenant = Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Tenant Contrato WhatsApp',
        'slug' => 'tenant-contrato-whatsapp-'.uniqid(),
        'status' => 'active',
    ]);

    $instance = TenantInstance::create([
        'tenant_id' => $tenant->id,
        'instance_name' => 'tenant_contrato_'.$tenant->id,
        'display_name' => 'Atendimento Contratos',
        'status' => 'connected',
        'is_default' => true,
        'api_base_url' => 'https://evo.test',
        'api_key' => 'tenant-token',
        'webhook_secret' => 'secret-token',
    ]);

    $customer = Customer::create([
        'tenant_id' => $tenant->id,
        'name' => 'Cliente WhatsApp',
        'email' => 'cliente-whatsapp@example.com',
        'whatsapp' => '21999998888',
    ]);

    $contract = Contract::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'title' => 'Contrato via WhatsApp',
        'content_html' => '<p>Contrato via WhatsApp.</p>',
        'background_type' => 'none',
        'layout_json' => app(ContractService::class)->defaultLayout(),
        'status' => 'draft',
    ]);

    $result = app(ContractService::class)->sendForSigning($contract, 'whatsapp');

    expect($result['whatsapp'])->toBeTrue()
        ->and($contract->fresh()->status)->toBe('sent')
        ->and(DB::table('whatsapp_messages')->where('tenant_instance_id', $instance->id)->where('evolution_id', 'ctr-msg-1')->exists())->toBeTrue();
});
