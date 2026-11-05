<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$conn = \App\Modules\WhmcsIntegration\Models\WhmcsConnection::find(2);
$client = new \App\Modules\WhmcsIntegration\Services\WhmcsClient($conn);
$data = $client->call('GetClientsDetails', ['clientid' => 1, 'stats' => false]);
echo "GetClientsDetails response keys: \n";
print_r(array_keys($data));
if (isset($data['customfields'])) {
    echo "Has custom fields!\n";
}
