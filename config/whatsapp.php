<?php

/**
 * @autor marcelo-brad rj
 * @contato Tel: 21 981325441 | contato@kdkhost.com.br | @MARCELO_BRAD
 * @copyright Copyright (c) KDK HOST / Marcelo Brad. Todos os direitos reservados.
 * @propriedade-intelectual Software proprietário — WhatsApp Cloud API Oficial da Meta (Graph API v21.0).
 */

return [
    /*
     * --------------------------------------------------------------------------
     * WHATSAPP CLOUD API OFICIAL DA META (GRAPH API v21.0)
     * --------------------------------------------------------------------------
     * Configuração direta e oficial da Meta for Developers para envio de templates,
     * mensagens de cobrança Pix e webhooks seguros HMAC-SHA256.
     */
    'meta_graph_version'        => env('META_GRAPH_VERSION', env('GRAPH_VERSION', 'v21.0')),
    'meta_phone_number_id'      => env('META_PHONE_NUMBER_ID', env('PHONE_NUMBER_ID', '')),
    'meta_waba_id'              => env('META_WABA_ID', env('WABA_ID', '')),
    'meta_access_token'         => env('META_ACCESS_TOKEN', env('META_APP_TOKEN', '')),
    'meta_app_secret'           => env('META_APP_SECRET', ''),
    'meta_webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN', env('META_VERIFY_TOKEN', 'kdkhost_meta_webhook_secret_2026')),

    // URL base oficial da Meta Graph API
    'meta_base_url'             => 'https://graph.facebook.com/' . env('META_GRAPH_VERSION', env('GRAPH_VERSION', 'v21.0')),

    'instance'                  => env('WHATSAPP_INSTANCE', 'meta_official'),
    'timeout'                   => (int) env('WHATSAPP_TIMEOUT', 30),
    'webhook_token'             => env('META_WEBHOOK_VERIFY_TOKEN', 'kdkhost_meta_webhook_secret_2026'),
    'version'                   => 'v21.0',

    // Política de retry para envios falhados
    'retry' => [
        'max_attempts'    => 5,
        'backoff_minutes' => [1, 5, 15, 60, 240],
    ],

    // Envio unificado de cobrança em mensagem única oficial (Pix Copia e Cola + Link da Fatura Pública)
    'single_billing_message'    => env('WHATSAPP_SINGLE_BILLING_MESSAGE', true),
    'send_separate_media'       => env('WHATSAPP_SEND_SEPARATE_MEDIA', false),
    'send_pix_qrcode_image'     => env('WHATSAPP_SEND_PIX_QRCODE_IMAGE', true),

    'features' => [
        'meta_official_api'     => true,
        'templates_preapproved' => true,
        'window_24h_monitoring' => true,
    ],
];
