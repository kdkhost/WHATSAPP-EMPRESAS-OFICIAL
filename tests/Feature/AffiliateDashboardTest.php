<?php

use App\Models\User;
use App\Modules\Affiliates\Models\Affiliate;
use App\Modules\Affiliates\Models\AffiliateCommission;
use App\Modules\Affiliates\Models\AffiliateReferral;

it('abre o painel de afiliado usando a coluna tecnica de comissao', function () {
    $affiliateUser = User::create([
        'name' => 'Afiliado Teste',
        'email' => 'afiliado@example.com',
        'password' => 'secret',
    ]);

    $referredUser = User::create([
        'name' => 'Cliente Indicado',
        'email' => 'cliente-indicado@example.com',
        'password' => 'secret',
    ]);

    $affiliate = Affiliate::create([
        'user_id' => $affiliateUser->id,
        'status' => 'active',
    ]);

    $referral = AffiliateReferral::create([
        'affiliate_id' => $affiliate->id,
        'user_id' => $referredUser->id,
        'cookie_expires_at' => now()->addDays(60),
    ]);

    AffiliateCommission::create([
        'affiliate_id' => $affiliate->id,
        'referral_id' => $referral->id,
        'base_amount' => 97.90,
        'commission_pct' => 20,
        'commission_amount' => 19.58,
        'status' => 'pending',
    ]);

    $this->actingAs($affiliateUser)
        ->get(route('tenant.affiliate.dashboard'))
        ->assertOk()
        ->assertSee('Programa de Afiliados')
        ->assertSee('R$ 19,58');
});
