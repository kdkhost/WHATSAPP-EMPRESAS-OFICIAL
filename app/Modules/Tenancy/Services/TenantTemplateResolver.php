<?php

namespace App\Modules\Tenancy\Services;

use App\Modules\EmailBilling\Models\EmailTemplate;
use App\Modules\Templates\Models\WhatsAppTemplate;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantTemplatePreference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TenantTemplateResolver
{
    public static function preferenceKey(?string $category, ?string $scheduleOffset): string
    {
        $category = $category ?: 'manual';
        $scheduleOffset = ($scheduleOffset !== null && $scheduleOffset !== '') ? $scheduleOffset : 'manual';

        return $category.'|'.$scheduleOffset;
    }

    public static function categoryForOffset(?string $scheduleOffset): string
    {
        if ($scheduleOffset === '0d' || $scheduleOffset === 'manual' || $scheduleOffset === 'send_now') {
            return 'cobranca';
        }

        if ($scheduleOffset && str_starts_with($scheduleOffset, '+')) {
            return 'atraso';
        }

        if ($scheduleOffset && str_starts_with($scheduleOffset, '-')) {
            return 'lembrete';
        }

        return 'cobranca';
    }

    /**
     * Determina a categoria financeira real com base no status/vencimento da fatura.
     */
    public static function categoryForInvoice(mixed $invoice, ?string $scheduleOffset = null): string
    {
        if ($scheduleOffset && ! in_array($scheduleOffset, ['manual', 'custom', 'send_now'], true)) {
            return self::categoryForOffset($scheduleOffset);
        }

        $dueDate = null;
        if (is_object($invoice) && ! empty($invoice->due_date)) {
            $dueDate = \Carbon\Carbon::parse($invoice->due_date, 'America/Sao_Paulo')->startOfDay();
        }

        if (! $dueDate) {
            return 'cobranca';
        }

        $today = now('America/Sao_Paulo')->startOfDay();
        if ($dueDate->lt($today)) {
            return 'atraso';
        }

        if ($dueDate->eq($today)) {
            return 'cobranca';
        }

        return 'lembrete';
    }

    /**
     * Resolve o template do WhatsApp mais apropriado para o tenant, offset, categoria e/ou slug.
     */
    public function whatsapp(?int $tenantId, ?string $scheduleOffset = null, ?string $category = null, ?string $slug = null): ?WhatsAppTemplate
    {
        // 1. Se um slug específico foi solicitado, tenta resolver diretamente
        if ($slug) {
            $bySlug = $this->resolveWhatsAppBySlug($tenantId, $slug);
            if ($bySlug) {
                return $bySlug;
            }
        }

        $category = $category ?: self::categoryForOffset($scheduleOffset);
        $preferred = $this->preferredTemplate('whatsapp', WhatsAppTemplate::class, $tenantId, $category, $scheduleOffset);

        if ($preferred instanceof WhatsAppTemplate) {
            return $preferred;
        }

        if ($tenantId) {
            $tenantTemplate = $this->firstScheduledTemplate(
                WhatsAppTemplate::query()->where('tenant_id', $tenantId),
                $scheduleOffset,
                $category,
                preferNewest: true
            );

            if ($tenantTemplate) {
                return $tenantTemplate;
            }
        }

        $systemTemplate = $this->firstScheduledTemplate(
            WhatsAppTemplate::query()->whereNull('tenant_id'),
            $scheduleOffset,
            $category
        );

        if ($systemTemplate) {
            // Se o tenant personalizou este template do sistema, prioriza a personalização do tenant
            if ($tenantId) {
                $customized = WhatsAppTemplate::query()
                    ->where('tenant_id', $tenantId)
                    ->where('parent_id', $systemTemplate->id)
                    ->where('active', true)
                    ->first();
                if ($customized) {
                    return $customized;
                }
            }

            return $systemTemplate;
        }

        // Se schedule_offset foi fornecido como manual, tenta resolver o template oficial cobranca-gerada
        if ($scheduleOffset === 'manual') {
            $manualTpl = $this->resolveWhatsAppBySlug($tenantId, 'cobranca-gerada');
            if ($manualTpl) {
                return $manualTpl;
            }
        }

        // Se ainda não encontrou e uma categoria foi fornecida (sem offset restritivo), busca template ativo da categoria excluindo boletos bancários
        if ($category && ($scheduleOffset === null || $scheduleOffset === '')) {
            $excludeBoleto = ! in_array($category, ['boleto', 'bank_slip'], true);

            if ($tenantId) {
                $tenantCat = WhatsAppTemplate::query()
                    ->where('tenant_id', $tenantId)
                    ->where('active', true)
                    ->where('category', $category)
                    ->when($excludeBoleto, fn (Builder $q) => $q->where('slug', '!=', 'boleto-fatura'))
                    ->orderByDesc('is_default')
                    ->orderByDesc('id')
                    ->first();
                if ($tenantCat) {
                    return $tenantCat;
                }
            }

            $sysCat = WhatsAppTemplate::query()
                ->whereNull('tenant_id')
                ->where('active', true)
                ->where('category', $category)
                ->when($excludeBoleto, fn (Builder $q) => $q->where('slug', '!=', 'boleto-fatura'))
                ->orderByDesc('is_default')
                ->first();

            if ($sysCat && $tenantId) {
                $customized = WhatsAppTemplate::query()
                    ->where('tenant_id', $tenantId)
                    ->where('parent_id', $sysCat->id)
                    ->where('active', true)
                    ->first();
                if ($customized) {
                    return $customized;
                }
            }

            return $sysCat;
        }

        return null;
    }

    public function resolveWhatsAppBySlug(?int $tenantId, string $slug): ?WhatsAppTemplate
    {
        if ($tenantId) {
            // 1. Procura se o tenant possui template próprio ativo com o slug exato
            $tenantTpl = WhatsAppTemplate::query()
                ->where('tenant_id', $tenantId)
                ->where('slug', $slug)
                ->where('active', true)
                ->first();
            if ($tenantTpl) {
                return $tenantTpl;
            }

            // 2. Procura o template base do sistema para este slug
            $systemTpl = WhatsAppTemplate::query()
                ->whereNull('tenant_id')
                ->where('slug', $slug)
                ->first();

            if ($systemTpl) {
                // Verifica se o perfil do tenant personalizou este modelo do sistema (White Label)
                $customized = WhatsAppTemplate::query()
                    ->where('tenant_id', $tenantId)
                    ->where('parent_id', $systemTpl->id)
                    ->where('active', true)
                    ->first();

                if ($customized) {
                    return $customized;
                }

                if ($systemTpl->active) {
                    return $systemTpl;
                }
            }
        }

        return WhatsAppTemplate::query()
            ->whereNull('tenant_id')
            ->where('slug', $slug)
            ->where('active', true)
            ->first();
    }

    public function email(?int $tenantId, ?string $scheduleOffset, ?string $category = null): ?EmailTemplate
    {
        if (! $tenantId) {
            return null;
        }

        $category = $category ?: self::categoryForOffset($scheduleOffset);
        $preferred = $this->preferredTemplate('email', EmailTemplate::class, $tenantId, $category, $scheduleOffset);

        if ($preferred instanceof EmailTemplate) {
            return $preferred;
        }

        return $this->firstScheduledTemplate(
            EmailTemplate::query()->where('tenant_id', $tenantId),
            $scheduleOffset,
            $category,
            preferNewest: true
        );
    }

    public function setDefault(Tenant $tenant, string $channel, Model $template): TenantTemplatePreference
    {
        $category = (string) $template->getAttribute('category');
        $scheduleOffset = $template->getAttribute('schedule_offset');
        $preferenceKey = self::preferenceKey($category, $scheduleOffset);

        $this->assertTemplateBelongsToTenant($tenant, $channel, $template);

        $modelClass = $channel === 'email' ? EmailTemplate::class : WhatsAppTemplate::class;
        $modelClass::query()
            ->where('tenant_id', $tenant->id)
            ->where('category', $category)
            ->where('schedule_offset', $scheduleOffset)
            ->update(['is_default' => false]);

        if ((int) $template->getAttribute('tenant_id') === $tenant->id) {
            $template->forceFill(['is_default' => true])->save();
        }

        return TenantTemplatePreference::updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'channel' => $channel,
                'preference_key' => $preferenceKey,
            ],
            [
                'template_id' => (int) $template->getKey(),
                'meta' => [
                    'category' => $category,
                    'schedule_offset' => $scheduleOffset,
                    'template_name' => (string) $template->getAttribute('name'),
                ],
            ]
        );
    }

    public function preferenceIds(Tenant $tenant, string $channel): array
    {
        return TenantTemplatePreference::query()
            ->where('tenant_id', $tenant->id)
            ->where('channel', $channel)
            ->pluck('template_id', 'preference_key')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function preferredTemplate(string $channel, string $modelClass, ?int $tenantId, ?string $category, ?string $scheduleOffset): ?Model
    {
        if (! $tenantId) {
            return null;
        }

        $preference = TenantTemplatePreference::query()
            ->where('tenant_id', $tenantId)
            ->where('channel', $channel)
            ->where('preference_key', self::preferenceKey($category, $scheduleOffset))
            ->first();

        if (! $preference) {
            return null;
        }

        $template = $modelClass::query()
            ->where('id', $preference->template_id)
            ->where('active', true)
            ->first();

        if (! $template) {
            return null;
        }

        if ($channel === 'whatsapp') {
            $ownerId = $template->getAttribute('tenant_id');
            return ($ownerId === null || (int) $ownerId === $tenantId) ? $template : null;
        }

        return (int) $template->getAttribute('tenant_id') === $tenantId ? $template : null;
    }

    protected function firstScheduledTemplate(Builder $query, ?string $scheduleOffset, ?string $category, bool $preferNewest = false): ?Model
    {
        $base = (clone $query)->where('active', true)->where('schedule_offset', $scheduleOffset);

        $strict = (clone $base)
            ->when($category, fn (Builder $q) => $q->where('category', $category))
            ->orderByDesc('is_default');
        $preferNewest ? $strict->orderByDesc('id') : $strict->orderBy('id');

        $template = $strict->first();
        if ($template) {
            return $template;
        }

        $fallback = (clone $base)->orderByDesc('is_default');
        $preferNewest ? $fallback->orderByDesc('id') : $fallback->orderBy('id');

        $template = $fallback->first();
        if ($template) {
            return $template;
        }

        // Se o offset não tiver template exato (ex: -5d, -2d, +2d configurado pelo tenant),
        // busca o melhor template ativo da categoria correspondente (lembrete, atraso ou cobranca)
        if ($category) {
            $catFallback = (clone $query)->where('active', true)
                ->where('category', $category)
                ->orderByDesc('is_default');
            $preferNewest ? $catFallback->orderByDesc('id') : $catFallback->orderBy('id');

            $template = $catFallback->first();
            if ($template) {
                return $template;
            }
        }

        return null;
    }

    protected function assertTemplateBelongsToTenant(Tenant $tenant, string $channel, Model $template): void
    {
        $ownerId = $template->getAttribute('tenant_id');

        if ($channel === 'whatsapp' && ($ownerId === null || (int) $ownerId === $tenant->id)) {
            return;
        }

        if ($channel === 'email' && (int) $ownerId === $tenant->id) {
            return;
        }

        throw new \RuntimeException('Template indisponivel para este tenant.');
    }
}
