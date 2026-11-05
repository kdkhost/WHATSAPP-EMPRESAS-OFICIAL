<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = app(\App\Modules\WhiteLabel\Services\TenantWhiteLabelService::class);
$tenant = \App\Modules\Tenancy\Models\Tenant::first();
if ($tenant) {
    $tenant->custom_domain = 'google.com';
    $tenant->save();
    $res = $service->checkDomain($tenant, request());
    print_r($res);
}
