<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$t = \App\Modules\Tenancy\Models\Tenant::find(1);
echo "Tenant 1 details:\n";
echo json_encode($t, JSON_PRETTY_PRINT);
