@extends('tenancy::tenant.layout')
@section('title', 'Contratos Digitais — Upgrade')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-7">

            {{-- Hero --}}
            <div class="text-center py-5">
                <div class="mb-4">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary" style="width:88px;height:88px;font-size:2.5rem;">
                        <i class="bi bi-file-earmark-lock2"></i>
                    </span>
                </div>
                <h2 class="fw-bold mb-2">Contratos Digitais</h2>
                <p class="text-muted fs-5 mb-0">
                    Envie contratos para assinatura eletrônica, acompanhe o status em tempo real<br class="d-none d-md-block">
                    e mantenha tudo registrado com validade jurídica.
                </p>
            </div>

            {{-- Benefícios --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <strong><i class="bi bi-stars me-2 text-warning"></i>O que você terá acesso</strong>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        @php $features = [
                            ['bi-send',             'primary',  'Envio por WhatsApp e E-mail',   'Envie o link de assinatura direto pelo canal que seu cliente prefere.'],
                            ['bi-pen-fill',         'success',  'Assinatura digital segura',      'Coleta de assinatura com timestamp, IP e confirmação por SMS/e-mail.'],
                            ['bi-collection',       'info',     'Templates personalizados',       'Crie templates com suas cláusulas padrão e aplique em segundos.'],
                            ['bi-cloud-download',   'warning',  'Download em PDF',                'Contrato assinado disponível para download imediato.'],
                            ['bi-clock-history',    'danger',   'Histórico completo',             'Rastreie cada acesso, visualização e assinatura com logs detalhados.'],
                            ['bi-shield-check',     'secondary','Validade jurídica',              'Processo em conformidade com a legislação brasileira (Lei 14.063/2020).'],
                        ]; @endphp

                        @foreach($features as [$icon, $color, $title, $desc])
                        <div class="col-md-6">
                            <div class="d-flex gap-3 align-items-start">
                                <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-{{ $color }}-subtle text-{{ $color }} flex-shrink-0" style="width:42px;height:42px;font-size:1.1rem;">
                                    <i class="bi {{ $icon }}"></i>
                                </span>
                                <div>
                                    <strong class="d-block mb-1">{{ $title }}</strong>
                                    <small class="text-muted">{{ $desc }}</small>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- CTA --}}
            <div class="card border-primary shadow-sm mb-4">
                <div class="card-body text-center py-4">
                    <h5 class="fw-bold mb-1">Quer ativar o módulo de Contratos?</h5>
                    <p class="text-muted mb-3 small">
                        Entre em contato com o suporte ou faça upgrade do seu plano para começar a usar.
                    </p>
                    <div class="d-flex flex-wrap gap-2 justify-content-center">
                        @php
                            $wpLink = config('app.whatsapp_support', 'https://wa.me/55' . ltrim(config('app.support_phone', ''), '+'));
                        @endphp
                        <a href="{{ $wpLink }}" target="_blank" rel="noopener" class="btn btn-success btn-sm">
                            <i class="bi bi-whatsapp me-1"></i> Falar com suporte
                        </a>
                        <a href="{{ Route::has('tenant.dashboard') ? route('tenant.dashboard') : url('/painel') }}" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left me-1"></i> Voltar ao painel
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
