<?php

/**
 * @autor marcelo-brad rj
 * @contato Tel: 21 981325441 | contato@kdkhost.com.br | @MARCELO_BRAD
 * @copyright Copyright (c) KDK HOST / Marcelo Brad. Todos os direitos reservados.
 * @propriedade-intelectual Software proprietário — Provedor do Módulo Mercado Pago SDK v2.
 */

namespace App\Modules\MercadoPago\Providers;

use App\Modules\MercadoPago\Services\MercadoPagoPaymentService;
use Illuminate\Support\ServiceProvider;

class MercadoPagoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MercadoPagoPaymentService::class, function () {
            return new MercadoPagoPaymentService();
        });
    }

    public function boot(): void
    {
        // Registro de rotas e recursos do módulo
    }
}

