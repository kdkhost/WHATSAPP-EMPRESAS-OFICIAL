<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\WhmcsIntegration\Models\WhmcsConnection;

$conn = WhmcsConnection::first();
echo "Sync Statuses: " . implode(', ', $conn->syncStatuses()) . "\n";
