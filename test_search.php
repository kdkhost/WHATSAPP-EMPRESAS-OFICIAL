<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

\Illuminate\Support\Facades\Auth::guard('admin')->loginUsingId(1); // FAKE LOGIN AS ADMIN

$request = \Illuminate\Http\Request::create('/admin/billing/calendar/customers/search', 'GET', ['q' => '']);
$app->instance('request', $request);

$controller = new \App\Modules\Billing\Http\Controllers\CalendarController();
$response = $controller->searchCustomers($request);

echo json_encode(json_decode($response->getContent()), JSON_PRETTY_PRINT);
