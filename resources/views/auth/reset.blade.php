@extends('auth.layout')
@section('title', 'Redefinir senha')
@section('content')
<h2 class="h4 mb-3 text-center">Esqueci minha senha</h2>
@if(session('status'))<div class="alert alert-success small">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger small">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('password.send') }}">
    @csrf
    <div class="mb-3">
        <label class="form-label">E-mail cadastrado</label>
        <input type="email" name="email" class="form-control" required value="{{ old('email') }}">
    </div>
    <button class="btn btn-primary w-100">Enviar link de redefinicao</button>
    <div class="text-center mt-3 small"><a href="{{ route('login') }}">Voltar ao login</a></div>
</form>
@endsection
