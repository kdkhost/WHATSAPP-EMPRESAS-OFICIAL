<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preload_settings', function (Blueprint $table) {
            $table->id();
            $table->string('style')->default('spinner'); // spinner, pulse, dots, wave, gradient, particles, logo
            $table->string('background_type')->default('solid'); // solid, gradient, image, video
            $table->string('background_color')->default('#0d6efd');
            $table->string('background_gradient_start')->nullable();
            $table->string('background_gradient_end')->nullable();
            $table->string('background_gradient_angle')->default('135');
            $table->string('background_image')->nullable();
            $table->string('background_video')->nullable();
            $table->string('logo')->nullable();
            $table->boolean('show_logo')->default(true);
            $table->boolean('show_text')->default(true);
            $table->string('text')->nullable();
            $table->string('text_color')->default('#ffffff');
            $table->string('spinner_color')->default('#ffffff');
            $table->string('accent_color')->default('#6610f2');
            $table->integer('animation_speed')->default(1000); // ms
            $table->string('font_family')->default('system-ui');
            $table->integer('font_size')->default(16);
            $table->boolean('show_progress_bar')->default(false);
            $table->string('progress_bar_color')->default('#ffffff');
            $table->boolean('blur_background')->default(false);
            $table->integer('blur_amount')->default(10);
            $table->text('custom_css')->nullable();
            $table->text('custom_js')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        // Inserir configuração padrão
        DB::table('preload_settings')->insert([
            'style' => 'spinner',
            'background_type' => 'gradient',
            'background_color' => '#0d6efd',
            'background_gradient_start' => '#0d6efd',
            'background_gradient_end' => '#6610f2',
            'background_gradient_angle' => '135',
            'show_logo' => true,
            'show_text' => true,
            'text' => 'Carregando...',
            'text_color' => '#ffffff',
            'spinner_color' => '#ffffff',
            'accent_color' => '#6610f2',
            'animation_speed' => 1000,
            'font_family' => 'system-ui, -apple-system, sans-serif',
            'font_size' => 16,
            'show_progress_bar' => false,
            'progress_bar_color' => '#ffffff',
            'blur_background' => false,
            'blur_amount' => 10,
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('preload_settings');
    }
};
