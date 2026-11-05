<?php

namespace App\Services;

use App\Models\SystemVisit;
use App\Modules\Settings\Services\SettingsManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SessionManagerService
{
    public function __construct(
        protected SettingsManager $settings
    ) {}

    /**
     * Termina e invalida uma sessão específica pelo seu ID.
     */
    public function terminateSession(string $sessionId, ?string $reason = null): bool
    {
        if (empty($sessionId)) {
            return false;
        }

        try {
            // 1. Marca no cache de revogação imediata (TTL 7 dias)
            Cache::put('session_revoked_' . $sessionId, [
                'revoked_at' => now()->toIso8601String(),
                'reason'     => $reason ?? 'Sessão desconectada pelo administrador.',
            ], now()->addDays(7));

            // 2. Apaga arquivo de sessão do driver 'file' se existir
            $sessionFile = storage_path('framework/sessions/' . $sessionId);
            if (file_exists($sessionFile)) {
                @unlink($sessionFile);
            }

            // 3. Remove da tabela sessions se o driver database estiver ativo
            try {
                if (DB::getSchemaBuilder()->hasTable('sessions')) {
                    DB::table('sessions')->where('id', $sessionId)->delete();
                }
            } catch (\Throwable) {}

            // 4. Atualiza os registros de rastreamento para marcar que não está mais autenticado
            SystemVisit::where('session_id', $sessionId)->update([
                'is_authenticated' => false,
            ]);

            Log::info("[SESSION_MANAGER] Sessão {$sessionId} revogada com sucesso. Motivo: " . ($reason ?? 'manual'));
            return true;
        } catch (\Throwable $e) {
            Log::error("[SESSION_MANAGER] Falha ao encerrar sessão {$sessionId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Termina todas as outras sessões ativas de um usuário/admin, mantendo apenas a sessão informada.
     */
    public function terminateOtherSessions(int|string $userId, string $guard = 'admin', ?string $currentSessionId = null): int
    {
        $query = SystemVisit::query()
            ->where('is_authenticated', true)
            ->where('guard', $guard);

        if ($guard === 'admin') {
            $query->where('admin_id', $userId);
        } else {
            $query->where('user_id', $userId);
        }

        if ($currentSessionId) {
            $query->where('session_id', '!=', $currentSessionId);
        }

        $sessionIds = $query->distinct()->pluck('session_id')->filter()->values()->all();

        $count = 0;
        foreach ($sessionIds as $sid) {
            if ($this->terminateSession($sid, 'Desconectada por política de sessão única ou logout remoto.')) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Termina todas as outras sessões ativas do sistema, exceto a sessão atual informada.
     */
    public function terminateAllOtherSessionsExcept(string $keepSessionId): int
    {
        $since = now()->subHours(24);
        $sessionIds = SystemVisit::where('last_activity_at', '>=', $since)
            ->where('is_authenticated', true)
            ->where('session_id', '!=', $keepSessionId)
            ->distinct()
            ->pluck('session_id')
            ->filter()
            ->values()
            ->all();

        $count = 0;
        foreach ($sessionIds as $sid) {
            if ($this->terminateSession($sid, 'Desconexão em massa de sessões secundárias pelo administrador.')) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Verifica se uma sessão foi revogada.
     */
    public function isSessionRevoked(string $sessionId): bool
    {
        if (empty($sessionId)) {
            return false;
        }
        return Cache::has('session_revoked_' . $sessionId);
    }

    /**
     * Retorna se múltiplas instâncias/sessões simultâneas são permitidas.
     * Padrão: true (permite múltiplas). Se false, apenas 1 login por conta é permitido.
     */
    public function isConcurrentSessionsAllowed(): bool
    {
        return (bool) $this->settings->get('security.allow_concurrent_sessions', true);
    }

    /**
     * Define se múltiplas conexões simultâneas são permitidas.
     */
    public function setConcurrentSessionsAllowed(bool $allowed): void
    {
        $this->settings->set('security.allow_concurrent_sessions', $allowed, 'bool');
    }
}

