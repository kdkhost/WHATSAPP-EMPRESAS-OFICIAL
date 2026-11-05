<?php

/*
 * Gateways de pagamento (checkout transparente).
 * Cada gateway implementa App\Modules\Gateways\Contracts\PaymentGateway.
 * Os webhooks sao dinamicos por requerimento (cada fatura gera URL unica).
 */

return [
    'default' => 'mercadopago',

    'mercadopago' => [
        'access_token'          => env('MERCADOPAGO_ACCESS_TOKEN', ''),
        'public_key'            => env('MERCADOPAGO_PUBLIC_KEY', ''),
        'client_id'             => env('MERCADOPAGO_CLIENT_ID', ''),
        'client_secret'         => env('MERCADOPAGO_CLIENT_SECRET', ''),
        'webhook_secret'        => env('MERCADOPAGO_WEBHOOK_SECRET', ''),
        'sandbox'               => (bool) env('MERCADOPAGO_SANDBOX', false),
        'enabled'               => (bool) env('MERCADOPAGO_ENABLED', true),
        'pix_enabled'           => (bool) env('MERCADOPAGO_PIX_ENABLED', true),
        'credit_card_enabled'   => (bool) env('MERCADOPAGO_CREDIT_CARD_ENABLED', true),
        'boleto_enabled'        => (bool) env('MERCADOPAGO_BOLETO_ENABLED', true),
        'expiration_days'       => (int)  env('MERCADOPAGO_EXPIRATION_DAYS', 3),
        'installments_max'      => (int)  env('MERCADOPAGO_INSTALLMENTS_MAX', 12),
        'soft_descriptor'       => env('MERCADOPAGO_SOFT_DESCRIPTOR', ''),
        'default_description'   => env('MERCADOPAGO_DEFAULT_DESCRIPTION', 'Cobranca'),
        // Multas e juros por atraso
        'late_fee_pct'          => (float) env('MERCADOPAGO_LATE_FEE_PCT', 0),       // % multa (ex: 2 = 2%)
        'late_interest_pct_day' => (float) env('MERCADOPAGO_LATE_INTEREST_DAY', 0),  // % juros ao dia (ex: 0.033)
        'late_fee_after_days'   => (int)   env('MERCADOPAGO_LATE_FEE_AFTER_DAYS', 1), // carencia em dias
    ],

    'efipro' => [
        'client_id'     => env('EFIPRO_CLIENT_ID', ''),
        'client_secret' => env('EFIPRO_CLIENT_SECRET', ''),
        'sandbox'       => (bool) env('EFIPRO_SANDBOX', true),
        'enabled'       => (bool) env('EFIPRO_ENABLED', false),
        'certificate_path' => env('EFIPRO_CERTIFICATE_PATH', ''),
        'pix_key'       => env('EFIPRO_PIX_KEY', ''),
        'pix_key_type'  => env('EFIPRO_PIX_KEY_TYPE', 'email'),
        'pix_expiration' => (int) env('EFIPRO_PIX_EXPIRATION', 3600),
        'boleto_enabled' => (bool) env('EFIPRO_BOLETO_ENABLED', false),
        'card_enabled'  => (bool) env('EFIPRO_CARD_ENABLED', false),
        'installments_max' => (int) env('EFIPRO_INSTALLMENTS_MAX', 1),
        'webhook_secret' => env('EFIPRO_WEBHOOK_SECRET', ''),
        'account_id'    => env('EFIPRO_ACCOUNT_ID', ''),
    ],

    'sumup' => [
        'client_id'     => env('SUMUP_CLIENT_ID', ''),
        'client_secret' => env('SUMUP_CLIENT_SECRET', ''),
        'api_key'       => env('SUMUP_API_KEY', ''),
        'merchant_code' => env('SUMUP_MERCHANT_CODE', ''),
        'redirect_uri'  => env('SUMUP_REDIRECT_URI', ''),
        'sandbox'       => (bool) env('SUMUP_SANDBOX', false),
        'enabled'       => (bool) env('SUMUP_ENABLED', false),
        'currency'      => env('SUMUP_CURRENCY', 'BRL'),
        'default_description' => env('SUMUP_DEFAULT_DESCRIPTION', 'Pagamento'),
        'link_expiration' => (int) env('SUMUP_LINK_EXPIRATION', 30),
        'hosted_checkout_enabled' => (bool) env('SUMUP_HOSTED_CHECKOUT_ENABLED', true),
        'credit_card_enabled' => (bool) env('SUMUP_CREDIT_CARD_ENABLED', true),
        'webhook_secret' => env('SUMUP_WEBHOOK_SECRET', ''),
    ],
];
