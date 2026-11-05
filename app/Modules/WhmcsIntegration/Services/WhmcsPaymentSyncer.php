<?php

namespace App\Modules\WhmcsIntegration\Services;

use App\Modules\WhmcsIntegration\Models\WhmcsConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhmcsPaymentSyncer
{
    public function pushPaidInvoice(int|object $invoice, string $gateway, ?string $transactionId = null): bool
    {
        $row = is_object($invoice)
            ? DB::table('invoices')->where('id', $invoice->id)->first()
            : DB::table('invoices')->where('id', $invoice)->first();

        if (! $row || $row->status !== 'paid') {
            return false;
        }

        $meta = $this->meta($row->meta ?? null);
        $whmcsInvoiceId = (string) ($meta['whmcs_invoice_id'] ?? '');
        $connectionId = (int) ($meta['whmcs_connection_id'] ?? 0);

        if ($whmcsInvoiceId === '' || $connectionId <= 0) {
            return false;
        }

        if (! empty($meta['whmcs_payment_pushed_at']) || ($meta['whmcs_paid_origin'] ?? null) === 'webhook') {
            return false;
        }

        if (strtolower((string) ($meta['whmcs_status'] ?? '')) === 'paid') {
            return false;
        }

        $connection = WhmcsConnection::where('active', true)->find($connectionId);
        if (! $connection) {
            return false;
        }

        try {
            $ok = (new WhmcsClient($connection))->markPaid(
                $whmcsInvoiceId,
                (float) $row->amount,
                $connection->default_gateway ?: $gateway
            );

            if (! $ok) {
                return false;
            }

            $meta['whmcs_payment_pushed_at'] = now()->toISOString();
            $meta['whmcs_payment_gateway'] = $gateway;
            $meta['whmcs_payment_transaction_id'] = $transactionId;
            $meta['whmcs_status'] = 'Paid';

            DB::table('invoices')->where('id', $row->id)->update([
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);

            app(WhmcsServiceAutomation::class)->unsuspendPaidServicesForInvoice($row->id);

            return true;
        } catch (\Throwable $e) {
            Log::warning('[WHMCS] Falha ao enviar pagamento local para WHMCS', [
                'invoice_id' => $row->id,
                'whmcs_invoice_id' => $whmcsInvoiceId,
                'connection_id' => $connectionId,
                'gateway' => $gateway,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function meta(mixed $meta): array
    {
        if (is_array($meta)) {
            return $meta;
        }

        if (is_string($meta) && $meta !== '') {
            $decoded = json_decode($meta, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
