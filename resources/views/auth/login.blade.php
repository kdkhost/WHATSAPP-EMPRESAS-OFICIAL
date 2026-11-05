@extends('auth.layout')
@section('title', 'Entrar')
@section('content')
<h2 class="h4 mb-3 text-center">Acessar minha conta</h2>
@if(session('status'))<div class="alert alert-success small">{{ session('status') }}</div>@endif
@if(session('error'))<div class="alert alert-warning small">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger small">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('login.attempt') }}">
    @csrf
    <div class="mb-3">
        <label class="form-label">E-mail</label>
        <input type="email" name="email" class="form-control" required value="{{ old('email') }}">
    </div>
    <div class="mb-3">
        <label class="form-label">Senha</label>
        <input type="password" name="password" class="form-control" required>
    </div>
    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="remember" id="remember">
        <label class="form-check-label" for="remember">Manter-me conectado</label>
    </div>
    <button class="btn btn-primary w-100">Entrar</button>
    <div class="text-center mt-3 small">
        <a href="{{ route('password.request') }}">Esqueci minha senha</a> -
        <a href="{{ route('register') }}">Criar conta</a>
    </div>
</form>
@endsection
