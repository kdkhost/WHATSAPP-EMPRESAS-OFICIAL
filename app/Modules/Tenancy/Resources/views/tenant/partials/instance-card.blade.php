@php
    use App\Support\Format;

    $status = $inst->status ?? 'creating';
    $profileName = $inst->whatsapp_profile_name;
    $displayName = $inst->display_name ?: $profileName;
    $initial = mb_strtoupper(mb_substr($displayName, 0, 1));
    $number = $inst->profile_number ?: $inst->phone;
    $maskedNumber = $number ? Format::phone(preg_replace('/^55/', '', $number)) : '-';
    $quality = data_get($inst->meta, 'quality_rating', 'GREEN');
@endphp

<article class="instance-card" data-instance-card="{{ $inst->id }}">
    <div class="instance-card__top">
        <div class="instance-avatar" data-avatar-wrap>
            @if($inst->profile_avatar_url)
                <img src="{{ $inst->profile_avatar_url }}" alt="{{ $displayName }}" referrerpolicy="no-referrer" onerror="this.style.display='none'; this.nextElementSibling?.classList.remove('d-none');" data-profile-avatar>
                <span data-profile-initial class="d-none">{{ $initial }}</span>
            @else
                <span data-profile-initial>{{ $initial }}</span>
            @endif
        </div>
        <div class="min-w-0 flex-grow-1">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h3 class="instance-card__title mb-0" data-profile-name>{{ $displayName }}</h3>
                @if($inst->is_default)
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Padrao</span>
                @endif
                @if($inst->is_extra_billable)
                    <span class="badge bg-info-subtle text-info border border-info-subtle">Extra {{ Format::money($inst->recurring_amount ?? 0) }}/mes</span>
                @endif
                <span class="badge bg-success-subtle text-success border border-success-subtle">
                    <i class="bi bi-patch-check-fill me-1"></i> Meta Cloud API v21.0
                </span>
            </div>
            <div class="instance-card__code"><code>{{ $inst->instance_name }}</code></div>
            <p class="instance-card__slogan" data-profile-status>{{ $inst->profile_status ?: 'WhatsApp Cloud API Oficial da Meta' }}</p>
        </div>
        <span class="badge text-bg-{{ $statusColors[$status] ?? 'secondary' }}" data-status-badge>
            {{ $statusLabels[$status] ?? ucfirst((string) $status) }}
        </span>
    </div>

    <div class="instance-card__meta">
        <div>
            <span>Numero Oficial</span>
            <strong data-profile-number>{{ $maskedNumber }}</strong>
        </div>
        <div>
            <span>Phone Number ID</span>
            <strong class="text-truncate" data-profile-phone-id>{{ $inst->meta_phone_number_id ?: 'Padrao Global' }}</strong>
        </div>
        <div>
            <span>WABA ID</span>
            <strong class="text-truncate" data-profile-waba-id>{{ $inst->meta_waba_id ?: 'Padrao Global' }}</strong>
        </div>
        <div>
            <span>Qualidade Meta</span>
            <strong class="text-success" data-profile-quality><i class="bi bi-shield-check me-1"></i>{{ $quality }}</strong>
        </div>
        <div>
            <span>Token Permanente</span>
            <strong>{{ filled($inst->meta_access_token) ? 'Individual' : 'Padrao Global' }}</strong>
        </div>
        <div>
            <span>Ultima sincronizacao</span>
            <strong data-profile-sync>{{ $inst->last_profile_sync_at?->format('d/m/Y H:i') ?? '-' }}</strong>
        </div>
    </div>

    @if($inst->last_error)
        <div class="alert alert-warning py-2 px-3 small mb-3" data-last-error>
            <i class="bi bi-exclamation-triangle me-1"></i>{{ $inst->last_error }}
        </div>
    @else
        <div class="d-none" data-last-error></div>
    @endif

    <div class="instance-card__actions">
        <button class="btn btn-outline-primary btn-sm btn-sync" data-id="{{ $inst->id }}">
            <i class="bi bi-arrow-repeat me-1"></i> Testar Conexao
        </button>
        <button class="btn btn-outline-success btn-sm btn-config" data-id="{{ $inst->id }}">
            <i class="bi bi-key-fill me-1"></i> Credenciais Meta
        </button>
        @unless($inst->is_default)
            <button class="btn btn-outline-dark btn-sm btn-rename" data-id="{{ $inst->id }}" data-current="{{ $inst->display_name }}">
                <i class="bi bi-pencil me-1"></i> Renomear
            </button>
        @endunless
        <button class="btn btn-outline-danger btn-sm btn-delete-instance" data-id="{{ $inst->id }}" data-name="{{ $displayName }}">
            <i class="bi bi-trash me-1"></i> Excluir
        </button>
    </div>
</article>
