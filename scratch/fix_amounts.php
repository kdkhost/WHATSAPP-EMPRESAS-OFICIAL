<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Modules\WhmcsIntegration\Models\WhmcsConnection;
use App\Modules\WhmcsIntegration\Services\WhmcsClient;

$conn = WhmcsConnection::first();
if ($conn) {
    echo "Running syncInvoices() to fix amounts...\n";
    $client = new WhmcsClient($conn);
    // Explicitly sync Paid invoices just to be sure
    $stats = $client->syncInvoices(['Paid']);
    echo "Sync Result: " . json_encode($stats) . "\n";
}
