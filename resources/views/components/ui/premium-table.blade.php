@props(['empty' => 'Nenhum registro encontrado.'])

<div {{ $attributes->class('table-responsive premium-table') }}>
    <table class="table table-hover align-middle mb-0">
        {{ $slot }}
    </table>
    @isset($rows)
        @if(trim((string) $rows) === '')
            <div class="p-4 text-center text-muted">{{ $empty }}</div>
        @endif
    @endisset
</div>
