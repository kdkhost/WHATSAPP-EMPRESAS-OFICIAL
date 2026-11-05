<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class Fix2FASeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('ALTER TABLE admin_users MODIFY two_factor_secret TEXT');
        DB::statement('ALTER TABLE admin_users MODIFY two_factor_recovery_codes TEXT');
        echo "Tabelas corrigidas com sucesso!\n";
    }
}
