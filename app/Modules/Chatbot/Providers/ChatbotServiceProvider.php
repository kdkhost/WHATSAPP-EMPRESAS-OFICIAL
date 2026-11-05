<?php

namespace App\Modules\Chatbot\Providers;

use App\Modules\Chatbot\Services\ChatbotEngine;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ChatbotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChatbotEngine::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(app_path('Modules/Chatbot/Resources/views'), 'chatbot');

        // Webhook que recebe mensagens da Evolution API — throttle para evitar flood
        Route::middleware(['web', 'throttle:120,1'])->post('/webhooks/evolution/messages/{event?}',
            [\App\Modules\Chatbot\Http\Controllers\ChatbotWebhookController::class, 'handle']
        )->name('chatbot.webhook');

        // Admin - configuracao do chatbot
        Route::middleware(['web', 'admin', '2fa'])->prefix('admin/chatbot')->name('admin.chatbot.')->group(function () {
            $ctrl = \App\Modules\Chatbot\Http\Controllers\ChatbotAdminController::class;
            Route::get('/',             [$ctrl, 'index'])->name('index');
            Route::post('/settings',    [$ctrl, 'saveSettings'])->name('settings');
            Route::get('/interactions', [$ctrl, 'interactions'])->name('interactions');
        });
    }
}
