<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class SystemAuditController extends Controller
{
    public function index()
    {
        return view('admin.system.audit', $this->buildPayload());
    }

    public function run(): JsonResponse
    {
        Artisan::call('system:audit-full', ['--write-docs' => true]);

        return response()->json([
            'ok' => true,
            'message' => 'Auditoria executada com sucesso.',
            ...$this->buildPayload(),
        ]);
    }

    protected function buildPayload(): array
    {
        $reportPath = storage_path('reports/SYSTEM_AUDIT_REPORT.md');
        $report = File::exists($reportPath) ? File::get($reportPath) : '';
        $metrics = $this->extractMetrics($report);

        $lastLog = null;
        $logPath = storage_path('logs/system_audit.log');
        if (File::exists($logPath)) {
            $lines = preg_split('/\R/', trim(File::get($logPath)));
            $lastLine = $lines ? end($lines) : null;
            if ($lastLine) {
                $lastLog = json_decode($lastLine, true);
            }
        }

        $score = 100;
        $score -= min(40, (int) ($metrics['rotas_criticas'] ?? 0) * 5);
        $score -= min(25, (int) ($metrics['rotas_sem_middleware'] ?? 0) * 2);
        $score -= min(20, (int) ($metrics['metodos_vazios'] ?? 0) * 2);
        $score -= min(15, (int) ($metrics['debug_leftovers'] ?? 0) * 3);
        $score = max(0, $score);

        return [
            'score' => $score,
            'metrics' => $metrics,
            'last_log' => $lastLog,
            'updated_at' => now()->format('d/m/Y H:i:s'),
            'report_excerpt' => $this->excerpt($report),
        ];
    }

    protected function extractMetrics(string $report): array
    {
        $map = [
            'rotas_mapeadas' => '/- Rotas mapeadas:\s*(\d+)/',
            'rotas_criticas' => '/- Achados criticos de rota:\s*(\d+)/',
            'metodos_vazios' => '/- Metodos vazios:\s*(\d+)/',
            'debug_leftovers' => '/- Restos de debug:\s*(\d+)/',
            'catches_fracos' => '/- Catches fracos \(Exception \+ return false\):\s*(\d+)/',
            'riscos_tenant' => '/- Possiveis riscos de escopo tenant:\s*(\d+)/',
            'rotas_duplicadas' => '/- Rotas duplicadas:\s*(\d+)/',
            'rotas_sem_middleware' => '/- Rotas sem middleware:\s*(\d+)/',
        ];

        $metrics = [];
        foreach ($map as $key => $regex) {
            $metrics[$key] = preg_match($regex, $report, $m) ? (int) ($m[1] ?? 0) : 0;
        }

        return $metrics;
    }

    protected function excerpt(string $report): string
    {
        $lines = array_filter(array_map('trim', preg_split('/\R/', $report)));
        return implode("\n", array_slice($lines, 0, 18));
    }
}
