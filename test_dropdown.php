<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// FAKE ADMIN LOGIN
\Illuminate\Support\Facades\Auth::guard('admin')->loginUsingId(1);

$request = \Illuminate\Http\Request::create('/admin/billing/calendar/customers/search', 'GET', ['q' => '']);
$app->instance('request', $request);

$controller = $app->make(\App\Modules\Billing\Http\Controllers\CalendarController::class);
$response = $controller->searchCustomers($request);

echo "--- SEM BUSCA (q='') ---\n";
print_r(json_decode($response->getContent(), true));

$request2 = \Illuminate\Http\Request::create('/admin/billing/calendar/customers/search', 'GET', ['q' => 'E']);
$app->instance('request', $request2);
$response2 = $controller->searchCustomers($request2);

echo "\n--- COM BUSCA (q='E') ---\n";
print_r(json_decode($response2->getContent(), true));
