<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (\App\Modules\AaPanel\Models\AaPanelAccount::with('server')->get() as $a) {
    echo "ID: {$a->id} | Domain: {$a->domain} | SiteID: {$a->site_id} | Server: " . ($a->server?->name ?? 'NULL') . " (IP: " . ($a->server?->host ?? 'NULL') . ")\n";
}
