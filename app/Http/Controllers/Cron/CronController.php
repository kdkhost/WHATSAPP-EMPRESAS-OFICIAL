<?php

namespace App\Http\Controllers\Cron;

use App\Http\Controllers\Controller;
use App\Services\CronManager;
use Illuminate\Http\JsonResponse;

class CronController extends Controller
{
    public function __construct(protected CronManager $cron) {}

    public function run(): JsonResponse
    {
        $report = $this->cron->runFromExternal();
        return response()->json([
            'ok'        => true,
            'timestamp' => now()->toIso8601String(),
            'jobs'      => $report,
        ]);
    }
}
