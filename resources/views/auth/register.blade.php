@extends('auth.layout')
@section('title', 'Cadastro')
@section('wide', true)
@section('content')
<h2 class="h4 mb-3 text-center">Criar uma conta</h2>

@if(isset($activeCoupon) && $activeCoupon)
    <div class="alert alert-success d-flex align-items-center gap-3 p-3 rounded-3 shadow-sm mb-3 border-success border-opacity-25" style="background: rgba(16, 185, 129, 0.1);">
        <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px; font-size: 1.25rem;">
            <i class="bi bi-tag-fill"></i>
        </div>
        <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge bg-success font-monospace fs-6 shadow-sm">{{ $activeCoupon['code'] }}</span>
                <span class="badge bg-success-subtle text-success border border-success-subtle small">Cupom Ativado</span>
            </div>
            <div class="small text-secondary mt-1">
                Indicação de <strong>{{ $activeCoupon['promoter_name'] }}</strong>: você terá <strong>{{ $activeCoupon['label'] }}</strong> aplicado automaticamente!
            </div>
        </div>
    </div>
@endif

@if(isset($selectedPlan) && $selectedPlan)
    <div class="alert alert-info d-flex align-items-center justify-content-between p-2.5 rounded-3 mb-3 small">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-layers-fill fs-5 text-info"></i>
            <span>Plano Selecionado: <strong>{{ $selectedPlan->name }}</strong> (R$ {{ number_format($selectedPlan->price, 2, ',', '.') }}/mês)</span>
        </div>
        <span class="badge bg-info text-white">Pronto para Ativação</span>
    </div>
@endif

@if($errors->any())<div class="alert alert-danger small">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('register.attempt') }}" data-address-block>
    @csrf
    <input type="hidden" name="ref" value="{{ $activeCoupon['slug'] ?? request('ref') }}">
    <input type="hidden" name="cupom" value="{{ $activeCoupon['code'] ?? request('cupom') }}">
    @if(isset($selectedPlan) && $selectedPlan)
        <input type="hidden" name="plan_id" value="{{ $selectedPlan->id }}">
    @endif
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Nome completo</label>
            <input type="text" name="name" class="form-control" required value="{{ old('name') }}">
        </div>
        <div class="col-md-6">
            <label class="form-label">E-mail</label>
            <input type="email" name="email" class="form-control" required value="{{ old('email') }}">
        </div>
        <div class="col-md-6">
            <label class="form-label">CPF/CNPJ</label>
            <input type="text" name="document" class="form-control" data-mask="document" value="{{ old('document') }}">
        </div>
        <div class="col-md-6">
            <label class="form-label">Telefone</label>
            <input type="text" name="phone" class="form-control" data-mask="phone" value="{{ old('phone') }}">
        </div>

        <div class="col-md-3">
            <label class="form-label">CEP</label>
            <input type="text" name="cep" class="form-control" data-mask="cep" data-cep value="{{ old('cep') }}">
        </div>
        <div class="col-md-7">
            <label class="form-label">Logradouro</label>
            <input type="text" name="logradouro" class="form-control" data-cep-fill="logradouro" value="{{ old('logradouro') }}">
        </div>
        <div class="col-md-2">
            <label class="form-label">Numero</label>
            <input type="text" name="numero" class="form-control" data-cep-fill="numero" value="{{ old('numero') }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">Complemento</label>
            <input type="text" name="complemento" class="form-control" data-cep-fill="complemento" value="{{ old('complemento') }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">Bairro</label>
            <input type="text" name="bairro" class="form-control" data-cep-fill="bairro" value="{{ old('bairro') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Cidade</label>
            <input type="text" name="cidade" class="form-control" data-cep-fill="cidade" value="{{ old('cidade') }}">
        </div>
        <div class="col-md-1">
            <label class="form-label">UF</label>
            <input type="text" name="uf" class="form-control" data-cep-fill="uf" maxlength="2" value="{{ old('uf') }}">
        </div>

        <div class="col-md-6">
            <label class="form-label">Senha</label>
            <input type="password" name="password" class="form-control" required minlength="8">
        </div>
        <div class="col-md-6">
            <label class="form-label">Confirme a senha</label>
            <input type="password" name="password_confirmation" class="form-control" required>
        </div>

        <div class="col-12">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="lgpd_accepted" id="lgpd" value="1" required>
                <label class="form-check-label" for="lgpd">
                    Li e concordo com a <a href="/privacidade" target="_blank">Política de Privacidade</a> (LGPD).
                </label>
            </div>
        </div>
    </div>
    <button class="btn btn-primary w-100 mt-3">Criar conta</button>
    <div class="text-center mt-3 small">Ja tem conta? <a href="{{ route('login') }}">Entrar</a></div>
</form>
@endsection
