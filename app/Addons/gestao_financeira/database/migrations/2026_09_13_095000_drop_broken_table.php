<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('addon_gestao_financeira_open_finance_connections');
    }

    public function down(): void
    {
        //
    }
};
