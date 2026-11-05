<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Modules\WhmcsIntegration\Models\WhmcsConnection;
use App\Modules\WhmcsIntegration\Services\WhmcsClient;

echo "--- CONNECTION ---\n";
$conn = WhmcsConnection::first();
if (!$conn) {
    echo "No WHMCS connection.\n";
    exit;
}

echo "\n--- TESTING SYNC OF LAST CLIENT ---\n";
$client = new WhmcsClient($conn);
try {
    $data = $client->call('GetClients', ['limitstart' => 0, 'limitnum' => 1, 'orderby' => 'id', 'sorting' => 'DESC']);
    $clients = $data['clients']['client'] ?? [];
    if (!empty($clients)) {
        $lastClient = $clients[0];
        echo "Last WHMCS Client ID: " . $lastClient['id'] . " - " . $lastClient['firstname'] . "\n";
        
        $customer = DB::table('customers')->where('meta->whmcs_id', $lastClient['id'])->first();
        if ($customer) {
            echo "Customer IS in local DB. ID: " . $customer->id . "\n";
            $invoices = DB::table('invoices')->where('customer_id', $customer->id)->get();
            echo "Local Invoices count for this customer: " . count($invoices) . "\n";
            foreach($invoices as $inv) {
                echo " - " . $inv->number . " | Status: " . $inv->status . " | Amount: " . $inv->amount . "\n";
            }
            
            // fetch invoices from whmcs for this client
            $invData = $client->call('GetInvoices', ['userid' => $lastClient['id'], 'limitnum' => 10]);
            $whmcsInvoices = $invData['invoices']['invoice'] ?? [];
            echo "WHMCS Invoices count for this customer: " . count($whmcsInvoices) . "\n";
            foreach($whmcsInvoices as $inv) {
                echo " - ID: " . $inv['id'] . " | Status: " . $inv['status'] . " | Total: " . $inv['total'] . "\n";
            }
        } else {
            echo "Customer IS NOT in local DB.\n";
            echo "Attempting to sync this customer specifically...\n";
            $res = $client->syncCustomerDetails($lastClient['id']);
            echo "Sync result: " . ($res ? "Success (ID: {$res->id})" : "Failed") . "\n";
        }
    } else {
        echo "No clients found in WHMCS.\n";
    }
} catch (\Throwable $e) {
    echo "Error calling GetClients: " . $e->getMessage() . "\n";
}
