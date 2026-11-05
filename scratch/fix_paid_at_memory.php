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

$limit = 500;
$offset = 0;
$whmcsPaidDates = [];

while (true) {
    try {
        $response = $client->call('GetInvoices', [
            'status' => 'Paid',
            'limitnum' => $limit,
            'limitstart' => $offset,
            'orderby' => 'datepaid',
            'order' => 'desc',
        ]);
    } catch (\Throwable $e) {
        break;
    }
    
    $invoices = $response['invoices']['invoice'] ?? [];
    if (empty($invoices)) {
        break;
    }
    
    foreach ($invoices as $inv) {
        if (!empty($inv['datepaid']) && $inv['datepaid'] !== '0000-00-00 00:00:00') {
            $whmcsPaidDates[$inv['id']] = \Carbon\Carbon::parse($inv['datepaid'])->format('Y-m-d H:i:s');
        }
    }
    
    if (count($invoices) < $limit) break;
    $offset += $limit;
}

echo "Fetched " . count($whmcsPaidDates) . " paid dates from WHMCS.\n";

$localInvoices = DB::table('invoices')->where('status', 'paid')->get(['id', 'meta']);
$updates = 0;

foreach ($localInvoices as $local) {
    $meta = json_decode($local->meta, true);
    $whmcsId = $meta['whmcs_invoice_id'] ?? null;
    
    if ($whmcsId && isset($whmcsPaidDates[$whmcsId])) {
        $realDate = $whmcsPaidDates[$whmcsId];
        DB::table('invoices')->where('id', $local->id)->update(['paid_at' => $realDate]);
        $updates++;
    }
}

echo "Updated $updates local invoices.\n";
