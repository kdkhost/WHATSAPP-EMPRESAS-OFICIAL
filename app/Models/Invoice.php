<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    public const OPEN_DUPLICATE_STATUSES = ['pending', 'sent', 'overdue'];

    protected $fillable = [
        'tenant_id','customer_id','number','description','amount','due_date','status',
        'gateway','gateway_charge_id','payment_url','webhook_token','paid_at','meta',
    ];

    protected $casts = [
        'amount'    => 'decimal:2',
        'due_date'  => 'date',
        'paid_at'   => 'datetime',
        'meta'      => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            if (empty($invoice->tenant_id) && !empty($invoice->customer_id)) {
                $invoice->tenant_id = \App\Models\Customer::where('id', $invoice->customer_id)->value('tenant_id');
            }
        });

        static::saving(function (Invoice $invoice) {
            if (empty($invoice->webhook_token)) {
                $invoice->webhook_token = \Illuminate\Support\Str::random(48);
            }
            // Garante que o link de pagamento seja sempre o checkout transparente seguro do próprio painel
            $publicUrl = url('/fatura/' . $invoice->webhook_token);
            if (empty($invoice->payment_url) || str_contains((string) $invoice->payment_url, 'mercadopago.com') || str_contains((string) $invoice->payment_url, '/ticket')) {
                if (filled($invoice->payment_url) && str_contains((string) $invoice->payment_url, 'mercadopago.com')) {
                    $meta = $invoice->meta ?: [];
                    $meta['gateway_payment']['ticket_url'] = (string) $invoice->payment_url;
                    $invoice->meta = $meta;
                }
                $invoice->payment_url = $publicUrl;
            }

            // Garante conformidade bancária: se skip_weekends_holidays estiver ativo, ajusta vencimento para dia útil
            if ($invoice->due_date && in_array(strtolower((string) ($invoice->status ?: 'pending')), ['pending', 'open', 'sent'], true)) {
                $shouldSkip = true;
                if ($invoice->tenant_id) {
                    $configVal = \Illuminate\Support\Facades\DB::table('tenant_billing_config')
                        ->where('tenant_id', $invoice->tenant_id)
                        ->value('skip_weekends_holidays');
                    if ($configVal !== null) {
                        $shouldSkip = (bool) $configVal;
                    }
                }
                if ($shouldSkip) {
                    try {
                        $holidays = app(\App\Modules\Holidays\Services\HolidayService::class);
                        $dueCarbon = \Carbon\Carbon::parse($invoice->due_date, 'America/Sao_Paulo')->startOfDay();
                        if ($holidays->isNonBusinessDay($dueCarbon)) {
                            $nextBusinessDay = $holidays->nextBusinessDay($dueCarbon);
                            $invoice->due_date = $nextBusinessDay->toDateString();
                        }
                    } catch (\Throwable) {
                        // Não interrompe o salvamento caso haja falha temporária no serviço de feriados
                    }
                }
            }
        });

        static::updated(function (Invoice $invoice) {
            if ($invoice->isDirty('status')) {
                $status = strtolower((string) $invoice->status);
                $inactiveList = ['paid', 'paga', 'pago', 'canceled', 'cancelled', 'cancelada', 'cancelado', 'refunded', 'estornada', 'estornado'];
                if (in_array($status, $inactiveList, true)) {
                    $tipo = match (true) {
                        in_array($status, ['canceled', 'cancelled', 'cancelada', 'cancelado'], true) => 'CANCELADA',
                        in_array($status, ['refunded', 'estornada', 'estornado'], true) => 'ESTORNADA',
                        default => 'PAGA',
                    };
                    \Illuminate\Support\Facades\DB::table('billing_dispatches')
                        ->where('invoice_id', $invoice->id)
                        ->whereIn('status', ['scheduled', 'failed', 'sending'])
                        ->update([
                            'status'     => 'canceled',
                            'last_error' => "Cancelado: fatura com status {$tipo} no sistema (cobrança bloqueada por WhatsApp e e-mail).",
                            'updated_at' => now(),
                        ]);
                }
            }

            if ($invoice->isDirty(['status', 'paid_at', 'amount', 'due_date'])) {
                \App\Modules\Tenancy\Models\CustomerPackageInstallment::where('invoice_id', $invoice->id)
                    ->update([
                        'status'   => $invoice->status,
                        'paid_at'  => $invoice->paid_at,
                        'amount'   => $invoice->amount,
                        'due_date' => $invoice->due_date,
                    ]);
            }

            if ($invoice->isDirty('due_date') && in_array(strtolower((string) $invoice->status), ['pending', 'open', 'sent'], true)) {
                static::rescheduleDispatchesForInvoice($invoice);
            }
        });

        static::deleted(function (Invoice $invoice) {
            \App\Modules\Tenancy\Models\CustomerPackageInstallment::where('invoice_id', $invoice->id)->delete();
        });
    }

    /**
     * Ajusta faturas pendentes com vencimento em finais de semana ou feriados para o próximo dia útil.
     */
    public static function adjustPendingInvoicesToBusinessDays(?int $tenantId = null): int
    {
        $query = static::query()
            ->whereIn('status', ['pending', 'open', 'sent'])
            ->whereNotNull('due_date');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $invoices = $query->get();
        $holidays = app(\App\Modules\Holidays\Services\HolidayService::class);
        $moved = 0;

        foreach ($invoices as $inv) {
            $shouldSkip = true;
            if ($inv->tenant_id) {
                $configVal = \Illuminate\Support\Facades\DB::table('tenant_billing_config')
                    ->where('tenant_id', $inv->tenant_id)
                    ->value('skip_weekends_holidays');
                if ($configVal !== null) {
                    $shouldSkip = (bool) $configVal;
                }
            }

            if (! $shouldSkip) {
                continue;
            }

            $dueCarbon = \Carbon\Carbon::parse($inv->due_date, 'America/Sao_Paulo')->startOfDay();
            if ($holidays->isNonBusinessDay($dueCarbon)) {
                $nextDay = $holidays->nextBusinessDay($dueCarbon)->toDateString();
                $inv->due_date = $nextDay;
                $inv->saveQuietly();
                static::rescheduleDispatchesForInvoice($inv);
                $moved++;
            }
        }

        return $moved;
    }

    /**
     * Reagenda dispatches pendentes de uma fatura para refletir a nova data de vencimento em dias úteis.
     */
    public static function rescheduleDispatchesForInvoice(Invoice $invoice): void
    {
        if (! $invoice->due_date) {
            return;
        }

        $dispatches = \Illuminate\Support\Facades\DB::table('billing_dispatches')
            ->where('invoice_id', $invoice->id)
            ->where('status', 'scheduled')
            ->get();

        if ($dispatches->isEmpty()) {
            return;
        }

        $holidays = app(\App\Modules\Holidays\Services\HolidayService::class);
        $due = \Carbon\Carbon::parse($invoice->due_date, 'America/Sao_Paulo')->startOfDay();
        $cronTime = '09:00';
        $shouldSkip = true;

        if ($invoice->tenant_id) {
            $bConfig = \Illuminate\Support\Facades\DB::table('tenant_billing_config')
                ->where('tenant_id', $invoice->tenant_id)
                ->first(['cron_time', 'skip_weekends_holidays']);
            if ($bConfig) {
                $cronTime = (string) ($bConfig->cron_time ?: '09:00');
                if ($bConfig->skip_weekends_holidays !== null) {
                    $shouldSkip = (bool) $bConfig->skip_weekends_holidays;
                }
            }
        }

        $parts = explode(':', $cronTime);
        $hour = (int) ($parts[0] ?? 9);
        $minute = (int) ($parts[1] ?? 0);

        foreach ($dispatches as $dispatch) {
            $offset = (string) ($dispatch->schedule_offset ?? '0d');
            $days = 0;
            if (preg_match('/^([+-]?\d+)d$/', $offset, $m)) {
                $days = (int) $m[1];
            }

            $newScheduled = $due->copy()->addDays($days)->setTime($hour, $minute);
            if ($shouldSkip) {
                $newScheduled = $holidays->nextBusinessDay($newScheduled);
            }

            // Se o envio cair hoje e o horário padrão já tiver passado, agenda para agora
            if ($newScheduled->isToday() && $newScheduled->isPast()) {
                $newScheduled = now('America/Sao_Paulo');
            }

            \Illuminate\Support\Facades\DB::table('billing_dispatches')
                ->where('id', $dispatch->id)
                ->update([
                    'scheduled_at' => $newScheduled,
                    'updated_at'   => now(),
                ]);
        }
    }

    public function customer(): BelongsTo  { return $this->belongsTo(Customer::class); }
    public function tenant(): BelongsTo    { return $this->belongsTo(\App\Modules\Tenancy\Models\Tenant::class); }
    public function dispatches(): HasMany  { return $this->hasMany(BillingDispatch::class); }

    public function getPublicPaymentUrlAttribute(): string
    {
        $token = $this->webhook_token;
        if (empty($token)) {
            $token = \Illuminate\Support\Str::random(48);
            $this->updateQuietly(['webhook_token' => $token]);
        }
        return url('/fatura/' . $token);
    }

    public function hasReusableCharge(): bool
    {
        return in_array((string) $this->status, self::OPEN_DUPLICATE_STATUSES, true)
            && filled($this->payment_url)
            && filled($this->gateway_charge_id);
    }

    public static function findOpenDuplicate(
        ?int $tenantId,
        int $customerId,
        string $description,
        float $amount,
        string $dueDate
    ): ?self {
        return static::query()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->where('description', trim($description))
            ->where('amount', $amount)
            ->whereDate('due_date', $dueDate)
            ->whereIn('status', self::OPEN_DUPLICATE_STATUSES)
            ->latest('id')
            ->first();
    }
}
