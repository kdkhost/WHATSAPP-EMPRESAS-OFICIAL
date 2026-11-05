@extends('layouts.pwa-public')
@section('content')
<main style="min-height:100dvh;display:flex;align-items:center;justify-content:center;background:#020617;padding:20px;">
    <section style="width:min(420px,100%);background:#0f172a;border:1px solid #1e293b;border-radius:16px;padding:20px;color:#fff;">
        <h1 style="font-size:1.25rem;margin:0 0 14px;">Entrar no aplicativo</h1>
        @if($errors->any())<div style="background:#7f1d1d;border:1px solid #ef4444;border-radius:10px;padding:10px;margin-bottom:12px;">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('login.attempt') }}">
            @csrf
            <input type="hidden" name="pwa_login" value="1">
            <label style="display:block;margin-bottom:6px;">E-mail</label>
            <input type="email" name="email" value="{{ old('email') }}" required style="width:100%;padding:10px;border-radius:10px;border:1px solid #334155;background:#0b1220;color:#fff;margin-bottom:12px;">
            <label style="display:block;margin-bottom:6px;">Senha</label>
            <input type="password" name="password" required style="width:100%;padding:10px;border-radius:10px;border:1px solid #334155;background:#0b1220;color:#fff;margin-bottom:12px;">
            <label style="display:flex;align-items:center;gap:8px;margin-bottom:14px;font-size:.9rem;">
                <input type="checkbox" name="remember" value="1"> Manter conectado
            </label>
            <button type="submit" style="width:100%;padding:11px;border-radius:10px;border:none;background:#2563eb;color:#fff;font-weight:700;">Entrar</button>
        </form>
        <div style="margin-top:12px;font-size:.9rem;">
            <a href="{{ route('password.request') }}" style="color:#93c5fd;">Recuperar senha</a>
        </div>
    </section>
</main>
@endsection

