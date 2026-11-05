<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('aapanel_servers', function (Blueprint $table) {
            $table->string('security_entry')->nullable()->after('host')
                ->comment('Entrada de seguranca do aaPanel (ex: a3c0960da)');
        });
    }

    public function down(): void
    {
        Schema::table('aapanel_servers', function (Blueprint $table) {
            $table->dropColumn('security_entry');
        });
    }
};
