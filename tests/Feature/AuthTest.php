<?php

use App\Models\User;
use App\Modules\Tenancy\Models\Plan;
use App\Modules\Tenancy\Models\Tenant;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('redirects unauthenticated users to login', function () {
    $response = $this->get('/admin/dashboard');
    $response->assertRedirect();
});

it('shows login page', function () {
    $response = $this->get('/admin/login');
    $response->assertStatus(200);
});

it('rejects invalid credentials', function () {
    $response = $this->post('/admin/login', [
        'email'    => 'invalid@example.com',
        'password' => 'wrong-password',
    ]);
    $response->assertStatus(302);
    $this->assertGuest('admin');
});

it('cadastra novo cliente com tenant e acessa o painel', function () {
    Plan::create([
        'name' => 'Starter Cadastro',
        'slug' => 'starter-cadastro',
        'price' => 0,
        'billing_cycle' => 'monthly',
        'trial_days' => 7,
        'max_instances' => 1,
        'max_customers' => 100,
        'max_invoices_month' => 500,
        'max_messages_month' => 1000,
        'custom_gateway' => false,
        'custom_templates' => true,
        'installment_enabled' => false,
        'fee_passthrough' => false,
        'active' => true,
        'is_demo' => false,
        'sort' => 1,
    ]);

    $response = $this->post(route('register.attempt'), [
        'name' => 'Cliente Novo',
        'email' => 'cliente-novo@example.com',
        'password' => 'senha-segura-123',
        'password_confirmation' => 'senha-segura-123',
        'lgpd_accepted' => '1',
    ]);

    $response->assertRedirect(route('tenant.dashboard'));
    $this->assertAuthenticated('web');

    $user = User::where('email', 'cliente-novo@example.com')->firstOrFail();

    expect(Tenant::where('user_id', $user->id)->whereIn('status', ['trial', 'active'])->exists())->toBeTrue();

    $this->get(route('tenant.dashboard'))->assertOk();
});

it('login de cliente sempre redireciona para o painel mesmo com intended na home', function () {
    $plan = Plan::create([
        'name' => 'Starter Login',
        'slug' => 'starter-login',
        'price' => 0,
        'billing_cycle' => 'monthly',
        'trial_days' => 7,
        'max_instances' => 1,
        'max_customers' => 100,
        'max_invoices_month' => 500,
        'max_messages_month' => 1000,
        'custom_gateway' => false,
        'custom_templates' => true,
        'installment_enabled' => false,
        'fee_passthrough' => false,
        'active' => true,
        'is_demo' => false,
        'sort' => 1,
    ]);

    $user = User::create([
        'name' => 'Cliente Login',
        'email' => 'cliente-login@example.com',
        'password' => 'senha-segura-123',
        'lgpd_accepted' => true,
        'lgpd_accepted_at' => now(),
    ]);

    Tenant::create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'company_name' => 'Cliente Login',
        'slug' => 'cliente-login',
        'status' => 'trial',
        'trial_ends_at' => now()->addDays(7),
    ]);

    $response = $this
        ->withSession(['url.intended' => url('/')])
        ->post(route('login.attempt'), [
            'email' => 'cliente-login@example.com',
            'password' => 'senha-segura-123',
        ]);

    $response->assertRedirect(route('tenant.dashboard'));
});

it('nao aplica rate limit em abertura da pagina de cadastro', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->get(route('register'))->assertOk();
    }
});

it('envia email padronizado de redefinicao de senha', function () {
    Notification::fake();

    $user = User::create([
        'name' => 'Cliente Teste',
        'email' => 'cliente@example.com',
        'password' => 'senha-antiga-123',
        'lgpd_accepted' => true,
        'lgpd_accepted_at' => now(),
    ]);

    $response = $this->post('/esqueci-senha', [
        'email' => $user->email,
    ]);

    $response->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('permite redefinir a senha com token valido', function () {
    $user = User::create([
        'name' => 'Cliente Reset',
        'email' => 'reset@example.com',
        'password' => 'senha-antiga-123',
        'lgpd_accepted' => true,
        'lgpd_accepted_at' => now(),
    ]);

    $token = Password::broker()->createToken($user);

    $response = $this->post('/redefinir-senha', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NovaSenha123',
        'password_confirmation' => 'NovaSenha123',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status');
    expect(Hash::check('NovaSenha123', $user->fresh()->password))->toBeTrue();
});

it('nao derruba a aplicacao quando o envio do reset falha por transporte de email', function () {
    Log::spy();

    Password::shouldReceive('sendResetLink')
        ->once()
        ->andThrow(new \RuntimeException('SMTP indisponivel'));

    $response = $this->from('/esqueci-senha')->post('/esqueci-senha', [
        'email' => 'cliente@example.com',
    ]);

    $response->assertRedirect('/esqueci-senha');
    $response->assertSessionHasErrors([
        'email' => 'Nao foi possivel enviar o email de redefinicao agora. Verifique a configuracao de email do sistema e tente novamente em instantes.',
    ]);
});

it('retorna ao formulario quando o rate limit bloqueia a redefinicao de senha', function () {
    Cache::put('rl_block:password:127.0.0.1', time() + 600, 600);

    $response = $this->from('/esqueci-senha')->post('/esqueci-senha', [
        'email' => 'cliente@example.com',
    ]);

    $response->assertStatus(429);
    expect($response->headers->get('Location'))->toEndWith('/esqueci-senha');
    $response->assertSessionHasErrors([
        'email' => 'Muitas tentativas de redefinicao de senha. Aguarde 10 minuto(s) antes de tentar novamente.',
    ]);
});
