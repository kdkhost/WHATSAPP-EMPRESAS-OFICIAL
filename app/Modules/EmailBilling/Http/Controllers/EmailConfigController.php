<?php

namespace App\Modules\EmailBilling\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\EmailBilling\Models\TenantEmailConfig;
use App\Modules\EmailBilling\Services\EmailBillingTenantResolver;
use App\Modules\EmailBilling\Services\SmtpMailer;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\ManagedAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Configuracao de SMTP/Email do tenant.
 * O tenant configura seu proprio servidor de email para enviar cobrancas.
 */
class EmailConfigController extends Controller
{
    public function __construct(protected EmailBillingTenantResolver $tenantResolver) {}

    public function show(Request $request)
    {
        $tenant = $this->tenant($request);
        $config = $tenant->emailConfig ?? new TenantEmailConfig([
            'tenant_id' => $tenant->id,
            'driver'    => 'smtp',
            'enabled'   => false,
        ]);
        if (empty($config->driver)) {
            $config->driver = 'smtp';
        }
        $routeNames = $this->routeNames($request);

        return view('email-billing::config', compact('tenant', 'config', 'routeNames'));
    }

    public function save(Request $request): JsonResponse
    {
        $tenant = $this->tenant($request);

        $driver = $request->input('driver');
        if (empty($driver)) {
            $driver = $tenant->emailConfig?->driver ?: 'smtp';
        }

        $request->merge([
            'driver'  => $driver,
            'enabled' => $request->boolean('enabled'),
        ]);

        $data = $request->validate([
            'enabled'    => 'required|boolean',
            'driver'     => 'nullable|string|in:smtp,sendgrid,mailgun,ses',
            'host'       => 'required_if:driver,smtp|nullable|string',
            'port'       => 'required_if:driver,smtp|nullable|integer|min:1|max:65535',
            'encryption' => 'required_if:driver,smtp|nullable|in:tls,ssl,none',
            'username'   => 'nullable|string',
            'password'   => 'nullable|string',
            'api_key'    => 'nullable|string',
            'from_email' => 'required|email',
            'from_name'  => 'required|string|max:120',
            'reply_to'   => 'nullable|email',
            'settings'   => 'nullable|array',
            'settings.brand_name' => 'nullable|string|max:120',
            'settings.logo_url' => 'nullable|string|max:500',
            'settings.primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'settings.accent_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'settings.footer_text' => 'nullable|string|max:500',
            'settings.footer_signature' => 'nullable|string|max:120',
            'settings.footer_subtext' => 'nullable|string|max:255',
        ]);

        $data['driver'] = $data['driver'] ?? $driver ?? 'smtp';

        if ((bool) ($data['enabled'] ?? false) === true) {
            if (($data['driver'] ?? 'smtp') === 'smtp') {
                if (empty($data['host']) || empty($data['port']) || empty($data['username'])) {
                    return response()->json([
                        'ok' => false,
                        'error' => 'Para habilitar SMTP, informe host, porta e usuario.',
                    ], 422);
                }

                $hasPassword = !empty($data['password']) || !empty($tenant->emailConfig?->password);
                if (! $hasPassword) {
                    return response()->json([
                        'ok' => false,
                        'error' => 'Para habilitar SMTP, informe a senha do servidor de email.',
                    ], 422);
                }
            } else {
                $hasApiKey = !empty($data['api_key']) || !empty($tenant->emailConfig?->api_key);
                if (! $hasApiKey) {
                    return response()->json([
                        'ok' => false,
                        'error' => 'Para habilitar este driver, informe a API key do provedor.',
                    ], 422);
                }
            }
        }

        // Nao sobrescrever password/api_key se vierem vazios
        $existing = $tenant->emailConfig;
        if ($existing) {
            if (empty($data['password'])) unset($data['password']);
            if (empty($data['api_key']))  unset($data['api_key']);
        }

        ManagedAsset::replaceIfChanged(
            data_get($existing?->settings, 'logo_url'),
            data_get($data, 'settings.logo_url')
        );

        TenantEmailConfig::updateOrCreate(
            ['tenant_id' => $tenant->id],
            $data
        );

        return response()->json(['ok' => true]);
    }

    public function test(Request $request, SmtpMailer $mailer): JsonResponse
    {
        $tenant = $this->tenant($request);
        $request->validate(['to' => 'required|email']);

        $config = $tenant->emailConfig;
        if (!$config) return response()->json(['ok' => false, 'error' => 'Configure primeiro.'], 422);

        $result = $mailer->test($config, $request->input('to'));
        return response()->json($result);
    }

    protected function tenant(Request $request): Tenant
    {
        return $this->tenantResolver->resolve($request);
    }

    protected function routeNames(Request $request): array
    {
        $prefix = $request->routeIs('admin.email.*') ? 'admin.email.' : 'tenant.email.';

        return [
            'configSave' => $prefix.'config.save',
            'configTest' => $prefix.'config.test',
        ];
    }
}
