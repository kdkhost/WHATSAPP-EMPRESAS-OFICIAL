<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Invoice;
use App\Modules\WhmcsIntegration\Models\WhmcsConnection;
use Illuminate\Support\Facades\DB;
use App\Modules\WhmcsIntegration\Services\WhmcsClient;

$connections = WhmcsConnection::where('active', 1)->get();

foreach ($connections as $conn) {
    echo "Processing connection: {$conn->id}\n";
    $client = new WhmcsClient($conn);
    
    $limit = 500;
    $offset = 0;
    
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
            echo "Error fetching from WHMCS: " . $e->getMessage() . "\n";
            break;
        }
        
        $invoices = $response['invoices']['invoice'] ?? [];
        if (empty($invoices)) {
            break;
        }
        
        echo "Fetched " . count($invoices) . " invoices at offset $offset\n";
        
        foreach ($invoices as $inv) {
            $datePaid = $inv['datepaid'] ?? null;
            if ($datePaid && $datePaid !== '0000-00-00 00:00:00') {
                try {
                    $paidAt = \Carbon\Carbon::parse($datePaid)->format('Y-m-d H:i:s');
                    $updated = DB::table('invoices')
                        ->where('meta->whmcs_invoice_id', $inv['id'])
                        ->where('status', 'paid')
                        ->update(['paid_at' => $paidAt]);
                } catch (\Throwable $e) {}
            }
        }
        
        if (count($invoices) < $limit) {
            break;
        }
        $offset += $limit;
    }
}
echo "Done.\n";
