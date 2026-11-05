<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: application/json');
try {
    echo json_encode([
        'database' => config('database.connections.mysql.database'),
        'username' => config('database.connections.mysql.username'),
        'host' => config('database.connections.mysql.host'),
        'plans_count' => Illuminate\Support\Facades\DB::table('plans')->count(),
        'plans_with_true' => Illuminate\Support\Facades\DB::table('plans')->where(['active' => true])->get()->toArray(),
        'plans_with_1' => Illuminate\Support\Facades\DB::table('plans')->where(['active' => 1])->get()->toArray(),
    ]);
} catch (\Throwable $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
}
