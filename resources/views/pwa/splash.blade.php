@extends('layouts.pwa-public')
@section('content')
<main style="min-height:100dvh;display:flex;align-items:center;justify-content:center;background:#0f172a;color:#fff;padding:24px;">
    <section style="width:min(420px,100%);text-align:center;">
        <img src="{{ $icon192 }}" alt="Logo" style="width:84px;height:84px;border-radius:20px;object-fit:cover;display:block;margin:0 auto 16px;">
        <h1 style="font-size:1.4rem;font-weight:700;margin:0 0 8px;">{{ $appName }}</h1>
        <p style="margin:0 0 18px;opacity:.8;">Abrindo aplicativo...</p>
        <div style="height:4px;border-radius:999px;background:rgba(255,255,255,.15);overflow:hidden;">
            <div style="height:100%;width:40%;background:#22c55e;animation:pwa-load 1s linear infinite;"></div>
        </div>
    </section>
</main>
<style>
@keyframes pwa-load {
    0% { transform: translateX(-120%); }
    100% { transform: translateX(280%); }
}
</style>
@endsection
@push('scripts')
<script>
setTimeout(function () {
    window.location.href = @json($targetUrl);
}, {{ (int) $delayMs }});
</script>
@endpush

