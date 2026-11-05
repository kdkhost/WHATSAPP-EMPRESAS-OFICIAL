<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$customers = \App\Models\Customer::orderBy('name')->limit(20)->get(['id', 'name', 'email', 'whatsapp', 'document', 'tenant_id', 'active']);
echo json_encode($customers, JSON_PRETTY_PRINT);
