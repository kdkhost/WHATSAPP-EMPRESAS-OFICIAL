<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Invoice;
use App\Modules\WhmcsIntegration\Models\WhmcsConnection;
use Illuminate\Support\Facades\DB;
use App\Modules\WhmcsIntegration\Services\WhmcsClient;

$conn = WhmcsConnection::where('active', 1)->first();
$client = new WhmcsClient($conn);

$response = $client->call('GetInvoices', [
    'status' => 'Paid',
    'limitnum' => 5,
    'limitstart' => 0,
    'orderby' => 'datepaid',
    'order' => 'desc',
]);

print_r($response['invoices']['invoice'][0] ?? null);
