<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

\Illuminate\Support\Facades\Auth::guard('admin')->loginUsingId(1);

$request2 = \Illuminate\Http\Request::create('/admin/billing/calendar/customers/search', 'GET', ['q' => 'Edson']);
$app->instance('request', $request2);
$controller = $app->make(\App\Modules\Billing\Http\Controllers\CalendarController::class);
$response2 = $controller->searchCustomers($request2);
$data2 = json_decode($response2->getContent(), true);
echo "\nTotal returned with query 'Edson': " . count($data2) . "\n";
foreach(array_slice($data2, 0, 5) as $c) {
    echo "- {$c['name']}\n";
}
