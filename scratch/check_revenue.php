<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$today = today()->toDateString();
$invoices = \Illuminate\Support\Facades\DB::table('invoices')
    ->where('status', 'paid')
    ->whereDate('paid_at', $today)
    ->orderByDesc('amount')
    ->take(10)
    ->get(['id', 'number', 'amount', 'paid_at', 'updated_at']);

foreach($invoices as $inv) {
    echo "ID: {$inv->id} | Num: {$inv->number} | Amt: {$inv->amount} | PaidAt: {$inv->paid_at} | UpdatedAt: {$inv->updated_at}\n";
}
