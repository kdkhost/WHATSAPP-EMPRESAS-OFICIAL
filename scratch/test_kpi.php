<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Invoice;

$invoices = Invoice::where('status', 'paid')->orderBy('id', 'desc')->limit(5)->get();
foreach ($invoices as $inv) {
    echo $inv->number . " | Amount: " . $inv->amount . " | Status: " . $inv->status . "\n";
}
