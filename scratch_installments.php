<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$installments = \App\Modules\Tenancy\Models\CustomerPackageInstallment::where('status', '!=', 'paid')->get();
echo "Total pending/overdue installments: " . $installments->count() . "\n";

foreach($installments as $inst) {
    $invoice = \App\Models\Invoice::find($inst->invoice_id);
    if (!$invoice) {
        echo "Installment {$inst->id} has no invoice (invoice_id: {$inst->invoice_id})\n";
    } else {
        echo "Installment {$inst->id} invoice status: {$invoice->status}\n";
    }
}
