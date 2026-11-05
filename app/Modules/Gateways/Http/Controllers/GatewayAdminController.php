<?php

namespace App\Modules\Gateways\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Services\SettingsManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class GatewayAdminController extends Controller
{
    protected function persistGatewayConfig(SettingsManager $settings, string $group, array $data, array $types = []): void
    {
        foreach ($data as $key => $value) {
            $type = $types[$key]
                ?? (is_bool($value) ? 'bool' : (is_int($value) ? 'int' : (is_float($value) ? 'float' : 'string')));

            $settings->set("gateways.{$group}.{$key}", $value ?? '', $type);
        }
    }

    // ─── Mercado Pago ───────────────────────────────────────────────

    public function mercadopago(SettingsManager $s)
    {
        $config = $s->group('gateways.mercadopago');
        return view('gateways-mercadopago::config', compact('config'));
    }

    public function saveMercadopago(Request $request, SettingsManager $s): JsonResponse
    {
        $data = $request->validate([
            'access_token'          => 'nullable|string',
            'public_key'            => 'nullable|string',
            'client_id'             => 'nullable|string',
            'client_secret'         => 'nullable|string',
            'webhook_secret'        => 'nullable|string|max:255',
            'sandbox'               => 'required|boolean',
            'enabled'               => 'required|boolean',
            'pix_enabled'           => 'required|boolean',
            'credit_card_enabled'   => 'required|boolean',
            'boleto_enabled'        => 'required|boolean',
            'expiration_days'       => 'nullable|integer|min:1|max:30',
            'installments_max'      => 'nullable|integer|min:1|max:24',
            'soft_descriptor'       => 'nullable|string|max:22',
            'default_description'   => 'nullable|string|max:100',
            // Multas e juros por atraso
            'late_fee_pct'          => 'nullable|numeric|min:0|max:20',
            'late_interest_pct_day' => 'nullable|numeric|min:0|max:5',
            'late_fee_after_days'   => 'nullable|integer|min:0|max:60',
        ]);

        $this->persistGatewayConfig($s, 'mercadopago', $data, [
            'sandbox'               => 'bool',
            'enabled'               => 'bool',
            'pix_enabled'           => 'bool',
            'credit_card_enabled'   => 'bool',
            'boleto_enabled'        => 'bool',
            'expiration_days'       => 'int',
            'installments_max'      => 'int',
            'late_fee_pct'          => 'float',
            'late_interest_pct_day' => 'float',
            'late_fee_after_days'   => 'int',
        ]);

        return response()->json(['ok' => true]);
    }

    public function deleteMercadopago(SettingsManager $s): JsonResponse
    {
        $s->forgetGroup('gateways.mercadopago');

        return response()->json(['ok' => true]);
    }

    public function testMercadopago(SettingsManager $s): JsonResponse
    {
        $token = $s->get('gateways.mercadopago.access_token', config('gateways.mercadopago.access_token', ''));
        if (!$token) {
            return response()->json(['ok' => false, 'message' => 'Access Token nao configurado.'], 422);
        }

        try {
            $resp = Http::withToken($token)->get('https://api.mercadopago.com/users/me');
            if ($resp->successful()) {
                return response()->json(['ok' => true, 'email' => $resp->json()['email'] ?? '']);
            }
            return response()->json(['ok' => false, 'message' => 'Token invalido ou expirado.'], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function recentMercadopago(): JsonResponse
    {
        $payments = DB::table('invoices')
            ->where('gateway', 'mercadopago')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['id', 'number', 'amount', 'status']);

        return response()->json(['payments' => $payments]);
    }

    // ─── Efi Pro ────────────────────────────────────────────────────

    public function efipro(SettingsManager $s)
    {
        $config = $s->group('gateways.efipro');
        return view('gateways-efipro::config', compact('config'));
    }

    public function saveEfipro(Request $request, SettingsManager $s): JsonResponse
    {
        $data = $request->validate([
            'client_id'        => 'nullable|string',
            'client_secret'    => 'nullable|string',
            'certificate_path' => 'nullable|string',
            'pix_key'          => 'nullable|string',
            'pix_key_type'     => 'nullable|in:email,cpf,cnpj,phone,random',
            'sandbox'          => 'required|boolean',
            'enabled'          => 'required|boolean',
            'pix_expiration'   => 'nullable|integer|min:300|max:86400',
            'boleto_enabled'   => 'required|boolean',
            'card_enabled'     => 'required|boolean',
            'installments_max' => 'nullable|integer|min:1|max:24',
            'webhook_secret'   => 'nullable|string|max:255',
            'account_id'       => 'nullable|string|max:120',
        ]);

        $this->persistGatewayConfig($s, 'efipro', $data, [
            'sandbox' => 'bool',
            'enabled' => 'bool',
            'boleto_enabled' => 'bool',
            'card_enabled' => 'bool',
            'pix_expiration' => 'int',
            'installments_max' => 'int',
        ]);

        return response()->json(['ok' => true]);
    }

    public function deleteEfipro(SettingsManager $s): JsonResponse
    {
        $s->forgetGroup('gateways.efipro');

        return response()->json(['ok' => true]);
    }

    public function testEfipro(SettingsManager $s): JsonResponse
    {
        $clientId = $s->get('gateways.efipro.client_id', '');
        $clientSecret = $s->get('gateways.efipro.client_secret', '');

        if (!$clientId || !$clientSecret) {
            return response()->json(['ok' => false, 'message' => 'Client ID e Secret sao obrigatorios.'], 422);
        }

        try {
            $sandbox = $s->get('gateways.efipro.sandbox', false);
            $baseUrl = $sandbox ? 'https://pix-h.api.efipay.com.br' : 'https://pix.api.efipay.com.br';

            $client = Http::withBasicAuth($clientId, $clientSecret)->asForm();
            $certPath = $s->get('gateways.efipro.certificate_path', '');
            if ($certPath && file_exists($certPath)) {
                $client = $client->withOptions(['cert' => $certPath]);
            }

            $resp = $client->post("{$baseUrl}/oauth/token", ['grant_type' => 'client_credentials']);

            if ($resp->successful()) {
                return response()->json(['ok' => true]);
            }
            $err = $resp->json();
            $msg = $err['error_description'] ?? $err['message'] ?? 'Credenciais ou certificado invalidos.';
            return response()->json(['ok' => false, 'message' => $msg], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function uploadCertificateEfipro(Request $request, SettingsManager $s): JsonResponse
    {
        $request->validate([
            'certificate' => 'required|file|max:4096',
            'passphrase'  => 'nullable|string',
        ]);

        $file = $request->file('certificate');
        $ext = strtolower($file->getClientOriginalExtension());
        $origName = $file->getClientOriginalName();

        if (! in_array($ext, ['pem', 'p12', 'crt', 'cer'])) {
            return response()->json(['ok' => false, 'message' => 'Formato invalido. O certificado deve ser .pem, .p12 ou .crt.'], 422);
        }

        $dir = storage_path('app/certificates');
        \Illuminate\Support\Facades\File::ensureDirectoryExists($dir, 0755, true);
        $destPath = $dir . '/efipro_admin.pem';

        $content = file_get_contents($file->getRealPath());

        if ($ext === 'p12') {
            $passphrase = (string) $request->input('passphrase', '');
            $certs = [];
            if (openssl_pkcs12_read($content, $certs, $passphrase)) {
                $pemContent = ($certs['cert'] ?? '') . "\n" . ($certs['pkey'] ?? '');
                if (!empty($certs['extracerts'])) {
                    foreach ((array)$certs['extracerts'] as $extra) {
                        $pemContent .= "\n" . $extra;
                    }
                }
                file_put_contents($destPath, $pemContent);
            } else {
                return response()->json([
                    'ok' => false,
                    'message' => 'Falha ao ler o certificado .p12. Se o arquivo possui senha, informe-a para conversao.'
                ], 422);
            }
        } else {
            file_put_contents($destPath, $content);
        }

        @chmod($destPath, 0600);

        $s->set('gateways.efipro.certificate_path', $destPath, 'string');
        $s->set('gateways.efipro.certificate_original_name', $origName, 'string');
        $s->set('gateways.efipro.certificate_uploaded_at', now()->toISOString(), 'string');

        return response()->json([
            'ok'            => true,
            'message'       => "Certificado {$origName} carregado com sucesso!",
            'path'          => $destPath,
            'original_name' => $origName,
            'uploaded_at'   => now()->format('d/m/Y H:i'),
        ]);
    }

    public function deleteCertificateEfipro(SettingsManager $s): JsonResponse
    {
        $path = $s->get('gateways.efipro.certificate_path', '');
        if ($path && file_exists($path)) {
            @unlink($path);
        }
        $s->forget('gateways.efipro.certificate_path');
        $s->forget('gateways.efipro.certificate_original_name');
        $s->forget('gateways.efipro.certificate_uploaded_at');

        return response()->json(['ok' => true, 'message' => 'Certificado removido com sucesso.']);
    }

    public function recentEfipro(): JsonResponse
    {
        $payments = DB::table('invoices')
            ->where('gateway', 'efipro')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['id', 'number', 'amount', 'status']);

        $stats = DB::table('invoices')
            ->where('gateway', 'efipro')
            ->selectRaw("
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN status='paid' THEN amount ELSE 0 END), 0) AS revenue,
                SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) AS paid_count,
                SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending_count
            ")->first();

        $avg = ($stats && $stats->paid_count > 0) ? ($stats->revenue / $stats->paid_count) : 0;

        return response()->json([
            'payments' => $payments,
            'stats' => [
                'total'         => (int) ($stats->total ?? 0),
                'revenue'       => (float) ($stats->revenue ?? 0),
                'paid_count'    => (int) ($stats->paid_count ?? 0),
                'pending_count' => (int) ($stats->pending_count ?? 0),
                'avg_ticket'    => (float) $avg,
            ],
        ]);
    }

    // ─── SumUp ──────────────────────────────────────────────────────

    public function sumup(SettingsManager $s)
    {
        $config = $s->group('gateways.sumup');
        return view('gateways-sumup::config', compact('config'));
    }

    public function saveSumup(Request $request, SettingsManager $s): JsonResponse
    {
        $data = $request->validate([
            'client_id'           => 'nullable|string',
            'client_secret'       => 'nullable|string',
            'api_key'             => 'nullable|string',
            'merchant_code'       => 'nullable|string',
            'redirect_uri'        => 'nullable|string',
            'sandbox'             => 'required|boolean',
            'enabled'             => 'required|boolean',
            'currency'            => 'nullable|in:BRL,EUR,USD',
            'default_description' => 'nullable|string|max:100',
            'link_expiration'     => 'nullable|integer|min:5|max:1440',
            'hosted_checkout_enabled' => 'required|boolean',
            'credit_card_enabled' => 'required|boolean',
            'webhook_secret'      => 'nullable|string|max:255',
        ]);

        $this->persistGatewayConfig($s, 'sumup', $data, [
            'sandbox' => 'bool',
            'enabled' => 'bool',
            'link_expiration' => 'int',
            'hosted_checkout_enabled' => 'bool',
            'credit_card_enabled' => 'bool',
        ]);

        return response()->json(['ok' => true]);
    }

    public function deleteSumup(SettingsManager $s): JsonResponse
    {
        $s->forgetGroup('gateways.sumup');

        return response()->json(['ok' => true]);
    }

    public function testSumup(SettingsManager $s): JsonResponse
    {
        $apiKey = $s->get('gateways.sumup.api_key', '');
        if (!$apiKey) {
            return response()->json(['ok' => false, 'message' => 'API Key nao configurada.'], 422);
        }

        try {
            $resp = Http::withToken($apiKey)->get('https://api.sumup.com/v0.1/me');
            if ($resp->successful()) {
                return response()->json(['ok' => true, 'merchant' => $resp->json()['merchant_profile']['merchant_code'] ?? '']);
            }
            return response()->json(['ok' => false, 'message' => 'API Key invalida.'], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function recentSumup(): JsonResponse
    {
        $payments = DB::table('invoices')
            ->where('gateway', 'sumup')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['id', 'number', 'amount', 'status']);

        $stats = DB::table('invoices')
            ->where('gateway', 'sumup')
            ->selectRaw("
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN status='paid' THEN amount ELSE 0 END), 0) AS revenue,
                SUM(CASE WHEN status='paid' THEN 1 ELSE 0 END) AS paid_count,
                SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending_count
            ")->first();

        $avg = ($stats && $stats->paid_count > 0) ? ($stats->revenue / $stats->paid_count) : 0;

        return response()->json([
            'payments' => $payments,
            'stats' => [
                'total'         => (int) ($stats->total ?? 0),
                'revenue'       => (float) ($stats->revenue ?? 0),
                'paid_count'    => (int) ($stats->paid_count ?? 0),
                'pending_count' => (int) ($stats->pending_count ?? 0),
                'avg_ticket'    => (float) $avg,
            ],
        ]);
    }
}
