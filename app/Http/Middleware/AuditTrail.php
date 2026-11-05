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

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auditoria automatica de todas as acoes admin que modificam dados.
 *
 * Registra:
 *  - Quem (admin_user_id)
 *  - O que (acao: create, update, delete, config_change, etc.)
 *  - Onde (rota, subject_type, subject_id)
 *  - Quando (timestamp)
 *  - De onde (IP, User-Agent)
 *  - Payload (para POST/PUT/DELETE — sem senhas)
 *
 * Aplicado automaticamente em todas as rotas admin que NAO sao GET.
 */
class AuditTrail
{
    /** Campos que NUNCA são logados (segurança). */
    protected array $redactedFields = [
        'password', 'password_confirmation', 'current_password',
        'secret', 'token', 'api_key', 'client_secret',
        'access_token', 'refresh_token', 'authorization',
        'cookie', 'x-csrf-token', 'x_csrf_token',
        'card_number', 'cvv', 'expiry',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Apenas rotas admin que modificam dados (POST, PUT, DELETE)
        if (! $request->is('admin*') || $request->isMethod('GET')) {
            return $response;
        }

        // Nao logar se a resposta eh erro de validacao (nao houve acao real)
        if ($response->getStatusCode() === 422) {
            return $response;
        }

        $this->record($request, $response);

        return $response;
    }

    protected function record(Request $request, Response $response): void
    {
        try {
            $admin = Auth::guard('admin')->user();
            $action = $this->detectAction($request);
            $subject = $this->detectSubject($request);

            DB::table('audit_logs')->insert([
                'admin_user_id' => $admin?->id,
                'action'        => $action,
                'subject_type'  => $subject['type'],
                'subject_id'    => $subject['id'],
                'changes'       => json_encode($this->getPayload($request), JSON_UNESCAPED_UNICODE),
                'ip'            => $request->ip(),
                'user_agent'    => substr((string) $request->userAgent(), 0, 500),
                'created_at'    => now(),
            ]);
        } catch (\Throwable) {
            // Silencioso - auditoria nao deve quebrar o sistema
        }
    }

    protected function detectAction(Request $request): string
    {
        $method = $request->method();
        $path = $request->path();

        if ($method === 'DELETE') return 'delete';
        if ($method === 'PUT' || $method === 'PATCH') return 'update';
        if ($method === 'POST') {
            if (str_contains($path, 'login')) return 'login';
            if (str_contains($path, 'logout')) return 'logout';
            if (str_contains($path, 'send')) return 'send';
            if (str_contains($path, 'install')) return 'install';
            if (str_contains($path, 'config') || str_contains($path, 'settings')) return 'config_change';
            return 'create';
        }
        return $method;
    }

    protected function detectSubject(Request $request): array
    {
        $segments = explode('/', trim($request->path(), '/'));
        $type = null;
        $id = null;

        // Detecta tipo e ID do subject pela URL (admin/billing/invoices/123)
        foreach ($segments as $i => $seg) {
            if (is_numeric($seg) && $i > 0) {
                $type = $segments[$i - 1] ?? null;
                $id = (int) $seg;
                break;
            }
        }

        if (! $type) {
            // Pega o ultimo segmento nao-numerico significativo
            $type = collect($segments)->filter(fn ($s) => ! is_numeric($s) && strlen($s) > 2)->last();
        }

        return ['type' => $type, 'id' => $id];
    }

    protected function getPayload(Request $request): array
    {
        $data = $request->except($this->redactedFields);

        // Redact campos sensiveis aninhados
        array_walk_recursive($data, function (&$value, $key) {
            if (in_array(strtolower($key), $this->redactedFields, true)) {
                $value = '[REDACTED]';
            }
            // Truncar valores muito longos
            if (is_string($value) && strlen($value) > 500) {
                $value = substr($value, 0, 500) . '...[truncated]';
            }
        });

        // Limitar tamanho total
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if (strlen($json) > 5000) {
            return ['_truncated' => true, '_size' => strlen($json)];
        }

        return $data;
    }
}
