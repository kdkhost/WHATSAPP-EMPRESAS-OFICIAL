<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\AaPanel\Models\AaPanelServer;
use App\Modules\AaPanel\Services\AaPanelClient;

$server = AaPanelServer::first();
if (!$server) {
    echo "No server found.\n";
    exit;
}
$client = new AaPanelClient($server);
$test = $client->test();
echo "TEST: " . ($test['ok'] ? "OK" : "FAILED") . "\n";
if ($test['ok']) {
    $sys = $client->getSystemTotal();
    echo "SYS OK: " . ($sys['ok'] ? "YES" : "NO") . "\n";
    echo "SYS DATA:\n";
    print_r($sys['data']);
} else {
    echo "TEST MESSAGE: " . $test['message'] . "\n";
}
