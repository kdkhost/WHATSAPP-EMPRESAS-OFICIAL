<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

Auth::guard('admin')->loginUsingId(1); // Mestre!

$request = \Illuminate\Http\Request::create('/admin/billing/calendar/customers/search', 'GET', ['q' => '']);
$response = app()->handle($request);
echo "HTTP: " . $response->getStatusCode() . "\n";
echo "Content: \n" . substr($response->getContent(), 0, 500) . "...\n";
