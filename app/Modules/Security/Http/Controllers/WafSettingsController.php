<?php

/**
 * @autor marcelo-brad rj
 * @contato Tel: 21 981325441
 * @contato Email: contato@kdkhost.com.br
 * @contato Telegram: @MARCELO_BRAD
 * @contato Instagram: @marcelobradrj
 * @contato WhatsApp: 21981325441
 *
 * @copyright Copyright (c) KDK HOST / Marcelo Brad. Todos os direitos reservados.
 * @propriedade-intelectual Este arquivo integra software proprietário. É proibida a cópia,
 * distribuição, sublicenciamento, engenharia reversa, revenda, cessão, publicação ou uso
 * fora do projeto autorizado sem permissão expressa e por escrito do titular.
 */

namespace App\Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Services\SettingsManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Gerencia as configurações globais do WAF (modo off/detect/block).
 * Acesso restrito a superadmin.
 */
class WafSettingsController extends Controller
{
    /** Chaves de configuração gerenciadas. */
    private array $keys = [
        'security.waf_enabled',
        'security.waf_mode',
        'security.waf_admin_enabled',
        'security.waf_log_payload',
        'security.waf_log_headers',
        'security.waf_rate_limit_enabled',
        'security.waf_rate_limit_max',
        'security.waf_rate_limit_window',
        'security.waf_auto_block_enabled',
        'security.waf_auto_block_minutes',
        'security.waf_block_empty_user_agent',
    ];

    public function index(): JsonResponse
    {
        $settings = [];
        foreach ($this->keys as $key) {
            $settings[$key] = $this->getSetting($key);
        }

        return response()->json([
            'ok'       => true,
            'settings' => $settings,
            'mode'     => $settings['security.waf_mode'] ?? 'detect',
            'defaults' => $this->defaults(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        \Log::info('WAF update called', ['payload' => $request->all()]);

        $validated = $request->validate([
            'security.waf_enabled'       => 'sometimes|boolean',
            'security.waf_mode'          => 'sometimes|in:off,detect,block',
            'security.waf_admin_enabled' => 'sometimes|boolean',
            'security.waf_log_payload'   => 'sometimes|boolean',
            'security.waf_log_headers'   => 'sometimes|boolean',
            'security.waf_rate_limit_enabled' => 'sometimes|boolean',
            'security.waf_rate_limit_max' => 'sometimes|integer|min:10|max:2000',
            'security.waf_rate_limit_window' => 'sometimes|integer|min:10|max:3600',
            'security.waf_auto_block_enabled' => 'sometimes|boolean',
            'security.waf_auto_block_minutes' => 'sometimes|integer|min:1|max:10080',
            'security.waf_block_empty_user_agent' => 'sometimes|boolean',
        ]);

        \Log::info('WAF validated', ['validated' => $validated]);

        foreach ($validated as $key => $value) {
            if (is_array($value) && $key === 'security') {
                // Laravel agrupou security.* em array aninhado
                foreach ($value as $subKey => $subValue) {
                    $this->setSetting("security.{$subKey}", $subValue);
                }
                continue;
            }
            if (!str_contains($key, '.')) {
                continue;
            }
            $this->setSetting($key, $value);
        }

        // Limpar caches do WAF
        Cache::forget('waf.mode');
        Cache::forget('waf.admin_enabled');
        Cache::forget('waf.settings');
        Cache::forget('security.rules');

        // Registrar auditoria
        try {
            $admin = auth()->guard('admin')->user();
            DB::table('audit_logs')->insert([
                'admin_user_id' => $admin?->id,
                'action'        => 'waf_config_change',
                'subject_type'  => 'waf_settings',
                'subject_id'    => null,
                'changes'       => json_encode($validated, JSON_UNESCAPED_UNICODE),
                'ip'            => $request->ip(),
                'user_agent'    => substr((string) $request->userAgent(), 0, 500),
                'created_at'    => now(),
            ]);
        } catch (\Throwable) {}

        return response()->json([
            'ok'      => true,
            'message' => 'Configurações do WAF atualizadas com sucesso.',
        ]);
    }

    public function clearCache(): JsonResponse
    {
        Cache::forget('waf.mode');
        Cache::forget('waf.admin_enabled');
        Cache::forget('waf.settings');
        Cache::forget('security.rules');
        Cache::forget('security.blocklist');
        Cache::forget('security.allowlist');

        return response()->json([
            'ok'      => true,
            'message' => 'Cache do WAF limpo com sucesso.',
        ]);
    }

    private function getSetting(string $key): mixed
    {
        try {
            return app(SettingsManager::class)->get($key, $this->defaults()[$key] ?? null);
        } catch (\Throwable) {
            return $this->defaults()[$key] ?? null;
        }
    }

    private function setSetting(string $key, mixed $value): void
    {
        try {
            $type = is_bool($value) ? 'bool' : 'string';
            app(SettingsManager::class)->set($key, $value, $type);
        } catch (\Throwable $e) {
            \Log::error('WAF setSetting error', ['key' => $key, 'value' => $value, 'error' => $e->getMessage()]);
        }
    }

    private function defaults(): array
    {
        return [
            'security.waf_enabled' => '1',
            'security.waf_mode' => 'detect',
            'security.waf_admin_enabled' => '0',
            'security.waf_log_payload' => '0',
            'security.waf_log_headers' => '0',
            'security.waf_rate_limit_enabled' => '1',
            'security.waf_rate_limit_max' => '120',
            'security.waf_rate_limit_window' => '60',
            'security.waf_auto_block_enabled' => '1',
            'security.waf_auto_block_minutes' => '15',
            'security.waf_block_empty_user_agent' => '0',
        ];
    }
}
