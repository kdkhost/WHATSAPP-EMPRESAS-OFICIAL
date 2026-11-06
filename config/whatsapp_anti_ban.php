<?php

return [
    'enabled' => env('WHATSAPP_ANTI_BAN_ENABLED', true),
    'timezone' => env('WHATSAPP_ANTI_BAN_TIMEZONE', 'America/Sao_Paulo'),
    'business_hours' => [
        'enabled' => env('WHATSAPP_BUSINESS_HOURS_ENABLED', true),
        'start' => env('WHATSAPP_BUSINESS_HOURS_START', '08:00'),
        'end' => env('WHATSAPP_BUSINESS_HOURS_END', '20:00'),
    ],
    'limits' => [
        'per_minute' => (int) env('WHATSAPP_LIMIT_PER_MINUTE', 2),
        'per_hour' => (int) env('WHATSAPP_LIMIT_PER_HOUR', 35),
        'per_day' => (int) env('WHATSAPP_LIMIT_PER_DAY', 250),
        'per_contact' => (int) env('WHATSAPP_LIMIT_PER_CONTACT', 5),
    ],
    'tenant_settings' => [
        'allow_custom_limits' => env('WHATSAPP_TENANT_ALLOW_CUSTOM_LIMITS', true),
        'max_limit_per_day' => (int) env('WHATSAPP_TENANT_MAX_LIMIT_PER_DAY', 500),
        'max_limit_per_minute' => (int) env('WHATSAPP_TENANT_MAX_LIMIT_PER_MINUTE', 6),
        'max_limit_per_hour' => (int) env('WHATSAPP_TENANT_MAX_LIMIT_PER_HOUR', 90),
    ],
    'pause_on_error_count' => (int) env('WHATSAPP_PAUSE_ON_ERROR_COUNT', 5),
    'validate_number_before_send' => env('WHATSAPP_VALIDATE_NUMBER_BEFORE_SEND', false),
    'respect_optout' => env('WHATSAPP_RESPECT_OPTOUT', true),
    'type_limits' => [
        'text' => (int) env('WHATSAPP_TEXT_LIMIT_PER_HOUR', 35),
        'image' => (int) env('WHATSAPP_IMAGE_LIMIT_PER_HOUR', 20),
        'audio' => (int) env('WHATSAPP_AUDIO_LIMIT_PER_HOUR', 15),
        'video' => (int) env('WHATSAPP_VIDEO_LIMIT_PER_HOUR', 10),
        'document' => (int) env('WHATSAPP_DOCUMENT_LIMIT_PER_HOUR', 15),
        'buttons' => (int) env('WHATSAPP_BUTTONS_LIMIT_PER_HOUR', 20),
        'list' => (int) env('WHATSAPP_LIST_LIMIT_PER_HOUR', 20),
        'poll' => (int) env('WHATSAPP_POLL_LIMIT_PER_HOUR', 10),
    ],
    'recipient_cooldown_minutes' => (int) env('WHATSAPP_RECIPIENT_COOLDOWN_MINUTES', 30),
    'human_delay' => [
        'enabled' => env('WHATSAPP_HUMAN_DELAY_ENABLED', true),
        'min_seconds' => (int) env('WHATSAPP_HUMAN_DELAY_MIN_SECONDS', 12),
        'max_seconds' => (int) env('WHATSAPP_HUMAN_DELAY_MAX_SECONDS', 32),
    ],
    'send_delay_ms' => [
        'min' => (int) env('WHATSAPP_SEND_DELAY_MIN_MS', 1500),
        'max' => (int) env('WHATSAPP_SEND_DELAY_MAX_MS', 4500),
    ],
    'release_when_limited_seconds' => (int) env('WHATSAPP_RELEASE_WHEN_LIMITED_SECONDS', 300),
    'cooldowns' => [
        'message_minutes' => (int) env('WHATSAPP_MESSAGE_COOLDOWN_MINUTES', 45),
        'campaign_minutes' => (int) env('WHATSAPP_CAMPAIGN_COOLDOWN_MINUTES', 90),
        'media_minutes' => (int) env('WHATSAPP_MEDIA_COOLDOWN_MINUTES', 20),
    ],
    'block_groups' => env('WHATSAPP_BLOCK_GROUP_SENDS', true),
    'minimum_message_chars' => (int) env('WHATSAPP_MINIMUM_MESSAGE_CHARS', 2),
    'stop_keywords' => array_values(array_filter(array_map(
        static fn (string $keyword) => trim(mb_strtolower($keyword)),
        explode(',', (string) env('WHATSAPP_STOP_KEYWORDS', 'stop,parar,cancelar,sair,remover,nao quero,descadastrar'))
    ))),
    'risk_thresholds' => [
        'medium' => (int) env('WHATSAPP_RISK_MEDIUM', 21),
        'high' => (int) env('WHATSAPP_RISK_HIGH', 51),
        'critical' => (int) env('WHATSAPP_RISK_CRITICAL', 81),
        'block_above' => (int) env('WHATSAPP_RISK_BLOCK_ABOVE', 95),
    ],
    'warmup' => [
        'enabled' => env('WHATSAPP_WARMUP_ENABLED', true),
        'days' => (int) env('WHATSAPP_WARMUP_DAYS', 30),
        'daily_limits' => [
            1 => (int) env('WHATSAPP_WARMUP_LIMIT_DAY_1', 15),
            2 => (int) env('WHATSAPP_WARMUP_LIMIT_DAY_2', 25),
            3 => (int) env('WHATSAPP_WARMUP_LIMIT_DAY_3', 40),
            7 => (int) env('WHATSAPP_WARMUP_LIMIT_DAYS_4_7', 60),
            15 => (int) env('WHATSAPP_WARMUP_LIMIT_DAYS_8_15', 100),
            30 => (int) env('WHATSAPP_WARMUP_LIMIT_DAYS_16_30', 180),
        ],
    ],
    'security' => [
        'webhook_secret_required' => env('WHATSAPP_WEBHOOK_SECRET_REQUIRED', true),
        'webhook_signature_required' => env('WHATSAPP_WEBHOOK_SIGNATURE_REQUIRED', true),
        'webhook_timestamp_required' => env('WHATSAPP_WEBHOOK_TIMESTAMP_REQUIRED', true),
        'webhook_max_age_seconds' => (int) env('WHATSAPP_WEBHOOK_MAX_AGE_SECONDS', 300),
        'api_rate_limit_per_minute' => (int) env('WHATSAPP_API_RATE_LIMIT_PER_MINUTE', 30),
    ],
    'alerts' => [
        'daily_70pct' => 70,
        'daily_85pct' => 85,
        'daily_95pct' => 95,
        'failure_threshold' => 20,
        'critical_risk_threshold' => 80,
    ],
    'repeat_detection' => [
        'identical_window_minutes' => 30,
        'similar_window_minutes' => 60,
        'max_identical_per_window' => 5,
        'cooldown_template_minutes' => 120,
        'cooldown_campaign_minutes' => 180,
    ],
];
