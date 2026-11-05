<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Modules\WhmcsIntegration\Models\WhmcsConnection;

$conn = WhmcsConnection::first();
if ($conn) {
    $conn->update([
        'sync_cancelled_invoices' => false,
        'sync_statuses' => ['Unpaid', 'Paid']
    ]);
    echo "Updated settings. sync_cancelled_invoices is now FALSE.\n";
    echo "New sync_statuses: " . json_encode($conn->syncStatuses()) . "\n";
} else {
    echo "No connection found.\n";
}
