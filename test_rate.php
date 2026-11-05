<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/painel/faturas?page=1', 'GET');
$response = $kernel->handle($request);
echo 'Status: ' . $response->getStatusCode() . "\n";
