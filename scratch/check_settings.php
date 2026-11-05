<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Modules\WhmcsIntegration\Models\WhmcsConnection;

$conn = WhmcsConnection::first();
echo "sync_cancelled_invoices: " . ($conn->sync_cancelled_invoices ? 'true' : 'false') . "\n";
echo "sync_paid_invoices: " . ($conn->sync_paid_invoices ? 'true' : 'false') . "\n";
echo "sync_statuses: " . json_encode($conn->sync_statuses) . "\n";
