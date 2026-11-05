<?php

namespace App\Modules\Affiliates\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\AffiliateClick;
use App\Modules\Affiliates\Models\AffiliateCommission;
use App\Modules\Affiliates\Models\AffiliateWithdrawal;
use App\Modules\Affiliates\Services\AffiliateService;
use App\Modules\Tenancy\Models\Tenant;
use App\Support\Format;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AffiliateAdminController extends Controller
{
    public function __construct(protected AffiliateService $service) {}

    public function index(Request $request)
    {
        $kpis = $this->service->getKpisAndChartData(30);
        $globalSettings = $this->service->getGlobalSettings();

        $stats = [
            'total_affiliates'    => $kpis['total_affiliates'],
            'active_affiliates'   => $kpis['active_affiliates'],
            'total_paid'          => $kpis['total_paid'],
            'pending_withdrawals' => $kpis['pending_withdrawals'],
            'total_clicks'        => $kpis['total_clicks'],
            'total_conversions'   => $kpis['total_conversions'],
            'conversion_rate'     => $kpis['conversion_rate'],
            'total_earned'        => $kpis['total_earned'],
            'pending_commissions' => $kpis['pending_commissions'],
        ];

        $top = Affiliate::with(['user', 'tenant'])->orderByDesc('total_earned')->limit(10)->get();

        $pendingWithdrawals = AffiliateWithdrawal::with(['affiliate.user', 'affiliate.tenant'])
            ->where('status', 'pending')
            ->orderBy('id')
            ->get();

        // Lista paginada com filtro de pesquisa e status
        $q = Affiliate::with(['user', 'tenant'])->orderByDesc('id');
        if ($search = trim((string) $request->query('q'))) {
            $q->where(function ($sub) use ($search) {
                $sub->where('slug', 'like', "%{$search}%")
                    ->orWhere('referral_code', 'like', "%{$search}%")
                    ->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('tenant', fn($t) => $t->where('company_name', 'like', "%{$search}%"));
            });
        }
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }
        $affiliates = $q->paginate(15)->withQueryString();

        $recentClicks = AffiliateClick::with(['affiliate.user', 'affiliate.tenant'])
            ->latest('id')
            ->limit(20)
            ->get();

        $recentCommissions = AffiliateCommission::with(['affiliate.user', 'referral.tenant', 'referral.user'])
            ->latest('id')
            ->limit(20)
            ->get();

        $existingUserIds = Affiliate::pluck('user_id')->filter()->unique();
        $availableTenants = Tenant::with('user')
            ->whereDoesntHave('affiliate')
            ->whereNotIn('user_id', $existingUserIds)
            ->orderBy('company_name')
            ->get();

        return view('affiliates::admin.index', compact(
            'kpis',
            'globalSettings',
            'stats',
            'top',
            'pendingWithdrawals',
            'affiliates',
            'recentClicks',
            'recentCommissions',
            'availableTenants'
        ));
    }

    public function affiliates(Request $request)
    {
        $q = Affiliate::with(['user', 'tenant'])->orderByDesc('total_earned');
        if ($s = $request->query('status')) $q->where('status', $s);
        $list = $q->paginate(25)->withQueryString();
        return view('affiliates::admin.list', compact('list'));
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $request->merge([
            'default_commission_pct'   => $request->filled('default_commission_pct') ? Format::decimal($request->input('default_commission_pct')) : 20.0,
            'default_commission_fixed' => $request->filled('default_commission_fixed') ? Format::decimal($request->input('default_commission_fixed')) : 0.0,
            'buyer_discount_pct'       => $request->filled('buyer_discount_pct') ? Format::decimal($request->input('buyer_discount_pct')) : 10.0,
            'buyer_discount_fixed'     => $request->filled('buyer_discount_fixed') ? Format::decimal($request->input('buyer_discount_fixed')) : 0.0,
            'min_withdrawal'           => $request->filled('min_withdrawal') ? Format::decimal($request->input('min_withdrawal')) : 50.0,
        ]);

        $data = $request->validate([
            'enabled'                  => 'nullable|boolean',
            'commission_mode'          => 'required|in:percentage,fixed',
            'default_commission_pct'   => 'nullable|numeric|min:0|max:100',
            'default_commission_fixed' => 'nullable|numeric|min:0',
            'default_commission_type'  => 'required|in:recurring,first_payment,lifetime',
            'buyer_discount_enabled'   => 'nullable|boolean',
            'buyer_discount_mode'      => 'required|in:percentage,fixed',
            'buyer_discount_pct'       => 'nullable|numeric|min:0|max:100',
            'buyer_discount_fixed'     => 'nullable|numeric|min:0',
            'buyer_discount_type'      => 'required|in:first_payment,recurring',
            'cookie_days'              => 'required|integer|min:1|max:365',
            'min_withdrawal'           => 'required|numeric|min:1',
            'auto_approve_days'        => 'required|integer|min:0|max:90',
        ]);

        $this->service->updateGlobalSettings($data);

        return response()->json([
            'ok'      => true,
            'message' => 'Configurações globais do Programa de Afiliados salvas com sucesso!',
        ]);
    }

    public function createAffiliate(Request $request): JsonResponse
    {
        $input = $request->all();

        $input['commission_pct']   = (isset($input['commission_pct']) && $input['commission_pct'] !== '') ? Format::decimal($input['commission_pct']) : 20.0;
        $input['commission_fixed'] = (isset($input['commission_fixed']) && $input['commission_fixed'] !== '') ? Format::decimal($input['commission_fixed']) : 0.0;
        $input['discount_pct']     = (isset($input['discount_pct']) && $input['discount_pct'] !== '') ? Format::decimal($input['discount_pct']) : 10.0;
        $input['discount_fixed']   = (isset($input['discount_fixed']) && $input['discount_fixed'] !== '') ? Format::decimal($input['discount_fixed']) : 0.0;
        $input['min_withdrawal']   = (isset($input['min_withdrawal']) && $input['min_withdrawal'] !== '') ? Format::decimal($input['min_withdrawal']) : 50.0;

        // Se o modo for percentage, zera o fixed; se for fixed, zera o pct
        if (($input['commission_mode'] ?? 'percentage') === 'percentage') {
            $input['commission_fixed'] = 0.0;
        } else {
            $input['commission_pct'] = 0.0;
        }

        if (($input['discount_mode'] ?? 'percentage') === 'percentage') {
            $input['discount_fixed'] = 0.0;
        } else {
            $input['discount_pct'] = 0.0;
        }

        $validator = \Illuminate\Support\Facades\Validator::make($input, [
            'tenant_id'        => 'nullable|exists:tenants,id',
            'user_id'          => 'nullable|exists:users,id',
            'commission_mode'  => 'required|in:percentage,fixed',
            'commission_pct'   => 'nullable|numeric|min:0|max:100',
            'commission_fixed' => 'nullable|numeric|min:0',
            'commission_type'  => 'required|in:recurring,first_payment,lifetime',
            'discount_mode'    => 'required|in:percentage,fixed',
            'discount_pct'     => 'nullable|numeric|min:0|max:100',
            'discount_fixed'   => 'nullable|numeric|min:0',
            'discount_type'    => 'required|in:first_payment,recurring',
            'min_withdrawal'   => 'nullable|numeric|min:1',
            'slug'             => 'nullable|string|max:60|unique:affiliates,slug',
            'status'           => 'required|in:active,pending,suspended',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'error' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        if (empty($data['tenant_id']) && empty($data['user_id'])) {
            return response()->json(['ok' => false, 'error' => 'Selecione um tenant ou usuário para vincular ao afiliado.'], 422);
        }

        $userId = $data['user_id'] ?? null;
        $tenantId = $data['tenant_id'] ?? null;

        if ($tenantId && ! $userId) {
            $t = Tenant::findOrFail($tenantId);
            $userId = $t->user_id;
        }

        $existing = Affiliate::where('user_id', $userId)->first();
        if ($existing) {
            return response()->json(['ok' => false, 'error' => 'Este usuário já possui cadastro de afiliado.'], 422);
        }

        $user = User::find($userId);
        $slugBase = $user?->name ?: 'afiliado';
        $slug = !empty($data['slug']) ? Str::slug($data['slug']) : (Str::slug($slugBase) . '-' . Str::lower(Str::random(5)));
        $status = $data['status'] ?? 'active';

        $affiliate = Affiliate::create([
            'user_id'          => $userId,
            'tenant_id'        => $tenantId,
            'slug'             => $slug,
            'referral_code'    => strtoupper(Str::random(8)),
            'commission_mode'  => $data['commission_mode'],
            'commission_pct'   => (float) ($data['commission_pct'] ?? 20.00),
            'commission_fixed' => (float) ($data['commission_fixed'] ?? 0.00),
            'commission_type'  => $data['commission_type'],
            'discount_mode'    => $data['discount_mode'],
            'discount_pct'     => (float) ($data['discount_pct'] ?? 10.00),
            'discount_fixed'   => (float) ($data['discount_fixed'] ?? 0.00),
            'discount_type'    => $data['discount_type'],
            'min_withdrawal'   => (float) ($data['min_withdrawal'] ?? 50.00),
            'status'           => $status,
            'applied_at'       => now(),
            'approved_at'      => $status === 'active' ? now() : null,
        ]);

        return response()->json([
            'ok'      => true,
            'message' => 'Afiliado cadastrado com sucesso!',
            'affiliate' => $affiliate,
        ]);
    }

    public function updateAffiliate(Request $request, int $id): JsonResponse
    {
        $affiliate = Affiliate::findOrFail($id);

        $input = $request->all();

        if (array_key_exists('commission_pct', $input)) {
            $input['commission_pct'] = ($input['commission_pct'] !== '' && $input['commission_pct'] !== null) ? Format::decimal($input['commission_pct']) : null;
        }
        if (array_key_exists('commission_fixed', $input)) {
            $input['commission_fixed'] = ($input['commission_fixed'] !== '' && $input['commission_fixed'] !== null) ? Format::decimal($input['commission_fixed']) : null;
        }
        if (array_key_exists('discount_pct', $input)) {
            $input['discount_pct'] = ($input['discount_pct'] !== '' && $input['discount_pct'] !== null) ? Format::decimal($input['discount_pct']) : null;
        }
        if (array_key_exists('discount_fixed', $input)) {
            $input['discount_fixed'] = ($input['discount_fixed'] !== '' && $input['discount_fixed'] !== null) ? Format::decimal($input['discount_fixed']) : null;
        }
        if (array_key_exists('min_withdrawal', $input)) {
            $input['min_withdrawal'] = ($input['min_withdrawal'] !== '' && $input['min_withdrawal'] !== null) ? Format::decimal($input['min_withdrawal']) : null;
        }

        $validator = \Illuminate\Support\Facades\Validator::make($input, [
            'commission_mode'  => 'nullable|in:percentage,fixed',
            'commission_pct'   => 'nullable|numeric|min:0|max:100',
            'commission_fixed' => 'nullable|numeric|min:0',
            'commission_type'  => 'nullable|in:recurring,first_payment,lifetime',
            'discount_mode'    => 'nullable|in:percentage,fixed',
            'discount_pct'     => 'nullable|numeric|min:0|max:100',
            'discount_fixed'   => 'nullable|numeric|min:0',
            'discount_type'    => 'nullable|in:first_payment,recurring',
            'min_withdrawal'   => 'nullable|numeric|min:1',
            'status'           => 'nullable|in:pending,active,rejected,suspended',
            'slug'             => 'nullable|string|max:60|unique:affiliates,slug,' . $affiliate->id,
            'referral_code'    => 'nullable|string|max:20|unique:affiliates,referral_code,' . $affiliate->id,
            'split_enabled'    => 'nullable|boolean',
            'split_pct'        => 'nullable|numeric|min:0|max:100',
            'split_mode'       => 'nullable|in:percentage,fixed',
            'mp_collector_id'  => 'nullable|string|max:60',
            'admin_notes'      => 'nullable|string|max:500',
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'error' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        if (!empty($data['slug'])) {
            $data['slug'] = Str::slug($data['slug']);
        }
        if (!empty($data['referral_code'])) {
            $data['referral_code'] = strtoupper(trim($data['referral_code']));
        }

        $affiliate->update(array_filter($data, fn($v) => $v !== null));

        return response()->json([
            'ok'      => true,
            'message' => 'Regras do afiliado atualizadas com sucesso!',
        ]);
    }

    public function approveAffiliate(Request $request, int $id): JsonResponse
    {
        $affiliate = Affiliate::with(['user', 'tenant'])->findOrFail($id);

        $updateData = [
            'status'           => 'active',
            'approved_at'      => now(),
            'rejection_reason' => null,
        ];

        if ($request->has('split_enabled')) {
            $updateData['split_enabled'] = (bool) $request->input('split_enabled');
        }
        if ($request->filled('split_pct')) {
            $updateData['split_pct'] = (float) $request->input('split_pct');
        }
        if ($request->filled('mp_collector_id')) {
            $updateData['mp_collector_id'] = trim($request->input('mp_collector_id'));
        }
        if ($request->filled('admin_notes')) {
            $updateData['admin_notes'] = trim($request->input('admin_notes'));
        }

        $affiliate->update($updateData);

        try {
            app(\App\Modules\Affiliates\Services\AffiliateNotificationService::class)->notifyApproved($affiliate);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[AffiliateAdmin] Erro ao notificar aprovação: ' . $e->getMessage());
        }

        return response()->json([
            'ok'      => true,
            'message' => "Afiliado #{$affiliate->id} ({$affiliate->user->name}) aprovado com sucesso! Notificações por e-mail e WhatsApp disparadas.",
        ]);
    }

    public function rejectAffiliate(Request $request, int $id): JsonResponse
    {
        $affiliate = Affiliate::with(['user', 'tenant'])->findOrFail($id);

        $request->validate([
            'reason' => 'required|string|min:3|max:500',
        ], [
            'reason.required' => 'Informe o motivo da não aprovação.',
        ]);

        $reason = trim($request->input('reason'));

        $affiliate->update([
            'status'           => 'rejected',
            'rejection_reason' => $reason,
        ]);

        try {
            app(\App\Modules\Affiliates\Services\AffiliateNotificationService::class)->notifyRejected($affiliate, $reason);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[AffiliateAdmin] Erro ao notificar rejeição: ' . $e->getMessage());
        }

        return response()->json([
            'ok'      => true,
            'message' => "Inscrição do afiliado rejeitada. Motivo registrado e notificações enviadas ao cliente.",
        ]);
    }

    public function pendAffiliate(Request $request, int $id): JsonResponse
    {
        $affiliate = Affiliate::with(['user', 'tenant'])->findOrFail($id);

        $request->validate([
            'instructions' => 'required|string|min:3|max:500',
        ], [
            'instructions.required' => 'Informe as orientações de pendência.',
        ]);

        $instructions = trim($request->input('instructions'));

        $affiliate->update([
            'status'           => 'pending',
            'rejection_reason' => $instructions,
        ]);

        try {
            app(\App\Modules\Affiliates\Services\AffiliateNotificationService::class)->notifyPending($affiliate, $instructions);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[AffiliateAdmin] Erro ao notificar pendência: ' . $e->getMessage());
        }

        return response()->json([
            'ok'      => true,
            'message' => "Inscrição marcada com pendência. Orientações enviadas ao cliente por e-mail e WhatsApp.",
        ]);
    }

    public function approveCommission(int $id): JsonResponse
    {
        $c = AffiliateCommission::findOrFail($id);
        $this->service->approveCommission($c);
        return response()->json(['ok' => true, 'message' => 'Comissão aprovada e saldo creditado!']);
    }

    public function rejectCommission(Request $request, int $id): JsonResponse
    {
        $c = AffiliateCommission::findOrFail($id);
        if ($c->status !== 'pending') {
            return response()->json(['ok' => false, 'error' => 'Apenas comissões pendentes podem ser rejeitadas.'], 422);
        }
        $c->update([
            'status' => 'canceled',
            'notes'  => $request->input('note', 'Rejeitada pelo administrador.')
        ]);
        return response()->json(['ok' => true, 'message' => 'Comissão rejeitada.']);
    }

    public function approveWithdrawal(Request $request, int $id): JsonResponse
    {
        $w = AffiliateWithdrawal::findOrFail($id);
        if ($w->status !== 'pending') {
            return response()->json(['ok' => false, 'error' => 'Saque já processado.'], 422);
        }
        $w->update(['status' => 'paid', 'paid_at' => now(), 'admin_note' => $request->input('note')]);
        $w->affiliate->increment('total_paid', $w->amount);
        return response()->json(['ok' => true, 'message' => 'Saque marcado como pago!']);
    }

    public function rejectWithdrawal(Request $request, int $id): JsonResponse
    {
        $w = AffiliateWithdrawal::findOrFail($id);
        if ($w->status !== 'pending') return response()->json(['ok' => false, 'error' => 'Saque já processado.'], 422);
        $w->update(['status' => 'rejected', 'admin_note' => $request->input('note')]);
        $w->affiliate->increment('balance', $w->amount);
        return response()->json(['ok' => true, 'message' => 'Saque rejeitado e saldo estornado ao afiliado.']);
    }
}
