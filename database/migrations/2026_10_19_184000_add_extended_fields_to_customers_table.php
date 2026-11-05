<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'birth_date')) {
                $table->date('birth_date')->nullable()->index()->after('document');
            }
            if (!Schema::hasColumn('customers', 'cpf')) {
                $table->string('cpf', 20)->nullable()->index()->after('birth_date');
            }
            if (!Schema::hasColumn('customers', 'cnpj')) {
                $table->string('cnpj', 20)->nullable()->index()->after('cpf');
            }
            if (!Schema::hasColumn('customers', 'person_type')) {
                $table->string('person_type', 20)->nullable()->after('cnpj'); // 'Fisica', 'Juridica'
            }
            if (!Schema::hasColumn('customers', 'whatsapp_optin')) {
                $table->boolean('whatsapp_optin')->default(true)->index()->after('whatsapp');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $columns = ['birth_date', 'cpf', 'cnpj', 'person_type', 'whatsapp_optin'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('customers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
