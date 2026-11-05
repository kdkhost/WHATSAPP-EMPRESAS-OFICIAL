<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\WhmcsIntegration\Models\WhmcsConnection;
use App\Modules\WhmcsIntegration\Services\WhmcsClient;

$conn = WhmcsConnection::first();
if (!$conn) {
    echo "No WHMCS connection.\n";
    exit;
}

$conn->update([
    'sync_paid_invoices' => true,
    'sync_cancelled_invoices' => true,
    'sync_statuses' => ['Unpaid', 'Paid', 'Cancelled', 'Refunded'],
]);

echo "Updated connection sync_statuses to include Paid.\n";
echo "New Sync Statuses: " . implode(', ', $conn->syncStatuses()) . "\n";

echo "Running syncAll()...\n";
$client = new WhmcsClient($conn);
$stats = $client->syncAll();
echo "Sync Result: " . json_encode($stats) . "\n";

