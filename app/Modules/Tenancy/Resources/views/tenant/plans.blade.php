@extends('tenancy::tenant.layout')
@section('title', 'Planos Disponíveis')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="m-0"><i class="bi bi-collection me-2"></i>Planos Disponíveis</h4>
</div>

@if($currentPlan)
<div class="alert alert-info">
    <i class="bi bi-info-circle me-2"></i>
    <strong>Plano atual:</strong> {{ $currentPlan->name }} - R$ {{ number_format($currentPlan->price, 2, ',', '.') }}/{{ $currentPlan->billing_cycle == 'monthly' ? 'mês' : $currentPlan->billing_cycle }}
</div>
@endif

<div class="row">
    @foreach($plans as $plan)
    <div class="col-md-4 mb-4">
        <div class="card h-100 @if($currentPlan && $currentPlan->id == $plan->id) border-primary @endif">
            <div class="card-header @if($currentPlan && $currentPlan->id == $plan->id) bg-primary text-white @endif">
                <h5 class="m-0">{{ $plan->name }}</h5>
                @if($plan->highlight)
                <span class="badge bg-warning text-dark">Recomendado</span>
                @endif
            </div>
            <div class="card-body">
                <h3 class="text-primary">
                    R$ {{ number_format($plan->price, 2, ',', '.') }}
                    <small class="text-muted fs-6">/{{ $plan->billing_cycle == 'monthly' ? 'mês' : $plan->billing_cycle }}</small>
                </h3>
                
                @if($plan->trial_days > 0)
                <p class="text-success"><i class="bi bi-gift me-1"></i>{{ $plan->trial_days }} dias grátis</p>
                @endif

                <ul class="list-unstyled">
                    <li><i class="bi bi-check-circle text-success me-2"></i>{{ $plan->max_instances }} instância(s)</li>
                    <li><i class="bi bi-check-circle text-success me-2"></i>{{ $plan->max_customers }} clientes</li>
                    <li><i class="bi bi-check-circle text-success me-2"></i>{{ $plan->max_invoices_month }} faturas/mês</li>
                    <li><i class="bi bi-check-circle text-success me-2"></i>{{ $plan->max_messages_month }} mensagens/mês</li>
                    @if($plan->custom_gateway)
                    <li><i class="bi bi-check-circle text-success me-2"></i>Gateway próprio</li>
                    @endif
                    @if($plan->custom_templates)
                    <li><i class="bi bi-check-circle text-success me-2"></i>Templates personalizados</li>
                    @endif
                </ul>

                @if(is_array($plan->features) && count($plan->features))
                <hr>
                <small class="text-muted">Recursos:</small>
                <ul class="list-unstyled">
                    @foreach($plan->features as $feature)
                    <li><i class="bi bi-check2 me-2"></i>{{ $feature }}</li>
                    @endforeach
                </ul>
                @endif
            </div>
            <div class="card-footer">
                @if($currentPlan && $currentPlan->id == $plan->id)
                <button class="btn btn-secondary w-100" disabled>Plano atual</button>
                @else
                <form method="POST" action="{{ route('tenant.plans.subscribe') }}">
                    @csrf
                    <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-cart-plus me-2"></i>Assinar agora
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="alert alert-warning mt-4">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>Funcionalidade em desenvolvimento:</strong> O sistema de pagamento está sendo implementado. Em breve você poderá assinar planos diretamente pelo painel.
</div>
@endsection
