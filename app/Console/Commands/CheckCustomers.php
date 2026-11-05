<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $total = \App\Models\Customer::count();
    $nullTenant = \App\Models\Customer::whereNull('tenant_id')->count();
    $zeroTenant = \App\Models\Customer::where('tenant_id', 0)->count();
    $activeNull = \App\Models\Customer::whereNull('tenant_id')->where('active', true)->count();
    $activeZero = \App\Models\Customer::where('tenant_id', 0)->where('active', true)->count();
    $activeAll = \App\Models\Customer::where('active', true)->count();

    echo "Total: $total\nNull: $nullTenant\nZero: $zeroTenant\nActiveNull: $activeNull\nActiveZero: $activeZero\nActiveAll: $activeAll\n";
    $first = \App\Models\Customer::first();
    echo "First customer tenant_id: " . ($first ? var_export($first->tenant_id, true) : 'none') . "\n";
    echo "First customer active: " . ($first ? var_export($first->active, true) : 'none') . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
