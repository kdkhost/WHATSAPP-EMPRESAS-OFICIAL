<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Configuracoes globais (chave/valor com grupo)
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('group', 60)->index(); // app, seo, pwa, maintenance, security, integrations, branding
            $t->string('key', 120);
            $t->longText('value')->nullable();
            $t->string('type', 20)->default('string'); // string, json, bool, int, float, html, image
            $t->timestamps();
            $t->unique(['group', 'key']);
        });

        // Menus dinamicos (frontend e admin) - editaveis via Kanban
        Schema::create('menus', function (Blueprint $t) {
            $t->id();
            $t->string('location', 40)->index(); // header, footer, pwa_footer, sidebar
            $t->string('label');
            $t->string('url')->nullable();
            $t->string('icon', 60)->nullable();
            $t->foreignId('parent_id')->nullable()->constrained('menus')->cascadeOnDelete();
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('active')->default(true);
            $t->json('meta')->nullable(); // target, badge, etc.
            $t->timestamps();
        });

        // Categorias / cards / fotos / modulos genericos (Kanban)
        Schema::create('kanban_boards', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('name');
            $t->string('context', 40); // menus, categorias, fotos, modulos, cards
            $t->timestamps();
        });
        Schema::create('kanban_columns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('board_id')->constrained('kanban_boards')->cascadeOnDelete();
            $t->string('name');
            $t->string('color', 30)->default('primary');
            $t->unsignedInteger('sort')->default(0);
        });
        Schema::create('kanban_cards', function (Blueprint $t) {
            $t->id();
            $t->foreignId('column_id')->constrained('kanban_columns')->cascadeOnDelete();
            $t->string('title');
            $t->longText('content')->nullable();
            $t->string('image')->nullable();
            $t->json('meta')->nullable();
            $t->unsignedInteger('sort')->default(0);
            $t->timestamps();
        });

        // Depoimentos (Google Business)
        Schema::create('testimonials', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('role')->nullable();
            $t->string('avatar')->nullable();
            $t->unsignedTinyInteger('rating')->default(5);
            $t->text('content');
            $t->string('source', 40)->default('manual'); // manual, google
            $t->boolean('active')->default(true);
            $t->unsignedInteger('sort')->default(0);
            $t->timestamps();
        });

        // Popups configuraveis pelo admin
        Schema::create('popups', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('trigger', 20)->default('time'); // time, scroll, exit, click
            $t->unsignedInteger('trigger_value')->default(5);
            $t->string('image')->nullable();
            $t->longText('html')->nullable();
            $t->string('cta_label')->nullable();
            $t->string('cta_url')->nullable();
            $t->date('starts_at')->nullable();
            $t->date('ends_at')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        // Slides / Carrossel
        Schema::create('slides', function (Blueprint $t) {
            $t->id();
            $t->string('title')->nullable();
            $t->string('subtitle')->nullable();
            $t->string('image');
            $t->string('cta_label')->nullable();
            $t->string('cta_url')->nullable();
            $t->string('animation', 30)->default('fade'); // fade, slide, zoom
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slides');
        Schema::dropIfExists('popups');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('kanban_cards');
        Schema::dropIfExists('kanban_columns');
        Schema::dropIfExists('kanban_boards');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('settings');
    }
};
