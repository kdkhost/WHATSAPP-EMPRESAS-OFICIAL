<?php

namespace App\Modules\WebhookMonitor\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Monitor de webhooks - mostra status em tempo real de cada gateway.
 * Diagnóstico de integridade, inspeção de payload e auditoria de erros.
 */
class WebhookMonitorController extends Controller
{
    public function index(Request $request)
    {
        $stats = $this->stats();

        $total24h = array_sum(array_column($stats, 'total_24h'));
        $errors24h = array_sum(array_column($stats, 'errors_24h'));
        $successRate = $total24h > 0 ? round((($total24h - $errors24h) / $total24h) * 100, 1) : 100;
        $totalEvents = 0;

        try {
            $totalEvents = DB::table('payment_webhooks')->count();

            $query = DB::table('payment_webhooks')
                ->leftJoin('invoices', 'payment_webhooks.invoice_id', '=', 'invoices.id')
                ->select('payment_webhooks.*', 'invoices.invoice_number')
                ->orderByDesc('payment_webhooks.id');

            if ($gw = $request->query('gateway')) {
                $query->where('payment_webhooks.gateway', $gw);
            }

            if ($st = $request->query('status')) {
                if ($st === 'success') {
                    $query->where('payment_webhooks.processed', true)->whereNull('payment_webhooks.error');
                } elseif ($st === 'error') {
                    $query->whereNotNull('payment_webhooks.error');
                } elseif ($st === 'pending') {
                    $query->where('payment_webhooks.processed', false)->whereNull('payment_webhooks.error');
                }
            }

            if ($q = trim((string) $request->query('q'))) {
                $query->where(function ($b) use ($q) {
                    $b->where('payment_webhooks.ip', 'like', "%{$q}%")
                      ->orWhere('payment_webhooks.event', 'like', "%{$q}%")
                      ->orWhere('payment_webhooks.error', 'like', "%{$q}%")
                      ->orWhere('payment_webhooks.gateway', 'like', "%{$q}%")
                      ->orWhere('invoices.invoice_number', 'like', "%{$q}%");
                });
            }

            $webhooks = $query->limit(250)->get();
        } catch (\Throwable $e) {
            Log::error('Webhook Monitor Index Error: ' . $e->getMessage());
            $webhooks = collect();
        }

        $overview = [
            'total_24h'     => $total24h,
            'errors_24h'    => $errors24h,
            'success_rate'  => $successRate,
            'total_all'     => $totalEvents,
            'healthy_count' => count(array_filter($stats, fn($s) => $s['status'] === 'healthy')),
        ];

        return view('webhook-monitor::index', compact('stats', 'webhooks', 'overview'));
    }

    public function data(Request $request): JsonResponse
    {
        return response()->json($this->stats());
    }

    public function recent(Request $request): JsonResponse
    {
        try {
            $gateway = $request->query('gateway');
            $q = DB::table('payment_webhooks')
                ->leftJoin('invoices', 'payment_webhooks.invoice_id', '=', 'invoices.id')
                ->select('payment_webhooks.*', 'invoices.invoice_number')
                ->orderByDesc('payment_webhooks.id');

            if ($gateway) {
                $q->where('payment_webhooks.gateway', $gateway);
            }

            $items = $q->limit(50)->get();

            return response()->json($items);
        } catch (\Throwable $e) {
            Log::error('Webhook Monitor Recent Error: ' . $e->getMessage());
            return response()->json([]);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $webhook = DB::table('payment_webhooks')
                ->leftJoin('invoices', 'payment_webhooks.invoice_id', '=', 'invoices.id')
                ->select('payment_webhooks.*', 'invoices.invoice_number')
                ->where('payment_webhooks.id', $id)
                ->first();

            if (! $webhook) {
                return response()->json(['success' => false, 'message' => 'Registro de webhook não encontrado.'], 404);
            }

            $decoded = null;
            if (is_string($webhook->payload)) {
                $decoded = json_decode($webhook->payload, true);
            } elseif (is_array($webhook->payload) || is_object($webhook->payload)) {
                $decoded = $webhook->payload;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id'             => $webhook->id,
                    'gateway'        => $webhook->gateway,
                    'event'          => $webhook->event ?: 'webhook_received',
                    'ip'             => $webhook->ip ?: 'Não informado',
                    'invoice_id'     => $webhook->invoice_id,
                    'invoice_number' => $webhook->invoice_number,
                    'processed'      => (bool) $webhook->processed,
                    'error'          => $webhook->error,
                    'payload'        => $decoded,
                    'payload_raw'    => is_string($webhook->payload) ? $webhook->payload : json_encode($webhook->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at'     => $webhook->created_at,
                    'created_at_fmt' => \Carbon\Carbon::parse($webhook->created_at)->format('d/m/Y H:i:s'),
                    'relative_time'  => \Carbon\Carbon::parse($webhook->created_at)->diffForHumans(),
                ]
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Erro ao carregar detalhes: ' . $e->getMessage()], 500);
        }
    }

    protected function stats(): array
    {
        $gateways = [
            'mercadopago' => [
                'name'  => 'Mercado Pago',
                'icon'  => 'bi-wallet2',
                'color' => '#009ee3',
                'badge' => 'primary',
            ],
            'efipro' => [
                'name'  => 'Efí Pro (Gerencianet)',
                'icon'  => 'bi-lightning-charge',
                'color' => '#f37021',
                'badge' => 'warning',
            ],
            'sumup' => [
                'name'  => 'SumUp',
                'icon'  => 'bi-credit-card',
                'color' => '#002b49',
                'badge' => 'info',
            ],
        ];

        $now = now();
        $result = [];

        try {
            foreach ($gateways as $g => $meta) {
                $last = DB::table('payment_webhooks')
                    ->where('gateway', $g)
                    ->orderByDesc('id')
                    ->first();

                $last24h = DB::table('payment_webhooks')
                    ->where('gateway', $g)
                    ->where('created_at', '>=', $now->copy()->subDay())
                    ->count();

                $errors24h = DB::table('payment_webhooks')
                    ->where('gateway', $g)
                    ->where('created_at', '>=', $now->copy()->subDay())
                    ->whereNotNull('error')
                    ->count();

                $successRate = $last24h > 0
                    ? round((($last24h - $errors24h) / $last24h) * 100, 1)
                    : null;

                // Status de saúde baseado no tempo e taxa de erro
                $status = 'no_data';
                if ($last) {
                    $lastDate = \Carbon\Carbon::parse($last->created_at);
                    $minutesAgo = $now->diffInMinutes($lastDate);
                    if ($minutesAgo < 120 && ($successRate === null || $successRate >= 95)) {
                        $status = 'healthy';
                    } elseif ($minutesAgo < 1440 && ($successRate === null || $successRate >= 80)) {
                        $status = 'warning';
                    } else {
                        $status = 'critical';
                    }
                }

                $result[$g] = [
                    'gateway'        => $g,
                    'name'           => $meta['name'],
                    'icon'           => $meta['icon'],
                    'badge'          => $meta['badge'],
                    'status'         => $status,
                    'last_event'     => $last?->created_at,
                    'last_event_ago' => $last ? \Carbon\Carbon::parse($last->created_at)->diffForHumans(['parts' => 2]) : 'Nenhum evento registrado',
                    'total_24h'      => $last24h,
                    'errors_24h'     => $errors24h,
                    'success_rate'   => $successRate,
                ];
            }
        } catch (\Throwable $e) {
            Log::error('Webhook Monitor Stats Error: ' . $e->getMessage());
            foreach ($gateways as $g => $meta) {
                $result[$g] = [
                    'gateway'        => $g,
                    'name'           => $meta['name'],
                    'icon'           => $meta['icon'],
                    'badge'          => $meta['badge'],
                    'status'         => 'critical',
                    'last_event'     => null,
                    'last_event_ago' => 'Erro interno ao consultar',
                    'total_24h'      => 0,
                    'errors_24h'     => 0,
                    'success_rate'   => null,
                ];
            }
        }

        return $result;
    }
}
