@extends('auth.layout')
@section('title', 'Criar nova senha')
@section('content')
<h2 class="h4 mb-3 text-center">Criar nova senha</h2>
<p class="text-muted small text-center mb-4">Defina uma nova senha para voltar a acessar sua conta com seguranca.</p>
@if($errors->any())<div class="alert alert-danger small">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('password.update') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="mb-3">
        <label class="form-label">E-mail cadastrado</label>
        <input type="email" name="email" class="form-control" required value="{{ old('email', $email) }}">
    </div>
    <div class="mb-3">
        <label class="form-label">Nova senha</label>
        <input type="password" name="password" class="form-control" required minlength="8">
    </div>
    <div class="mb-3">
        <label class="form-label">Confirmar nova senha</label>
        <input type="password" name="password_confirmation" class="form-control" required minlength="8">
    </div>
    <button class="btn btn-primary w-100">Salvar nova senha</button>
    <div class="text-center mt-3 small"><a href="{{ route('login') }}">Voltar ao login</a></div>
</form>
@endsection
