<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class UpdateBillingStatusJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public ?int $tenantId = null)
    {
        $this->onQueue('billing');
    }

    public function handle(): void
    {
        DB::table('invoices')
            ->whereIn('status', ['pending', 'sent'])
            ->whereDate('due_date', '<', now('America/Sao_Paulo')->toDateString())
            ->when($this->tenantId, fn ($query) => $query->where('tenant_id', $this->tenantId))
            ->update([
                'status' => 'overdue',
                'updated_at' => now(),
            ]);
    }
}
