<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo 'Total: ' . \App\Models\Customer::count() . PHP_EOL;
echo 'Null tenant: ' . \App\Models\Customer::whereNull('tenant_id')->count() . PHP_EOL;
echo 'Zero tenant: ' . \App\Models\Customer::where('tenant_id', 0)->count() . PHP_EOL;
echo 'Active Null: ' . \App\Models\Customer::whereNull('tenant_id')->where('active', true)->count() . PHP_EOL;
echo 'Active Zero: ' . \App\Models\Customer::where('tenant_id', 0)->where('active', true)->count() . PHP_EOL;
echo 'Tenant ID 1: ' . \App\Models\Customer::where('tenant_id', 1)->count() . PHP_EOL;
