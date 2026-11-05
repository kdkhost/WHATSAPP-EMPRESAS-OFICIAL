@extends('admin.layouts.app')

@section('title', 'WhatsApp - Relatório anti-ban')

@section('content')
<div class="container-fluid whatsapp-export-report-page">
    <x-ui.page-header
        title="Relatório anti-ban WhatsApp"
        subtitle="Leitura operacional das instâncias, limites e qualidade dos envios. O JSON fica disponível somente na exportação."
        eyebrow="Segurança WhatsApp">
        <x-slot:actions>
            <a href="{{ route('admin.whatsapp.safety') }}" class="btn btn-outline-secondary">
                <i class="bi bi-shield-check me-1"></i> Painel anti-ban
            </a>
            <a href="{{ route('admin.whatsapp.export-report.download') }}" class="btn btn-primary" target="_blank" rel="noopener">
                <i class="bi bi-download me-1"></i> Exportar JSON
            </a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Instâncias" :value="$report['summary']['total']" icon="bi-whatsapp" color="primary">
                <x-slot:meta>Total monitorado no período {{ $report['period'] }}.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Pausadas" :value="$report['summary']['paused']" icon="bi-pause-circle" color="warning">
                <x-slot:meta>Envios interrompidos manualmente.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Quarentena" :value="$report['summary']['quarantined']" icon="bi-shield-x" color="danger">
                <x-slot:meta>Instâncias bloqueadas por risco.</x-slot:meta>
            </x-ui.stats-card>
        </div>
        <div class="col-12 col-md-6 col-xl-3">
            <x-ui.stats-card label="Warmup" :value="$report['summary']['warmup']" icon="bi-thermometer-half" color="success">
                <x-slot:meta>Números em aquecimento controlado.</x-slot:meta>
            </x-ui.stats-card>
        </div>
    </div>

    <x-ui.premium-card
        title="Detalhamento por instância"
        subtitle="Relatório gerado em {{ \Carbon\Carbon::parse($report['generated_at'])->format('d/m/Y H:i') }}.">
        @if(empty($report['instances']))
            <x-ui.empty-state
                icon="bi-whatsapp"
                title="Nenhuma instância encontrada"
                message="Ainda não há instâncias WhatsApp disponíveis para relatório." />
        @else
            <div class="p-0">
                <table class="table table-bordered table-striped dt-responsive nowrap w-100 app-datatable" id="table-whatsapp-export-report" data-export-title="Relatório de Instâncias WhatsApp" style="width: 100% !important;">
                    <thead class="table-light">
                        <tr>
                            <th class="all" data-priority="1">Instância</th>
                            <th class="all">Tenant</th>
                            <th class="min-tablet" data-priority="2">Status</th>
                            <th class="desktop">Confiança</th>
                            <th class="min-tablet">Risco</th>
                            <th class="desktop">Qualidade</th>
                            <th class="min-tablet text-end">Enviados</th>
                            <th class="min-tablet text-end">Falhas</th>
                            <th class="desktop">Controles</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($report['instances'] as $instance)
                            @php
                                $statusTone = match ($instance['status']) {
                                    'connected' => 'success',
                                    'connecting' => 'warning',
                                    'error' => 'danger',
                                    default => 'secondary',
                                };
                                $riskTone = $instance['risk_score'] >= 80
                                    ? 'danger'
                                    : ($instance['risk_score'] >= 50 ? 'warning' : 'success');
                            @endphp
                            <tr>
                                <td>
                                    <span class="fw-semibold">{{ $instance['name'] }}</span>
                                    @if(! empty($instance['quarantine_reason']))
                                        <span class="d-block small text-muted">{{ $instance['quarantine_reason'] }}</span>
                                    @endif
                                </td>
                                <td>{{ $instance['tenant'] ?? '-' }}</td>
                                <td><x-ui.status-badge :status="$statusTone" :label="$instance['status']" /></td>
                                <td>{{ $instance['trust_score'] }}</td>
                                <td><x-ui.status-badge :status="$riskTone" :label="$instance['risk_score']" /></td>
                                <td>{{ $instance['quality_score'] }}</td>
                                <td class="text-end fw-semibold">{{ number_format($instance['daily_sent'], 0, ',', '.') }}</td>
                                <td class="text-end text-danger">{{ number_format($instance['daily_failed'], 0, ',', '.') }}</td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        @if($instance['sending_paused'])
                                            <x-ui.status-badge status="warning" label="Pausada" />
                                        @endif
                                        @if($instance['quarantined'])
                                            <x-ui.status-badge status="danger" label="Quarentena" />
                                        @endif
                                        @if($instance['warmup_enabled'])
                                            <x-ui.status-badge status="info" label="Warmup" />
                                        @endif
                                        @unless($instance['sending_paused'] || $instance['quarantined'] || $instance['warmup_enabled'])
                                            <span class="text-muted small">Sem bloqueios</span>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.premium-card>
</div>
@endsection
