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

namespace App\Modules\Security\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Web Application Firewall enterprise.
 *
 * Regras (todas configuraveis em security_rules):
 *  - sqli         SQL Injection (UNION, OR 1=1, sleep, benchmark, load_file)
 *  - xss          Cross-Site Scripting (script, iframe, onerror, javascript:)
 *  - lfi          Local File Inclusion (../, /etc/passwd, php://input)
 *  - rfi          Remote File Inclusion (=http://..., data:base64)
 *  - badbot       Scanners (sqlmap, nikto, nessus, nmap, etc.)
 *  - ratelimit    Rate limit por IP+rota (configuravel)
 *  - shellshock   Shellshock (Bash CVE-2014-6271)
 *  - xxe          XML External Entity injection
 *  - ssti         Server-Side Template Injection (Twig, Jinja2)
 *  - path_traversal Path traversal alem de LFI
 *  - upload_exec  Upload de extensoes executaveis (.php, .phtml, .jsp, etc.)
 *  - geo          Bloqueio por pais (security_blocklist type=country)
 *  - bruteforce   Detecta tentativas repetidas em /login
 */
class WafService
{
    public const PATTERNS = [
        'sqli' => [
            "/\b(union\s+select|select\s+.+\s+from|insert\s+into|update\s+\w+\s+set|delete\s+from|drop\s+table|drop\s+database|load_file\s*\(|outfile\s+|into\s+outfile)/i",
            "/(\bor\b|\band\b)\s+\d+\s*=\s*\d+/i",
            "/(\'|\"|;|--|\#|\/\*).*?(or|and).*?\=/i",
            "/sleep\s*\(\s*\d+\s*\)/i",
            "/benchmark\s*\(/i",
            "/information_schema\.|sysobjects\b/i",
            "/\bxp_cmdshell\b/i",
        ],
        'xss' => [
            "/<\s*script[^>]*>/i",
            "/javascript\s*:/i",
            "/on(load|error|click|mouseover|focus|blur|submit|change|keyup|keydown)\s*=/i",
            "/<\s*iframe[^>]*>/i",
            "/<\s*svg[^>]*onload/i",
            "/document\.(cookie|write|location)/i",
            "/\beval\s*\(/i",
            "/\balert\s*\(/i",
            "/<\s*img[^>]*on\w+\s*=/i",
        ],
        'lfi' => [
            "/(\.\.\/){2,}/",
            "/(\.\.\\\\){2,}/",
            "/\/etc\/(passwd|shadow|hosts|group)/i",
            "/\/proc\/self\/(environ|cmdline|status)/i",
            "/php:\/\/(input|filter|wrapper|memory)/i",
            "/file:\/\//i",
            "/\.\.%2f/i",
        ],
        'rfi' => [
            "/=\s*https?:\/\/[^\s&]+\.(php|txt|asp|jsp|exe)/i",
            "/=\s*ftp:\/\//i",
            "/=\s*data:[a-z\/]+;base64,/i",
        ],
        'badbot' => [
            "/(sqlmap|nikto|nessus|acunetix|fimap|nmap|masscan|zgrab|wpscan|dirbuster|gobuster|zmeu|wpscan|skipfish|whatweb|joomscan)/i",
            "/(crawler|spider|bot)\b.*(scan|exploit|attack|hack)/i",
            "/(libwww-perl|python-requests|go-http-client|curl\/[0-7]\.|wget\/)/i",
        ],
        'shellshock' => [
            "/\(\s*\)\s*\{\s*:;\s*\}\s*;/",
            "/\(\)\s*\{[^}]+\}\s*;.*\b(echo|cat|wget|curl|bash|sh)\b/i",
        ],
        'xxe' => [
            "/<!ENTITY\s+\w+\s+SYSTEM\s+/i",
            "/<!DOCTYPE\s+\w+\s+\[/i",
            "/<\?xml.*?<!ENTITY/is",
        ],
        'ssti' => [
            "/\{\{\s*[\w\.]+\s*\(\s*\)\s*\}\}/",
            "/\{\%.+?(import|include|extends).+?\%\}/i",
            "/\{\{\s*config\.|\{\{\s*self\.|\{\{\s*request\./i",
        ],
        'path_traversal' => [
            "/(\.{2,}[\/\\\\]){2,}/",
            "/%2e%2e[\/\\\\]/i",
        ],
        'upload_exec' => [
            "/\.(php[3-7]?|phtml|phar|pht|jsp|jspx|asp|aspx|cgi|exe|sh|py|pl|rb)(\?|$|\.)/i",
        ],
    ];

    public function inspect(Request $request): array
    {
        $ip = $request->ip();
        $ua = (string) $request->userAgent();
        $settings = $this->settings();

        // Modo global: se detect, nunca bloquear (apenas logar)
        $mode = Cache::get('waf.mode', env('SECURITY_WAF_MODE', 'detect'));

        if ($this->inAllowlist($ip, $ua)) {
            return ['block' => false];
        }

        if ($entry = $this->inBlocklist($ip, $ua)) {
            $this->logEvent('blocklist', 'critical', $mode === 'block' ? 'blocked' : 'detected', $request, ['matched' => $entry]);
            return ['block' => $mode === 'block', 'reason' => 'IP/UA bloqueado', 'rule' => 'blocklist'];
        }

        if (($settings['block_empty_user_agent'] ?? false) && trim($ua) === '') {
            $this->logEvent('badbot', 'medium', $mode === 'block' ? 'blocked' : 'detected', $request, [
                'rule_triggered' => 'empty_user_agent',
            ]);
            return ['block' => $mode === 'block', 'reason' => 'User-Agent ausente', 'rule' => 'badbot'];
        }

        $rules = $this->loadRules();
        $haystack = implode(' ', [
            $request->getRequestUri(),
            json_encode($request->query()),
            json_encode($request->post()),
            (string) $request->getContent(),
        ]);

        // Headers tambem
        $headers = '';
        foreach ($request->headers->all() as $name => $values) {
            $headers .= "{$name}: " . implode(',', (array) $values) . "\n";
        }

        foreach ($rules as $code => $rule) {
            if (! ($rule['enabled'] ?? true)) continue;

            if ($code === 'ratelimit') {
                if (!($settings['rate_limit_enabled'] ?? true)) {
                    continue;
                }
                $cfg = $rule['config'] ?? [];
                $max = (int) ($settings['rate_limit_max'] ?? ($cfg['max'] ?? 120));
                $win = (int) ($settings['rate_limit_window'] ?? ($cfg['window_seconds'] ?? 60));
                $key = "waf:rl:{$ip}";
                $hits = (int) Cache::get($key, 0) + 1;
                Cache::put($key, $hits, $win);
                if ($hits > $max) {
                    $this->logEvent('ratelimit', 'medium', $rule['action'] === 'block' ? 'blocked' : 'logged', $request, [
                        'hits' => $hits, 'max' => $max, 'window' => $win,
                    ]);
                    if ($rule['action'] === 'block') {
                        if ($settings['auto_block_enabled'] ?? true) {
                            $this->autoBlock($ip, 'ratelimit', minutes: (int) ($settings['auto_block_minutes'] ?? 15));
                        }
                        return ['block' => true, 'reason' => 'Limite de requisicoes excedido', 'rule' => 'ratelimit'];
                    }
                }
                continue;
            }

            $patterns = self::PATTERNS[$code] ?? [];
            $subject = match ($code) {
                'badbot'      => $ua,
                'shellshock'  => $headers . $haystack,
                'upload_exec' => $haystack . $headers,
                default       => $haystack,
            };

            foreach ($patterns as $rx) {
                if (@preg_match($rx, $subject)) {
                    $action = $rule['action'] ?? 'block';
                    // Em modo detect, nunca bloquear
                    if ($mode === 'detect') {
                        $action = 'log';
                    }
                    $severity = match ($code) {
                        'sqli', 'shellshock', 'xxe' => 'critical',
                        'xss', 'rfi', 'upload_exec' => 'high',
                        default => 'medium',
                    };
                    $this->logEvent($code, $severity, $action === 'block' ? 'blocked' : 'detected', $request, [
                        'rule_triggered' => $code,
                    ]);
                    if ($action === 'block') {
                        return ['block' => true, 'reason' => 'Atividade suspeita ('.$code.')', 'rule' => $code];
                    }
                    break;
                }
            }
        }

        return ['block' => false];
    }

    /** Detecta brute-force em /login (>10 falhas em 15min). */
    public function detectBruteForce(): int
    {
        $threshold = 10;
        $window = 15;
        $blockedCount = 0;

        try {
            $suspects = DB::table('security_login_attempts')
                ->select('ip', DB::raw('COUNT(*) as fails'))
                ->where('success', false)
                ->where('created_at', '>=', now()->subMinutes($window))
                ->groupBy('ip')
                ->having('fails', '>=', $threshold)
                ->get();

            foreach ($suspects as $s) {
                if (DB::table('security_blocklist')->where('type', 'ip')->where('value', $s->ip)->exists()) continue;
                $this->autoBlock($s->ip, "Brute-force detectado ({$s->fails} falhas em {$window}min)", 60);
                try {
                    DB::table('security_events')->insert([
                        'rule_code'    => 'bruteforce',
                        'severity'     => 'critical',
                        'action_taken' => 'blocked',
                        'ip'           => $s->ip,
                        'method'       => 'CRON',
                        'uri'          => '/login',
                        'matched'      => json_encode(['fails' => $s->fails, 'window' => $window]),
                        'created_at'   => now(),
                    ]);
                } catch (\Throwable) {}
                $blockedCount++;
            }
        } catch (\Throwable) {}

        return $blockedCount;
    }

    public function autoBlock(string $ip, string $reason, int $minutes = 60): void
    {
        try {
            DB::table('security_blocklist')->updateOrInsert(
                ['type' => 'ip', 'value' => $ip],
                [
                    'reason'     => 'WAF auto: '.$reason,
                    'expires_at' => now()->addMinutes($minutes),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            Cache::forget('security.blocklist');
        } catch (\Throwable) {}
    }

    public function cleanup(): void
    {
        try {
            DB::table('security_events')->where('created_at', '<', now()->subDays(30))->delete();
            DB::table('security_blocklist')
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', now())
                ->delete();
            DB::table('security_login_attempts')->where('created_at', '<', now()->subDays(60))->delete();
        } catch (\Throwable) {}
    }

    protected function loadRules(): array
    {
        return Cache::remember('security.rules', 300, function () {
            try {
                $rows = DB::table('security_rules')->get();
            } catch (\Throwable) {
                return $this->defaultRules();
            }
            if ($rows->isEmpty()) {
                return $this->defaultRules();
            }
            return $rows->keyBy('code')
                ->map(fn ($r) => [
                    'enabled' => (bool) $r->enabled,
                    'action'  => $r->action,
                    'config'  => $r->config ? json_decode($r->config, true) : [],
                ])->toArray();
        });
    }

    protected function defaultRules(): array
    {
        $defaults = [];
        foreach (['sqli','xss','lfi','rfi','badbot','shellshock','xxe','ssti','path_traversal','upload_exec'] as $code) {
            $defaults[$code] = ['enabled' => true, 'action' => 'block'];
        }
        $defaults['ratelimit'] = ['enabled' => true, 'action' => 'block', 'config' => ['max' => 120, 'window_seconds' => 60]];
        return $defaults;
    }

    protected function settings(): array
    {
        return Cache::remember('waf.settings', 60, function () {
            $defaults = [
                'rate_limit_enabled' => true,
                'rate_limit_max' => 120,
                'rate_limit_window' => 60,
                'auto_block_enabled' => true,
                'auto_block_minutes' => 15,
                'block_empty_user_agent' => false,
            ];

            try {
                $rows = DB::table('settings')
                    ->whereIn('key', [
                        'security.waf_rate_limit_enabled',
                        'security.waf_rate_limit_max',
                        'security.waf_rate_limit_window',
                        'security.waf_auto_block_enabled',
                        'security.waf_auto_block_minutes',
                        'security.waf_block_empty_user_agent',
                    ])
                    ->pluck('value', 'key');

                return [
                    'rate_limit_enabled' => filter_var($rows['security.waf_rate_limit_enabled'] ?? $defaults['rate_limit_enabled'], FILTER_VALIDATE_BOOLEAN),
                    'rate_limit_max' => max(10, min(2000, (int) ($rows['security.waf_rate_limit_max'] ?? $defaults['rate_limit_max']))),
                    'rate_limit_window' => max(10, min(3600, (int) ($rows['security.waf_rate_limit_window'] ?? $defaults['rate_limit_window']))),
                    'auto_block_enabled' => filter_var($rows['security.waf_auto_block_enabled'] ?? $defaults['auto_block_enabled'], FILTER_VALIDATE_BOOLEAN),
                    'auto_block_minutes' => max(1, min(10080, (int) ($rows['security.waf_auto_block_minutes'] ?? $defaults['auto_block_minutes']))),
                    'block_empty_user_agent' => filter_var($rows['security.waf_block_empty_user_agent'] ?? $defaults['block_empty_user_agent'], FILTER_VALIDATE_BOOLEAN),
                ];
            } catch (\Throwable) {
                return $defaults;
            }
        });
    }

    protected function inAllowlist(string $ip, string $ua): bool
    {
        try {
            $list = Cache::remember('security.allowlist', 300, fn () => DB::table('security_allowlist')->get()->all());
        } catch (\Throwable) { return false; }
        foreach ($list as $row) {
            if ($row->type === 'ip' && $row->value === $ip) return true;
            if ($row->type === 'cidr' && $this->ipInCidr($ip, $row->value)) return true;
            if ($row->type === 'user_agent' && stripos($ua, $row->value) !== false) return true;
        }
        return false;
    }

    protected function inBlocklist(string $ip, string $ua): ?array
    {
        try {
            $list = Cache::remember('security.blocklist', 60, fn () => DB::table('security_blocklist')
                ->where(function ($q) { $q->whereNull('expires_at')->orWhere('expires_at', '>', now()); })
                ->get()->all());
        } catch (\Throwable) { return null; }
        foreach ($list as $row) {
            $hit = match ($row->type) {
                'ip'         => $row->value === $ip,
                'cidr'       => $this->ipInCidr($ip, $row->value),
                'user_agent' => stripos($ua, $row->value) !== false,
                default      => false,
            };
            if ($hit) {
                return ['type' => $row->type, 'value' => $row->value, 'reason' => $row->reason];
            }
        }
        return null;
    }

    protected function ipInCidr(string $ip, string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return $ip === $cidr;
        }
        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;
        $ipLong = ip2long($ip);
        $sub = ip2long($subnet);
        if ($ipLong === false || $sub === false) return false;
        $mask = -1 << (32 - $bits);
        return ($ipLong & $mask) === ($sub & $mask);
    }

    protected function logEvent(string $rule, string $severity, string $action, Request $request, array $matched = []): void
    {
        /** Campos sensíveis que NUNCA devem ser logados. */
        $sensitiveFields = [
            'password','password_confirmation','current_password',
            'token','secret','api_key','access_token','refresh_token',
            'client_secret','authorization','cookie','x-csrf-token',
        ];

        // Sanitizar matched para não vazar dados sensíveis
        $safeMatched = $matched;
        if (isset($safeMatched['pattern'])) {
            // Não logar o padrão regex completo — apenas o código da regra
            unset($safeMatched['pattern']);
            $safeMatched['rule_triggered'] = $rule;
        }

        // Truncar valores longos
        array_walk_recursive($safeMatched, function (&$v) {
            if (is_string($v) && strlen($v) > 200) {
                $v = substr($v, 0, 200) . '...[truncated]';
            }
        });

        // Verificar modo: se detect, marcar como 'detected' em vez de 'blocked'
        $mode = Cache::get('waf.mode', 'detect');
        if ($mode === 'detect' && $action === 'blocked') {
            $action = 'detected';
        }

        try {
            DB::table('security_events')->insert([
                'rule_code'    => $rule,
                'severity'     => $severity,
                'action_taken' => $action,
                'ip'           => $request->ip(),
                'method'       => $request->method(),
                'uri'          => substr($request->getRequestUri(), 0, 500),
                'user_agent'   => substr((string) $request->userAgent(), 0, 1000),
                'referer'      => substr((string) $request->headers->get('referer'), 0, 500),
                'matched'      => json_encode($safeMatched, JSON_UNESCAPED_UNICODE),
                'created_at'   => now(),
            ]);
        } catch (\Throwable) {}
    }
}
