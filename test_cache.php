<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Cache;

Cache::put('test_inc', 1, 60);
echo "Initial: " . Cache::get('test_inc') . "\n";
Cache::increment('test_inc');
echo "Incremented: " . Cache::get('test_inc') . "\n";

// Get expiration
$path = storage_path('framework/cache/data/' . substr(sha1('test_inc'), 0, 2) . '/' . substr(sha1('test_inc'), 2, 2) . '/' . sha1('test_inc'));
if (file_exists($path)) {
    $content = file_get_contents($path);
    echo "File Expiration: " . substr($content, 0, 10) . "\n";
    echo "Current Time: " . time() . "\n";
}
