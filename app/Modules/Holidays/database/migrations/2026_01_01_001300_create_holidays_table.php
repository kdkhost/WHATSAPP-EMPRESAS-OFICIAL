<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $t) {
            $t->id();
            $t->date('date')->index();
            $t->string('name', 120);
            $t->enum('type', ['national', 'state', 'municipal', 'custom'])->default('national');
            $t->string('uf', 2)->nullable()->index();   // null = nacional
            $t->boolean('moveable')->default(false);    // pascoa, carnaval, corpus christi
            $t->boolean('active')->default(true);
            $t->boolean('block_billing')->default(true);   // nao cobra
            $t->boolean('block_dispatch')->default(true);  // nao envia
            $t->boolean('block_suspension')->default(true); // nao suspende
            $t->string('color', 7)->default('#dc3545');
            $t->timestamps();
            $t->unique(['date', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
