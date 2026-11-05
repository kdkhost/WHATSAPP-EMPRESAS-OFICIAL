<?php

/**
 * @autor marcelo-brad rj
 * @contato Tel: 21 981325441 | contato@kdkhost.com.br | @MARCELO_BRAD
 * @copyright Copyright (c) KDK HOST / Marcelo Brad. Todos os direitos reservados.
 * @propriedade-intelectual Software proprietário — proibida cópia, distribuição ou uso não autorizado.
 */

namespace App\Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SecurityRulesController extends Controller
{
    /**
     * Metadata padrao de cada regra (label amigavel, descricao, severidade, icone, categoria).
     */
    private array $meta = [
        'sqli' => [
            'name' => 'SQL Injection',
            'description' => 'Detecta tentativas de injecao SQL em parametros (UNION, SELECT, DROP, OR 1=1, etc).',
            'severity' => 'critical', 'icon' => 'bi-database-fill-exclamation', 'category' => 'Injecao'
        ],
        'xss' => [
            'name' => 'Cross-Site Scripting (XSS)',
            'description' => 'Detecta scripts maliciosos: tags <script>, eventos onload/onerror, javascript: URIs.',
            'severity' => 'high', 'icon' => 'bi-bug-fill', 'category' => 'Injecao'
        ],
        'lfi' => [
            'name' => 'Local File Inclusion',
            'description' => 'Detecta tentativas de leitura de arquivos locais via path traversal (../etc/passwd, etc).',
            'severity' => 'high', 'icon' => 'bi-folder-x', 'category' => 'Inclusao'
        ],
        'rfi' => [
            'name' => 'Remote File Inclusion',
            'description' => 'Detecta tentativas de inclusao remota (=http://attacker.com/shell.php).',
            'severity' => 'high', 'icon' => 'bi-cloud-arrow-down-fill', 'category' => 'Inclusao'
        ],
        'badbot' => [
            'name' => 'Bad Bots',
            'description' => 'Bloqueia ferramentas de scan/exploit (sqlmap, nikto, nessus, acunetix, nmap).',
            'severity' => 'medium', 'icon' => 'bi-robot', 'category' => 'Bots'
        ],
        'shellshock' => [
            'name' => 'Shellshock',
            'description' => 'Detecta exploits da vulnerabilidade Bash (CVE-2014-6271).',
            'severity' => 'critical', 'icon' => 'bi-terminal-fill', 'category' => 'CVE'
        ],
        'xxe' => [
            'name' => 'XML External Entity (XXE)',
            'description' => 'Detecta payloads XXE em XMLs (DOCTYPE, ENTITY, SYSTEM).',
            'severity' => 'critical', 'icon' => 'bi-file-earmark-code-fill', 'category' => 'Injecao'
        ],
        'ssti' => [
            'name' => 'Server-Side Template Injection',
            'description' => 'Detecta tentativas de SSTI em engines de template ({{ config }}, {{ request }}).',
            'severity' => 'high', 'icon' => 'bi-braces-asterisk', 'category' => 'Injecao'
        ],
        'path_traversal' => [
            'name' => 'Path Traversal',
            'description' => 'Detecta caminhos relativos suspeitos com codificacao (.. / %2e%2e/).',
            'severity' => 'high', 'icon' => 'bi-signpost-2', 'category' => 'Inclusao'
        ],
        'upload_exec' => [
            'name' => 'Upload Executavel',
            'description' => 'Bloqueia upload de arquivos executaveis (.php, .phtml, .jsp, .asp, .exe, .sh).',
            'severity' => 'high', 'icon' => 'bi-file-earmark-arrow-up-fill', 'category' => 'Upload'
        ],
        'ratelimit' => [
            'name' => 'Rate Limit',
            'description' => 'Limita requisicoes por IP. Auto-bloqueia IPs que excedem o limite.',
            'severity' => 'medium', 'icon' => 'bi-speedometer2', 'category' => 'Limite'
        ],
        'geo' => [
            'name' => 'Geo-blocking',
            'description' => 'Bloqueia paises configurados na blocklist (type=country).',
            'severity' => 'low', 'icon' => 'bi-globe', 'category' => 'Geografia'
        ],
        'bruteforce' => [
            'name' => 'Brute-force',
            'description' => 'Detecta tentativas repetidas de login e auto-bloqueia o IP por 60min.',
            'severity' => 'critical', 'icon' => 'bi-shield-lock-fill', 'category' => 'Autenticacao'
        ],
    ];

    public function index(Request $request)
    {
        $rules = DB::table('security_rules')->orderBy('code')->get();

        // Filtros
        if ($search = $request->query('q')) {
            $rules = $rules->filter(function ($r) use ($search) {
                $meta = $this->meta[$r->code] ?? [];
                $hay = strtolower($r->code . ' ' . $r->name . ' ' . ($meta['name'] ?? '') . ' ' . ($meta['description'] ?? '') . ' ' . ($meta['category'] ?? ''));
                return str_contains($hay, strtolower($search));
            })->values();
        }
        if ($status = $request->query('status')) {
            $rules = $rules->filter(fn ($r) => $status === 'enabled' ? $r->enabled : ! $r->enabled)->values();
        }
        if ($action = $request->query('action')) {
            $rules = $rules->filter(fn ($r) => $r->action === $action)->values();
        }

        // KPIs
        $allRules = DB::table('security_rules')->get();
        $stats = [
            'total'    => $allRules->count(),
            'enabled'  => $allRules->where('enabled', true)->count(),
            'disabled' => $allRules->where('enabled', false)->count(),
            'blocking' => $allRules->where('action', 'block')->where('enabled', true)->count(),
        ];

        // Eventos por regra (ultimos 7 dias)
        $eventsByRule = DB::table('security_events')
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->selectRaw('rule_code, COUNT(*) as total')
            ->groupBy('rule_code')
            ->pluck('total', 'rule_code');

        // Anexa metadata e contagem
        $rules = $rules->map(function ($r) use ($eventsByRule) {
            $r->_meta = $this->meta[$r->code] ?? ['name' => $r->name, 'description' => $r->description ?? '—', 'severity' => 'medium', 'icon' => 'bi-shield', 'category' => 'Outros'];
            $r->_events_7d = (int) ($eventsByRule[$r->code] ?? 0);
            return $r;
        });

        // Categorias para filtro
        $categories = $rules->groupBy('_meta.category')->map->count();

        return view('security::rules', compact('rules', 'stats', 'categories'));
    }

    public function update(Request $request, string $rule)
    {
        $data = $request->validate([
            'enabled' => 'required|boolean',
            'action'  => 'required|in:log,block,challenge',
            'config'  => 'nullable|array',
        ]);

        $existing = DB::table('security_rules')->where('code', $rule)->first();

        DB::table('security_rules')->updateOrInsert(
            ['code' => $rule],
            [
                'name'        => $existing->name ?? ($this->meta[$rule]['name'] ?? $rule),
                'description' => $existing->description ?? ($this->meta[$rule]['description'] ?? null),
                'enabled'     => $data['enabled'],
                'action'      => $data['action'],
                'config'      => isset($data['config']) ? json_encode($data['config']) : ($existing->config ?? null),
                'updated_at'  => now(),
                'created_at'  => $existing->created_at ?? now(),
            ]
        );

        // Limpar cache das regras
        Cache::forget('security.rules');

        // Registrar auditoria
        try {
            $admin = auth()->guard('admin')->user();
            DB::table('audit_logs')->insert([
                'admin_user_id' => $admin?->id,
                'action'        => 'waf_rule_update',
                'subject_type'  => 'security_rule',
                'subject_id'    => null,
                'changes'       => json_encode(['rule' => $rule, 'data' => $data], JSON_UNESCAPED_UNICODE),
                'ip'            => $request->ip(),
                'user_agent'    => substr((string) $request->userAgent(), 0, 500),
                'created_at'    => now(),
            ]);
        } catch (\Throwable) {}

        return response()->json([
            'ok'      => true,
            'message' => 'Regra atualizada com sucesso.',
        ]);
    }
}
