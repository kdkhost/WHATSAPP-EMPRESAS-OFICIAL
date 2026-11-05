<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanOldLogsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public int $days = 90)
    {
        $this->onQueue('maintenance');
    }

    public function handle(): void
    {
        $limit = now()->subDays(max(7, $this->days));

        foreach ([
            'whatsapp_webhook_logs',
            'whatsapp_sync_logs',
            'whatsapp_customer_service_logs',
            'security_audit_logs',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where('created_at', '<', $limit)->delete();
            }
        }
    }
}
