<?php

namespace App\Modules\WhmcsIntegration\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Modules\Billing\Services\InvoicePaymentService;
use App\Modules\Holidays\Services\HolidayService;
use App\Modules\WhmcsIntegration\Models\WhmcsConnection;
use App\Modules\WhmcsIntegration\Models\WhmcsService;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhmcsClient
{
    public function __construct(protected WhmcsConnection $connection) {}

    public function call(string $action, array $params = []): array
    {
        $startedAt = microtime(true);
        $payload = $this->payload($action, $params, 'json');

        try {
            $response = $this->send($payload);

            if (! $response->successful()) {
                throw new \RuntimeException("HTTP {$response->status()} ao chamar WHMCS.");
            }

            $data = $this->decodeJson($response->body());

            if ($this->shouldRetryAsXml($data)) {
                $data = $this->callXml($action, $params);
            }

            $this->assertSuccess($data);

            $this->log('pull', 'api', null, null, 'ok', $action, [
                'action' => $action,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'response_type' => ($data['_response_type'] ?? 'json'),
            ]);

            unset($data['_response_type']);

            return $data;
        } catch (\Throwable $e) {
            $message = "{$action}: ".$e->getMessage();
            Log::warning('[WHMCS] Falha em chamada API', [
                'connection_id' => $this->connection->id,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
            $this->rememberError($message);
            $this->log('pull', 'api', null, null, 'error', $message, [
                'action' => $action,
                'params' => $this->sanitizePayload($params),
            ]);

            throw $e;
        }
    }

    protected function callXml(string $action, array $params = []): array
    {
        $payload = $this->payload($action, $params, 'xml');
        $response = $this->send($payload);

        if (! $response->successful()) {
            throw new \RuntimeException("HTTP {$response->status()} ao chamar WHMCS em XML.");
        }

        $data = $this->decodeXml($response->body());
        $this->assertSuccess($data);
        $data['_response_type'] = 'xml';

        return $data;
    }

    protected function payload(string $action, array $params, string $responseType): array
    {
        return array_merge([
            'identifier' => $this->connection->identifier,
            'secret' => $this->connection->secret,
            'action' => $action,
            'responsetype' => $responseType,
        ], $params, ['responsetype' => $responseType]);
    }

    protected function send(array $payload)
    {
        return Http::asForm()
            ->connectTimeout(10)
            ->timeout(35)
            ->retry(2, 350, throw: false)
            ->post($this->connection->api_url, $payload);
    }

    protected function decodeJson(string $body): array
    {
        $data = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $this->normalizeEncoding($data);
        }

        $normalized = $this->normalizeEncoding($body);
        $data = json_decode($normalized, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $this->normalizeEncoding($data);
        }

        return [
            'result' => 'error',
            'message' => 'Resposta JSON invalida do WHMCS: '.json_last_error_msg(),
            '_decode_error' => true,
        ];
    }

    protected function decodeXml(string $body): array
    {
        if (! function_exists('simplexml_load_string')) {
            throw new \RuntimeException('Extensao SimpleXML indisponivel para fallback XML do WHMCS.');
        }

        $body = $this->scrubXml($body);
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NOCDATA);

        if (! $xml) {
            $body = $this->scrubXml($this->normalizeEncoding($body));
            $body = preg_replace('/encoding=["\'][^"\']+["\']/i', 'encoding="UTF-8"', $body);
            $xml = simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NOCDATA);
        }

        libxml_use_internal_errors($previous);

        if (! $xml) {
            throw new \RuntimeException('Resposta XML invalida do WHMCS.');
        }

        $data = json_decode(json_encode($xml, JSON_INVALID_UTF8_SUBSTITUTE), true) ?: [];

        return $this->normalizeEncoding($data);
    }

    protected function scrubXml(string $body): string
    {
        foreach (['password', 'secret', 'api_token', 'token', 'webhook_secret'] as $tag) {
            $body = preg_replace('/<'.$tag.'\b[^>]*>.*?<\/'.$tag.'>/is', '<'.$tag.'></'.$tag.'>', $body) ?? $body;
        }

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $body) ?? $body;
    }

    protected function assertSuccess(array $data): void
    {
        if (($data['result'] ?? '') !== 'success') {
            throw new \RuntimeException('WHMCS API: '.($data['message'] ?? 'erro desconhecido'));
        }
    }

    protected function shouldRetryAsXml(array $data): bool
    {
        $message = Str::lower((string) ($data['message'] ?? ''));

        return ($data['_decode_error'] ?? false)
            || str_contains($message, 'malformed utf-8')
            || str_contains($message, 'json encoded response')
            || str_contains($message, 'incorrectly encoded');
    }

    public function testConnection(): bool
    {
        return $this->validateCredentials()['ok'];
    }

    public function validateCredentials(): array
    {
        try {
            $data = $this->call('GetActivityLog', ['limitnum' => 1]);
            $this->rememberTest('ok');

            return [
                'ok' => true,
                'message' => 'Credenciais validadas com sucesso.',
                'raw' => Arr::except($data, ['activity']),
            ];
        } catch (\Throwable $e) {
            $this->rememberTest('error', $e->getMessage());

            return [
                'ok' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function syncAll(): array
    {
        $stats = [
            'customers' => ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0],
            'invoices' => ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0],
            'services' => ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0],
        ];

        try {
            if ($this->connection->sync_customers) {
                $stats['customers'] = $this->syncCustomers();
            }

            if ($this->connection->sync_invoices) {
                $stats['invoices'] = $this->syncInvoices();
            }

            if ($this->connection->sync_services) {
                $stats['services'] = $this->syncServices();
            }

            $this->connection->forceFill([
                'last_sync_at' => now(),
                'last_sync_status' => $this->hasErrors($stats) ? 'partial' : 'ok',
                'last_sync_error' => null,
                'last_error' => null,
                'last_sync_stats' => $stats,
            ])->save();
        } catch (\Throwable $e) {
            $this->connection->forceFill([
                'last_sync_at' => now(),
                'last_sync_status' => 'error',
                'last_sync_error' => $e->getMessage(),
                'last_error' => $e->getMessage(),
                'last_sync_stats' => $stats,
            ])->save();

            throw $e;
        }

        return $stats;
    }

    public function syncCustomers(int $limit = 100): array
    {
        $stats = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $offset = 0;

        do {
            $data = $this->call('GetClients', [
                'limitstart' => $offset,
                'limitnum'   => $limit,
                'sorting'    => 'DESC', // GetClients uses sorting
            ]);

            $clients = $this->normalizeRows($data['clients']['client'] ?? []);

            if (empty($clients)) {
                break;
            }

            $whmcsIds = array_filter(array_map(fn ($c) => (string) ($c['id'] ?? $c['userid'] ?? ''), $clients));

            $existingCustomers = Customer::query()
                ->when($this->connection->tenant_id, fn ($q) => $q->where('tenant_id', $this->connection->tenant_id))
                ->where(function ($q) use ($whmcsIds) {
                    foreach ($whmcsIds as $wid) {
                        $q->orWhere('meta->whmcs_id', $wid)
                          ->orWhere('meta->whmcs_id', (int) $wid);
                    }
                })
                ->get()
                ->keyBy(function ($c) {
                    return (string) ($c->meta['whmcs_id'] ?? '');
                });

            foreach ($clients as $client) {
                $whmcsClientId = (string) ($client['id'] ?? $client['userid'] ?? '');
                if ($whmcsClientId === '') {
                    $stats['skipped']++;
                    continue;
                }

                try {
                    // Busca os detalhes completos do WHMCS para mapear todos os customfields (Nascimento, CPF, CNPJ, WhatsApp Opt-in)
                    $customer = $this->syncCustomerDetails($whmcsClientId, $stats);
                    if (! $customer) {
                        $this->upsertCustomer($client, $stats);
                    }
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    $this->log('pull', 'customer', $whmcsClientId, null, 'error', $e->getMessage());
                }
            }

            $totalResults = (int) ($data['totalresults'] ?? count($clients));
            $offset += count($clients);
        } while (count($clients) === $limit && $offset < $totalResults);

        return $stats;
    }

    public function syncInvoices(?array $statuses = null, int $limit = 100): array
    {
        $stats = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $statuses = $statuses ?: $this->connection->syncStatuses();

        foreach ($statuses as $status) {
            $offset = 0;

            do {
                $data = $this->call('GetInvoices', [
                    'status' => $status,
                    'limitstart' => $offset,
                    'limitnum' => $limit,
                    'orderby' => 'id',
                    'order' => 'desc',
                ]);

                $invoices = $this->normalizeRows($data['invoices']['invoice'] ?? []);

                foreach ($invoices as $invoice) {
                    $this->upsertInvoice($invoice, $stats);
                }

                $totalResults = (int) ($data['totalresults'] ?? count($invoices));
                $offset += count($invoices);
                
                // Limita sync de faturas antigas para nao travar o cron com historico completo
                if (in_array($status, ['Paid', 'Cancelled', 'Refunded']) && $offset >= 500) {
                    break;
                }
            } while (count($invoices) === $limit && $offset < $totalResults);
        }

        return $stats;
    }

    public function syncServices(?array $statuses = null, int $limit = 100): array
    {
        $stats = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $statuses = $statuses ?: $this->connection->syncServiceStatuses();

        foreach ($statuses as $status) {
            $offset = 0;

            do {
                $data = $this->call('GetClientsProducts', [
                    'status' => $status,
                    'limitstart' => $offset,
                    'limitnum' => $limit,
                ]);

                $services = $this->normalizeRows($data['products']['product'] ?? []);

                foreach ($services as $service) {
                    $this->upsertService($service, $stats);
                }

                $totalResults = (int) ($data['totalresults'] ?? count($services));
                $offset += count($services);
            } while (count($services) === $limit && $offset < $totalResults);
        }

        return $stats;
    }

    public function syncSingleInvoice(string|int $invoiceId): array
    {
        $stats = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $invoice = $this->call('GetInvoice', ['invoiceid' => $invoiceId]);

        if (empty($invoice) || ($invoice['result'] ?? '') === 'error') {
            // Fallback para GetInvoices se GetInvoice falhar
            $data = $this->call('GetInvoices', ['invoiceid' => $invoiceId, 'limitnum' => 1]);
            $invoice = $this->normalizeRows($data['invoices']['invoice'] ?? [])[0] ?? null;
        }

        if (! $invoice) {
            $stats['skipped']++;
            $this->log('pull', 'invoice', $invoiceId, null, 'skipped', 'Fatura nao encontrada no WHMCS.');
            return $stats;
        }

        $this->upsertInvoice($invoice, $stats);

        return $stats;
    }

    public function syncSingleService(string|int $serviceId): ?WhmcsService
    {
        $stats = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
        $data = $this->call('GetClientsProducts', ['serviceid' => $serviceId, 'limitnum' => 1]);
        $service = $this->normalizeRows($data['products']['product'] ?? [])[0] ?? null;

        return $service ? $this->upsertService($service, $stats) : null;
    }

    public function markPaid(string $whmcsInvoiceId, float $amount, string $gateway = 'mercadopago'): bool
    {
        try {
            $this->call('AddInvoicePayment', [
                'invoiceid' => $whmcsInvoiceId,
                'amount' => $amount,
                'gateway' => $gateway,
                'date' => now()->format('Y-m-d H:i:s'),
            ]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function suspendService(int $serviceId, string $reason = ''): bool
    {
        try {
            $this->call('ModuleSuspend', ['serviceid' => $serviceId, 'suspendreason' => $reason]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function unsuspendService(int $serviceId): bool
    {
        try {
            $this->call('ModuleUnsuspend', ['serviceid' => $serviceId]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function upsertCustomer(array $client, array &$stats): ?Customer
    {
        try {
            $client = $this->withClientDetailsWhenNeeded($client);
            $whmcsId = (string) ($client['id'] ?? $client['userid'] ?? '');
            if ($whmcsId === '') {
                $stats['skipped']++;
                return null;
            }

            $email = strtolower(trim((string) ($client['email'] ?? '')));
            
            // Extração avançada de campos personalizados (CPF, CNPJ, Nascimento, Tipo Pessoa, Opt-in WhatsApp)
            $customData = $this->extractWhmcsCustomFields($client);
            $document = $customData['document'] ?: $this->extractDocument($client);
            $cpf = $customData['cpf'];
            $cnpj = $customData['cnpj'];
            $birthDate = $customData['birth_date'];
            $personType = $customData['person_type'];
            $whatsappOptin = $customData['whatsapp_optin'];

            $extractedPhones = $this->extractPhones($client);
            $whatsapp = $extractedPhones['whatsapp'];
            $phone = $extractedPhones['phone'];
            
            $name = trim((string) ($client['fullname'] ?? trim(($client['firstname'] ?? '').' '.($client['lastname'] ?? ''))));
            $name = $name !== '' ? $name : 'Cliente WHMCS #'.$whmcsId;

            // 1. Busca primeiro pelo whmcs_id dentro do mesmo tenant_id da conexão
            $customer = Customer::query()
                ->when($this->connection->tenant_id, fn ($q) => $q->where('tenant_id', $this->connection->tenant_id))
                ->where(function ($q) use ($whmcsId) {
                    $q->where('meta->whmcs_id', (string) $whmcsId)
                        ->orWhere('meta->whmcs_id', (int) $whmcsId);
                })
                ->first();

            // 2. Se não encontrou no tenant, busca na base geral (onde tenant_id IS NULL) para reaproveitar clientes pré-existentes
            if (! $customer) {
                $customer = Customer::query()
                    ->where(function ($q) use ($whmcsId) {
                        $q->where('meta->whmcs_id', (string) $whmcsId)
                            ->orWhere('meta->whmcs_id', (int) $whmcsId);
                    })
                    ->first();
            }

            // 3. Se ainda não encontrou, tenta por e-mail ou documento
            if (! $customer && ($email !== '' || $document)) {
                $customer = Customer::query()
                    ->where(function ($q) use ($email, $document) {
                        if ($email !== '') {
                            $q->orWhere('email', $email);
                        }
                        if ($document) {
                            $q->orWhere('document', $document)
                              ->orWhere('cpf', $document)
                              ->orWhere('cnpj', $document);
                        }
                    })
                    ->first();
            }

            // Preservação de dados locais se o WHMCS vier vazio:
            if (empty($document) && !empty($customer?->document)) {
                $document = $customer->document;
            }
            if (empty($cpf) && !empty($customer?->cpf)) {
                $cpf = $customer->cpf;
            }
            if (empty($cnpj) && !empty($customer?->cnpj)) {
                $cnpj = $customer->cnpj;
            }
            if (empty($birthDate) && !empty($customer?->birth_date)) {
                $birthDate = $customer->birth_date;
            }
            if (empty($personType) && !empty($customer?->person_type)) {
                $personType = $customer->person_type;
            }
            if (empty($phone) && !empty($customer?->phone)) {
                $phone = $customer->phone;
            }
            if (empty($whatsapp) && !empty($customer?->whatsapp)) {
                $whatsapp = $customer->whatsapp;
            }

            $cleanCity = $this->cleanText($client['city'] ?? null);
            if (empty($cleanCity) && !empty($customer?->cidade)) {
                $cleanCity = $customer->cidade;
            }

            $cleanState = $this->normalizeState($client['state'] ?? null);
            if (empty($cleanState) && !empty($customer?->uf)) {
                $cleanState = $customer->uf;
            }

            $cleanAddress1 = $this->cleanText($client['address1'] ?? null);
            if (empty($cleanAddress1) && !empty($customer?->logradouro)) {
                $cleanAddress1 = $customer->logradouro;
            }

            $cleanAddress2 = $this->cleanText($client['address2'] ?? null);
            if (empty($cleanAddress2) && !empty($customer?->complemento)) {
                $cleanAddress2 = $customer->complemento;
            }

            $cleanPostcode = $this->normalizeCep($client['postcode'] ?? null);
            if (empty($cleanPostcode) && !empty($customer?->cep)) {
                $cleanPostcode = $customer->cep;
            }

            $meta = array_merge($customer?->meta ?: [], [
                'whmcs_id' => $whmcsId,
                'whmcs_connection_id' => $this->connection->id,
                'whmcs_status' => $client['status'] ?? null,
                'whmcs_synced_at' => now()->toISOString(),
                'all_phones' => $extractedPhones['all'],
                'birth_date' => $birthDate ? (string) $birthDate : null,
                'cpf' => $cpf,
                'cnpj' => $cnpj,
                'person_type' => $personType,
                'whatsapp_optin' => $whatsappOptin,
                'whmcs_custom_fields' => $customData['raw'],
            ]);

            $targetTenantId = $this->connection->tenant_id ?: ($customer?->tenant_id ?: null);

            $payload = [
                'tenant_id' => $targetTenantId,
                'name' => $name,
                'email' => $email ?: null,
                'document' => $document,
                'cpf' => $cpf,
                'cnpj' => $cnpj,
                'birth_date' => $birthDate,
                'person_type' => $personType,
                'whatsapp_optin' => $whatsappOptin,
                'phone' => $phone,
                'whatsapp' => $whatsapp,
                'cep' => $cleanPostcode,
                'logradouro' => $cleanAddress1,
                'complemento' => $cleanAddress2,
                'cidade' => $cleanCity,
                'uf' => $cleanState,
                'meta' => $meta,
                'active' => ($client['status'] ?? 'Active') !== 'Inactive',
            ];

            if ($customer) {
                $updates = [];
                foreach ($payload as $key => $val) {
                    if (in_array($key, ['meta', 'active', 'tenant_id'])) {
                        $updates[$key] = $val;
                    } elseif ($val !== null && $val !== '') {
                        if ($customer->{$key} !== $val) {
                            $updates[$key] = $val;
                        }
                    } elseif (empty($customer->{$key})) {
                        $updates[$key] = $val;
                    }
                }
                
                if (!empty($updates)) {
                    $customer->update($updates);
                    $stats['updated']++;
                } else {
                    $stats['skipped']++;
                }
            } else {
                $customer = Customer::create($payload);
                $stats['imported']++;
            }

            // Sincronização Reversa: Se o cliente local tiver dados (CPF/CNPJ/Nascimento) e o WHMCS estiver com eles vazios, envia para o WHMCS!
            $needsPushToWhmcs = false;
            if (!empty($cpf) && empty($customData['cpf'])) $needsPushToWhmcs = true;
            if (!empty($cnpj) && empty($customData['cnpj'])) $needsPushToWhmcs = true;
            if (!empty($birthDate) && empty($customData['birth_date'])) $needsPushToWhmcs = true;
            if (!empty($cleanCity) && empty($client['city'])) $needsPushToWhmcs = true;
            if (!empty($cleanState) && empty($client['state'])) $needsPushToWhmcs = true;

            if ($needsPushToWhmcs && $customer) {
                try {
                    $this->pushCustomerToWhmcs($customer);
                } catch (\Throwable) {}
            }

            // Se o cliente marcou que NÃO quer receber mensagens WhatsApp, registra opt-out no módulo de segurança
            if ($whatsappOptin === false && $customer && $whatsapp) {
                try {
                    app(\App\Modules\WhatsAppIntegration\Services\WhatsAppSafetyService::class)->markOptOut(
                        $whatsapp,
                        'whmcs_customfield_opt_out'
                    );
                } catch (\Throwable) {}
            }

            $this->log('pull', 'customer', $whmcsId, $customer->id, 'ok');

            return $customer;
        } catch (\Throwable $e) {
            $stats['errors']++;
            $this->log('pull', 'customer', $client['id'] ?? null, null, 'error', $e->getMessage(), $client);
            return null;
        }
    }

    protected function withClientDetailsWhenNeeded(array $client): array
    {
        // Se já possui os customfields carregados, não refaz chamada
        if (!empty($client['customfields']) || !empty($client['customfields1']) || !empty($client['client']['customfields'])) {
            return $client;
        }

        $whmcsId = $this->scalarString($client['id'] ?? $client['userid'] ?? null);
        if (! $whmcsId) {
            return $client;
        }

        try {
            $details = $this->call('GetClientsDetails', [
                'clientid' => $whmcsId,
                'stats' => false,
            ]);

            return array_replace($client, $details);
        } catch (\Throwable $e) {
            $this->log('pull', 'customer_details', $whmcsId, null, 'error', $e->getMessage());

            return $client;
        }
    }

    /**
     * Envia os dados e campos personalizados do cliente para o WHMCS via API UpdateClient.
     * Mantém ambos os sistemas sincronizados e idênticos.
     */
    public function pushCustomerToWhmcs(Customer $customer): bool
    {
        $whmcsId = $customer->meta['whmcs_id'] ?? null;
        if (! $whmcsId) {
            return false;
        }

        try {
            $customFields = [];
            if ($customer->person_type) {
                $customFields[1] = $customer->person_type === 'PJ' || strtolower((string)$customer->person_type) === 'juridica'
                    ? 'Pessoa Jurídica'
                    : 'Pessoa Física';
            }
            if ($customer->cpf) {
                $customFields[2] = $customer->formatted_cpf ?: $customer->cpf;
            }
            if ($customer->birth_date) {
                $customFields[3] = $customer->formatted_birth_date ?: (string) $customer->birth_date;
            }
            if ($customer->cnpj) {
                $customFields[4] = $customer->formatted_cnpj ?: $customer->cnpj;
            }
            if ($customer->whatsapp_optin !== null) {
                $customFields[13] = $customer->whatsapp_optin ? 'Sim' : 'Não';
            }

            $params = [
                'clientid' => (int) $whmcsId,
            ];

            if (!empty($customer->name)) {
                $parts = explode(' ', trim($customer->name), 2);
                $params['firstname'] = $parts[0];
                if (!empty($parts[1])) {
                    $params['lastname'] = $parts[1];
                }
            }
            if (!empty($customer->email)) {
                $params['email'] = $customer->email;
            }
            if (!empty($customer->cidade)) {
                $params['city'] = $customer->cidade;
            }
            if (!empty($customer->uf)) {
                $params['state'] = $customer->uf;
            }
            if (!empty($customer->logradouro)) {
                $params['address1'] = $customer->logradouro . ($customer->numero ? ', ' . $customer->numero : '');
            }
            if (!empty($customer->complemento)) {
                $params['address2'] = $customer->complemento;
            }
            if (!empty($customer->cep)) {
                $params['postcode'] = $customer->cep;
            }
            if (!empty($customer->phone) || !empty($customer->whatsapp)) {
                $params['phonenumber'] = preg_replace('/\D+/', '', $customer->whatsapp ?: $customer->phone);
            }

            if (!empty($customFields)) {
                $params['customfields'] = base64_encode(serialize($customFields));
            }

            $res = $this->call('UpdateClient', $params);
            $success = ($res['result'] ?? '') === 'success';

            if ($success) {
                $this->log('push', 'customer', $whmcsId, $customer->id, 'ok', 'Dados e campos personalizados sincronizados no WHMCS.');
            }

            return $success;
        } catch (\Throwable $e) {
            $this->log('push', 'customer', $whmcsId, $customer->id, 'error', $e->getMessage());
            return false;
        }
    }

    protected function upsertInvoice(array $invoice, array &$stats): ?Invoice
    {
        try {
            $whmcsId = (string) ($invoice['id'] ?? $invoice['invoiceid'] ?? '');
            $whmcsUserId = (string) ($invoice['userid'] ?? '');

            if ($whmcsId === '' || $whmcsUserId === '') {
                $stats['skipped']++;
                return null;
            }

            $customer = $this->findCustomerByWhmcsId($whmcsUserId);

            if (! $customer) {
                $customer = $this->syncCustomerDetails($whmcsUserId);
            }

            if (! $customer) {
                $stats['skipped']++;
                $this->log('pull', 'invoice', $whmcsId, null, 'skipped', 'Cliente WHMCS nao encontrado: '.$whmcsUserId);
                return null;
            }

            $status = $this->mapInvoiceStatus($invoice['status'] ?? 'Unpaid');
            $dueDate = $this->parseDate($invoice['duedate'] ?? null) ?: now('America/Sao_Paulo')->addDays(7);
            
            $defaultNumber = 'WHMCS-'.$whmcsId;
            $scopedNumber = 'WHMCS-'.$this->connection->id.'-'.$whmcsId;
            $existing = Invoice::query()
                ->when($this->connection->tenant_id, fn ($q) => $q->where('tenant_id', $this->connection->tenant_id))
                ->where(function ($q) use ($defaultNumber, $scopedNumber, $whmcsId) {
                    $q->whereIn('number', [$defaultNumber, $scopedNumber])
                        ->orWhere('meta->whmcs_invoice_id', $whmcsId)
                        ->orWhere('meta->whmcs_invoice_id', (int) $whmcsId);
                })
                ->first();

            // Regra de Cancelamento/Estorno: Atualiza status para canceled/refunded e cancela disparos
            if (in_array($status, ['canceled', 'refunded'], true)) {
                if ($existing) {
                    $existing->update([
                        'status' => $status,
                        'meta'   => array_merge($existing->meta ?: [], [
                            'whmcs_status'    => $invoice['status'] ?? ($status === 'refunded' ? 'Refunded' : 'Cancelled'),
                            'whmcs_synced_at' => now()->toISOString(),
                        ]),
                    ]);
                    DB::table('billing_dispatches')
                        ->where('invoice_id', $existing->id)
                        ->whereIn('status', ['scheduled', 'failed', 'sending'])
                        ->update([
                            'status'     => 'canceled',
                            'last_error' => "Cancelado: fatura consta como '{$status}' no WHMCS (cobrança bloqueada por WhatsApp e e-mail).",
                            'updated_at' => now(),
                        ]);
                    $stats['updated']++;
                } else {
                    $stats['skipped']++;
                }
                return $existing;
            }

            // Regra de Janela: Ignorar faturas com vencimento maior que 15 dias no futuro
            if ($dueDate->copy()->startOfDay()->gt(now('America/Sao_Paulo')->addDays(15)->startOfDay())) {
                $stats['skipped']++;
                return null;
            }

            // Se 'balance' não estiver presente no payload (acontece quando a lista vem de GetInvoices),
            // busca via GetInvoice apenas se a fatura for nova ou se o status/valor tiver mudado.
            if (! isset($invoice['balance']) && ! empty($whmcsId)) {
                $whmcsStatusRaw = $invoice['status'] ?? 'Unpaid';
                $whmcsTotalRaw = (float) ($invoice['total'] ?? 0);

                $needsDetails = true;
                if ($existing && $existing->status === $status && abs((float) $existing->amount - $whmcsTotalRaw) < 0.01) {
                    $needsDetails = false;
                }

                if ($needsDetails) {
                    try {
                        $details = $this->call('GetInvoice', ['invoiceid' => $whmcsId]);
                        if (($details['result'] ?? '') === 'success') {
                            $invoice = array_merge($invoice, $details);
                        }
                    } catch (\Throwable) {}
                }
            }

            // Usar 'balance' (saldo real pendente após créditos aplicados).
            // Se o cliente tem crédito que abate parte da fatura, balance < total.
            $whmcsTotal        = $this->money($invoice['total'] ?? $invoice['subtotal'] ?? 0);
            $whmcsSubtotal     = $this->money($invoice['subtotal'] ?? $invoice['total'] ?? 0);
            $whmcsCreditDirect = $this->money($invoice['credit'] ?? 0);
            $hasBalanceKey     = isset($invoice['balance']) && is_numeric($invoice['balance']);
            $whmcsBalance      = $hasBalanceKey ? $this->money($invoice['balance']) : null;

            if (in_array($status, ['paid', 'canceled', 'refunded'], true)) {
                $whmcsCreditApplied = $whmcsCreditDirect;
                $baseAmount = $whmcsTotal > 0 ? $whmcsTotal : $whmcsSubtotal;
                if ($hasBalanceKey) {
                    $whmcsBalance = $this->money($invoice['balance']);
                } else {
                    $whmcsBalance = 0.0;
                }
            } elseif ($whmcsBalance !== null) {
                // Balance real pendente retornado pelo WHMCS
                $baseAmount = $whmcsBalance;
                // Crédito aplicado é a diferença entre o total original e o balance (ou o credit direto, se maior)
                $whmcsCreditApplied = max($whmcsCreditDirect, round(max(0.0, $whmcsTotal - $whmcsBalance), 2));
            } elseif ($whmcsCreditDirect > 0) {
                $whmcsCreditApplied = $whmcsCreditDirect;
                $baseAmount = max(0.0, round($whmcsTotal - $whmcsCreditDirect, 2));
                $whmcsBalance = $baseAmount;
            } else {
                $whmcsCreditApplied = 0.0;
                $baseAmount = $whmcsTotal > 0 ? $whmcsTotal : $whmcsSubtotal;
                $whmcsBalance = $baseAmount;
            }
            $paymentMethodRaw = $this->cleanText(
                $invoice['paymentmethod']
                    ?? $invoice['payment_method']
                    ?? $invoice['paymentmodule']
                    ?? $invoice['gateway']
                    ?? null,
                120
            );
            $respectPaymentMethod = (bool) data_get($this->connection->settings ?: [], 'respect_payment_method', true);
            $paymentMethod = $respectPaymentMethod
                ? $this->normalizePaymentMethod($paymentMethodRaw, $invoice)
                : 'all';
            $lateFee = $this->lateFeeBreakdown($baseAmount, $dueDate, $status);

            $origDueDateStr = $dueDate->toDateString();
            if (in_array($status, Invoice::OPEN_DUPLICATE_STATUSES, true)) {
                $dueDate = app(HolidayService::class)->nextBusinessDay($dueDate->copy()->startOfDay());
                if ($dueDate->toDateString() !== $origDueDateStr && $this->shouldProtectWeekendsHolidays()) {
                    try {
                        $this->call('UpdateInvoice', [
                            'invoiceid' => $whmcsId,
                            'duedate'   => $dueDate->toDateString(),
                        ]);
                    } catch (\Throwable) {}
                }
            }

            $paidAt = null;
            if ($status === 'paid') {
                $whmcsDatePaid = $invoice['datepaid'] ?? null;
                if ($whmcsDatePaid && $whmcsDatePaid !== '0000-00-00 00:00:00') {
                    try {
                        $paidAt = \Carbon\Carbon::parse($whmcsDatePaid);
                    } catch (\Throwable) {
                        $paidAt = $existing?->paid_at ?: now();
                    }
                } else {
                    $paidAt = $existing?->paid_at ?: now();
                }
            }

            $number = $existing?->number ?: $defaultNumber;
            if (! $existing && $this->connection->tenant_id && Invoice::where('number', $defaultNumber)->exists()) {
                $number = $scopedNumber;
            }

            $notes = (string) ($invoice['notes'] ?? '');
            $extractedPix = null;
            if (str_contains($notes, '000201') || str_contains($notes, 'BR.GOV.BCB.PIX')) {
                if (preg_match('/000201[0-9a-zA-Z]{50,}/', $notes, $matches)) {
                    $extractedPix = $matches[0];
                } elseif (preg_match('/[^\s]*BR\.GOV\.BCB\.PIX[^\s]*/', $notes, $matches)) {
                    $extractedPix = $matches[0];
                }
            }

            $meta = array_merge($existing?->meta ?: [], [
                'whmcs_invoice_id' => $whmcsId,
                'whmcs_user_id' => $whmcsUserId,
                'whmcs_connection_id' => $this->connection->id,
                'whmcs_status' => $invoice['status'] ?? null,
                'whmcs_synced_at' => now()->toISOString(),
                'whmcs_payment_method_raw' => $paymentMethodRaw,
                'payment_method' => $paymentMethod,
                'metodo_pagamento' => $this->paymentMethodLabel($paymentMethod, $paymentMethodRaw),
                'valor_original' => $whmcsSubtotal > 0 ? $whmcsSubtotal : $lateFee['base_amount'],
                'valor_pendente' => $lateFee['base_amount'],
                'valor_atualizado' => $lateFee['final_amount'],
                'multa_atraso' => $lateFee['fine_amount'],
                'juros_atraso' => $lateFee['daily_fee_amount'],
                'dias_atraso' => $lateFee['days_late'],
                // Crédito WHMCS: valor total da fatura antes de descontos, crédito aplicado e saldo pendente
                'whmcs_subtotal' => $whmcsSubtotal,
                'whmcs_credit_applied' => $whmcsCreditApplied > 0 ? $whmcsCreditApplied : null,
                'whmcs_balance' => $whmcsBalance > 0 ? $whmcsBalance : null,
                'linha_digitavel' => $this->firstFilled(
                    $invoice['barcode'] ?? null,
                    $invoice['linha_digitavel'] ?? null,
                    data_get($invoice, 'items.item.0.boleto_linha_digitavel')
                ),
                'boleto_url' => $this->firstFilled(
                    $invoice['invoiceurl'] ?? null,
                    $invoice['url'] ?? null,
                    $invoice['boleto_url'] ?? null
                ),
                'boleto_pdf_url' => $this->firstFilled(
                    $invoice['pdf_url'] ?? null,
                    $invoice['boleto_pdf_url'] ?? null,
                    $invoice['invoicepdf'] ?? null
                ),
                'pix_copia_cola' => $this->firstFilled(
                    $invoice['pix_copia_cola'] ?? null,
                    $invoice['pix_copy_paste'] ?? null,
                    $invoice['pix_payload'] ?? null,
                    $invoice['qrcode'] ?? null,
                    $extractedPix
                ),
                'qrcode_url' => $this->firstFilled(
                    $invoice['qrcode_url'] ?? null,
                    $invoice['qr_code_url'] ?? null,
                    $invoice['pix_qrcode'] ?? null
                ),
            ]);

            $serviceIds = $this->extractInvoiceServiceIds($invoice);
            if ($serviceIds !== []) {
                $meta['whmcs_service_ids'] = $serviceIds;
                if (in_array($status, Invoice::OPEN_DUPLICATE_STATUSES, true) && $dueDate->toDateString() !== $origDueDateStr && $this->shouldProtectWeekendsHolidays()) {
                    foreach ($serviceIds as $sId) {
                        try {
                            $this->call('UpdateClientProduct', [
                                'serviceid'   => $sId,
                                'nextduedate' => $dueDate->toDateString(),
                            ]);
                        } catch (\Throwable) {}
                    }
                }
            }

            $webhookToken = $existing?->webhook_token ?: \Illuminate\Support\Str::random(48);
            $meta['whmcs_viewinvoice_url'] = $this->invoicePaymentUrl($whmcsId);

            // Se o valor mudou em relação ao gateway salvo anteriormente, invalida gateway_payment e pix antigos
            $oldPrevGatewayAmount = (float) data_get($existing?->meta, 'gateway_payment.amount', 0);
            if ($existing && $oldPrevGatewayAmount > 0 && abs($oldPrevGatewayAmount - $lateFee['final_amount']) > 0.01) {
                unset(
                    $meta['gateway_payment'],
                    $meta['pix_copia_cola'],
                    $meta['pix_payload'],
                    $meta['qrcode_url'],
                    $meta['pix_qrcode'],
                    $meta['ticket_url'],
                    $meta['gateway_raw']
                );
                $existing->payment_url = null;
                $existing->gateway_charge_id = null;
            }

            $description = $this->invoiceDescription($invoice);
            if ($existing && !empty($existing->description) && !isset($invoice['items']) && !isset($invoice['lineitems']) && str_starts_with($description, 'Fatura WHMCS ')) {
                $description = $existing->description;
            }

            $payload = [
                'tenant_id' => $this->connection->tenant_id,
                'customer_id' => $customer->id,
                'number' => $number,
                'description' => $description,
                'amount' => $lateFee['final_amount'],
                'due_date' => $dueDate->toDateString(),
                'status' => $status,
                'gateway' => $this->connection->default_gateway,
                'webhook_token' => $webhookToken,
                'payment_url' => url('/fatura/' . $webhookToken),
                'paid_at' => $paidAt,
                'meta' => $meta,
            ];

            if ($existing) {
                $existing->update($payload);
                $localInvoice = $existing;
                $stats['updated']++;
            } else {
                $localInvoice = Invoice::create($payload);
                $stats['imported']++;
            }

            // Se a fatura é ou ficou paga/cancelada, cancela imediatamente qualquer disparo pendente
            if (in_array($status, ['paid', 'canceled', 'cancelled', 'refunded'], true)) {
                DB::table('billing_dispatches')
                    ->where('invoice_id', $localInvoice->id)
                    ->whereIn('status', ['scheduled', 'failed', 'sending'])
                    ->update([
                        'status'     => 'canceled',
                        'last_error' => "Cancelado: fatura identificada como '{$status}' na sincronização com o WHMCS.",
                        'updated_at' => now(),
                    ]);
            }

            $this->log('pull', 'invoice', $whmcsId, $localInvoice->id, 'ok', null, [
                'status' => $invoice['status'] ?? null,
                'total' => $invoice['total'] ?? null,
                'payment_method' => $paymentMethod,
                'days_late' => $lateFee['days_late'],
            ]);

            $this->syncLocalGatewayCharge($localInvoice, $paymentMethod, $status);

            return $localInvoice;
        } catch (\Throwable $e) {
            $stats['errors']++;
            $this->log('pull', 'invoice', $invoice['id'] ?? null, null, 'error', $e->getMessage(), $invoice);
            return null;
        }
    }

    protected function upsertService(array $service, array &$stats): ?WhmcsService
    {
        try {
            $serviceId = (string) ($service['id'] ?? $service['serviceid'] ?? '');
            $clientId = (string) ($service['clientid'] ?? $service['userid'] ?? '');

            if ($serviceId === '' || $clientId === '') {
                $stats['skipped']++;
                return null;
            }

            $customer = $this->findCustomerByWhmcsId($clientId) ?: $this->syncCustomerDetails($clientId);
            if (! $customer) {
                $stats['skipped']++;
                $this->log('pull', 'service', $serviceId, null, 'skipped', 'Cliente WHMCS nao encontrado: '.$clientId);
                return null;
            }

            $status = $this->cleanText($service['status'] ?? null) ?: 'Unknown';
            $existing = WhmcsService::query()
                ->where('connection_id', $this->connection->id)
                ->where('whmcs_service_id', $serviceId)
                ->first();

            $productName = $this->cleanText($service['name'] ?? $service['productname'] ?? $service['translated_name'] ?? null);
            $groupName = $this->cleanText($service['groupname'] ?? $service['productgroup'] ?? null);

            $payload = [
                'connection_id' => $this->connection->id,
                'tenant_id' => $this->connection->tenant_id,
                'customer_id' => $customer->id,
                'whmcs_client_id' => $clientId,
                'whmcs_service_id' => $serviceId,
                'product_id' => $this->scalarString($service['pid'] ?? $service['productid'] ?? null),
                'product_name' => $productName ?: 'Servico WHMCS #'.$serviceId,
                'group_name' => $groupName ?: null,
                'domain' => $this->cleanText($service['domain'] ?? null),
                'username' => $this->cleanText($service['username'] ?? null),
                'dedicated_ip' => $this->cleanText($service['dedicatedip'] ?? $service['dedicated_ip'] ?? null),
                'server_id' => $this->scalarString($service['serverid'] ?? null),
                'status' => $status,
                'billing_cycle' => $this->cleanText($service['billingcycle'] ?? null),
                'amount' => $this->money($service['recurringamount'] ?? $service['amount'] ?? $service['firstpaymentamount'] ?? 0),
                'registration_date' => $this->parseDate($service['regdate'] ?? $service['registrationdate'] ?? null)?->toDateString(),
                'next_due_date' => $this->parseDate($service['nextduedate'] ?? null)?->toDateString(),
                'next_invoice_date' => $this->parseDate($service['nextinvoicedate'] ?? null)?->toDateString(),
                'suspended_at' => Str::lower($status) === 'suspended'
                    ? ($existing?->suspended_at ?: now())
                    : null,
                'reactivated_at' => Str::lower($status) === 'active'
                    ? ($existing?->reactivated_at)
                    : null,
                'last_synced_at' => now(),
                'raw' => $this->sanitizePayload($service),
            ];

            // Proteção contra suspensão indevida pelo WHMCS em fins de semana ou feriados bancários
            if (Str::lower($status) === 'suspended' && $this->shouldProtectWeekendsHolidays()) {
                $holidays = app(HolidayService::class);
                $today = now('America/Sao_Paulo');
                $isNonBusiness = $holidays->isNonBusinessDay($today);

                $hasValidOpenInvoice = false;
                if ($customer) {
                    $hasValidOpenInvoice = Invoice::query()
                        ->where('customer_id', $customer->id)
                        ->whereIn('status', ['pending', 'open', 'sent'])
                        ->where('due_date', '>=', $today->toDateString())
                        ->exists();
                }

                if ($isNonBusiness || $hasValidOpenInvoice) {
                    try {
                        $unok = $this->unsuspendService((int) $serviceId);
                        if ($unok) {
                            $status = 'Active';
                            $payload['status'] = 'Active';
                            $payload['suspended_at'] = null;
                            $payload['reactivated_at'] = now();
                            $this->log('push', 'service', $serviceId, $existing?->id, 'ok',
                                'Suspensão pelo WHMCS cancelada automaticamente: regras do sistema não permitem corte em finais de semana ou feriados bancários.');
                        }
                    } catch (\Throwable) {}
                }
            }

            if ($existing) {
                $existing->update($payload);
                $stats['updated']++;
                $localService = $existing;
            } else {
                $localService = WhmcsService::create($payload);
                $stats['imported']++;
            }

            $this->log('pull', 'service', $serviceId, $localService->id, 'ok', null, [
                'status' => $status,
                'clientid' => $clientId,
                'nextduedate' => $service['nextduedate'] ?? null,
            ]);

            return $localService;
        } catch (\Throwable $e) {
            $stats['errors']++;
            $this->log('pull', 'service', $service['id'] ?? null, null, 'error', $e->getMessage(), $service);
            return null;
        }
    }

    // Método updateExistingCustomerFromList removido pois upsertCustomer agora cuida de tudo.
    public function syncCustomerDetails(string $clientId, ?array &$stats = null): ?Customer
    {
        try {
            $data = $this->call('GetClientsDetails', ['clientid' => $clientId, 'stats' => false]);
            if (empty($data) || ($data['result'] ?? '') === 'error') {
                return null;
            }

            $localStats = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
            $customer = $this->upsertCustomer($data, $localStats);

            if ($stats !== null) {
                $stats['imported'] += $localStats['imported'];
                $stats['updated']  += $localStats['updated'];
                $stats['skipped']  += $localStats['skipped'];
                $stats['errors']   += $localStats['errors'];
            }

            return $customer;
        } catch (\Throwable) {
            if ($stats !== null) {
                $stats['errors']++;
            }
            return null;
        }
    }

    protected function findCustomerByWhmcsId(string $whmcsUserId): ?Customer
    {
        return Customer::query()
            ->when($this->connection->tenant_id, fn ($q) => $q->where('tenant_id', $this->connection->tenant_id))
            ->where(function ($q) use ($whmcsUserId) {
                $q->where('meta->whmcs_id', $whmcsUserId)
                    ->orWhere('meta->whmcs_id', (int) $whmcsUserId);
            })
            ->first();
    }

    protected function normalizeRows(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        if ($rows === []) {
            return [];
        }

        if (array_is_list($rows)) {
            return $rows;
        }

        return [$rows];
    }

    protected function extractInvoiceServiceIds(array $invoice): array
    {
        $ids = [];
        foreach (['serviceid', 'service_id', 'relid', 'hostingid'] as $field) {
            if (! empty($invoice[$field])) {
                $ids[] = (string) $invoice[$field];
            }
        }

        foreach ([
            $invoice['items']['item'] ?? null,
            $invoice['lineitems']['lineitem'] ?? null,
            $invoice['line_items'] ?? null,
        ] as $items) {
            foreach ($this->normalizeRows($items) as $item) {
                foreach (['serviceid', 'service_id', 'relid', 'hostingid'] as $field) {
                    if (! empty($item[$field])) {
                        $ids[] = (string) $item[$field];
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    protected function mapInvoiceStatus(string $status): string
    {
        return match (Str::lower($status)) {
            'paid' => 'paid',
            'cancelled', 'canceled' => 'canceled',
            'refunded' => 'refunded',
            'collections', 'overdue' => 'overdue',
            default => 'pending',
        };
    }

    protected function invoiceDescription(array $invoice): string
    {
        $items = [];
        foreach ([
            $invoice['items']['item'] ?? null,
            $invoice['lineitems']['lineitem'] ?? null,
            $invoice['line_items'] ?? null,
        ] as $itemList) {
            if ($itemList) {
                $items = $this->normalizeRows($itemList);
                break;
            }
        }

        if (!empty($items)) {
            $descriptions = [];
            foreach ($items as $item) {
                if (!empty($item['description'])) {
                    $desc = $this->cleanText($item['description']);
                    $descriptions[] = trim(explode("\n", $desc)[0]);
                }
            }
            if (!empty($descriptions)) {
                return implode(' / ', array_unique($descriptions));
            }
        }

        $id = $invoice['id'] ?? $invoice['invoiceid'] ?? '';
        $date = $invoice['date'] ?? null;
        $description = 'Fatura WHMCS '.$id;

        if ($date) {
            $description .= ' emitida em '.Carbon::parse($date)->format('d/m/Y');
        }

        return $description;
    }

    protected function invoicePaymentUrl(string $invoiceId): string
    {
        return rtrim($this->baseUrl(), '/').'/viewinvoice.php?id='.$invoiceId;
    }

    protected function normalizePaymentMethod(?string $raw, array $invoice = []): string
    {
        $raw = Str::lower((string) $raw);

        if ($raw === '') {
            if ($this->firstFilled($invoice['pix_copia_cola'] ?? null, $invoice['pix_payload'] ?? null, $invoice['qrcode'] ?? null) !== '') {
                return 'pix';
            }

            return 'all';
        }

        return match (true) {
            str_contains($raw, 'pix') => 'pix',
            str_contains($raw, 'boleto') || str_contains($raw, 'ticket') || str_contains($raw, 'bank slip') => 'boleto',
            str_contains($raw, 'debit') || str_contains($raw, 'debito') => 'debit_card',
            str_contains($raw, 'cart') || str_contains($raw, 'credit') || str_contains($raw, 'card') => 'credit_card',
            default => 'all',
        };
    }

    protected function paymentMethodLabel(string $normalized, ?string $raw): string
    {
        if ($raw) {
            return $raw;
        }

        return match ($normalized) {
            'pix' => 'Pix',
            'boleto' => 'Boleto',
            'debit_card' => 'Cartao de debito',
            'credit_card' => 'Cartao de credito',
            default => 'Checkout online',
        };
    }

    protected function lateFeeBreakdown(float $baseAmount, Carbon $dueDate, string $status): array
    {
        $settings = $this->connection->settings ?: [];
        $openStatuses = Invoice::OPEN_DUPLICATE_STATUSES;
        $daysLate = 0;

        if (in_array($status, $openStatuses, true)) {
            $today = now('America/Sao_Paulo')->startOfDay();
            $reference = $dueDate->copy()->startOfDay();

            if ($reference->isBefore($today)) {
                $daysLate = $reference->diffInDays($today);
            }
        }

        $graceDays = max(0, (int) data_get($settings, 'late_fee_grace_days', 0));
        $chargeableDays = max(0, $daysLate - $graceDays);
        $fineFixed = round((float) data_get($settings, 'late_fee_fixed_amount', 0), 2);
        $finePercent = max(0, (float) data_get($settings, 'late_fee_fixed_percent', 0));
        $dailyAmount = round((float) data_get($settings, 'late_fee_daily_amount', 0), 2);
        $dailyPercent = max(0, (float) data_get($settings, 'late_fee_daily_percent', 0));

        $fineAmount = $chargeableDays > 0
            ? round($fineFixed + ($baseAmount * ($finePercent / 100)), 2)
            : 0.0;
        $dailyFeeAmount = $chargeableDays > 0
            ? round(($dailyAmount * $chargeableDays) + (($baseAmount * ($dailyPercent / 100)) * $chargeableDays), 2)
            : 0.0;
        $finalAmount = round($baseAmount + $fineAmount + $dailyFeeAmount, 2);

        return [
            'base_amount' => round($baseAmount, 2),
            'days_late' => $daysLate,
            'chargeable_days' => $chargeableDays,
            'fine_amount' => $fineAmount,
            'daily_fee_amount' => $dailyFeeAmount,
            'final_amount' => $finalAmount,
            'summary' => $chargeableDays > 0
                ? 'Valor atualizado com multa e juros diarios configurados.'
                : '',
        ];
    }

    protected function syncLocalGatewayCharge(Invoice $invoice, string $paymentMethod, string $status): void
    {
        $settings = $this->connection->settings ?: [];

        if (! in_array($status, Invoice::OPEN_DUPLICATE_STATUSES, true)) {
            return;
        }

        if (! (bool) data_get($settings, 'generate_local_gateway_charge', true)) {
            return;
        }

        if (! $this->providerSupportsPaymentMethod((string) $invoice->gateway, $paymentMethod)) {
            return;
        }

        $meta = $invoice->meta ?: [];
        $meta['payment_method'] = $paymentMethod;
        $meta['metodo_pagamento'] = $this->paymentMethodLabel($paymentMethod, (string) ($meta['whmcs_payment_method_raw'] ?? ''));
        $invoice->forceFill(['meta' => $meta])->save();

        $chargeSummary = data_get($meta, 'gateway_payment');
        $mustRefresh = ! is_array($chargeSummary)
            || (string) data_get($chargeSummary, 'provider') !== (string) $invoice->gateway
            || round((float) data_get($chargeSummary, 'amount', 0), 2) !== round((float) $invoice->amount, 2)
            || (float) ($meta['valor_atualizado'] ?? $invoice->amount) !== (float) $invoice->amount
            || ! $invoice->hasReusableCharge();

        if (! $mustRefresh) {
            return;
        }

        try {
            app(InvoicePaymentService::class)->hydrateGatewayPayment($invoice, force: true);
        } catch (\Throwable $e) {
            $this->log('push', 'invoice_payment', (string) ($meta['whmcs_invoice_id'] ?? $invoice->id), $invoice->id, 'error', $e->getMessage(), [
                'gateway' => $invoice->gateway,
                'payment_method' => $paymentMethod,
            ]);
        }
    }

    protected function providerSupportsPaymentMethod(string $provider, string $paymentMethod): bool
    {
        $provider = strtolower(trim($provider));

        return match ($provider) {
            'sumup' => in_array($paymentMethod, ['credit_card', 'debit_card', 'all'], true),
            'efipro' => in_array($paymentMethod, ['pix', 'credit_card', 'card', 'all'], true),
            default => in_array($paymentMethod, ['pix', 'boleto', 'credit_card', 'debit_card', 'all'], true),
        };
    }

    protected function firstFilled(mixed ...$values): string
    {
        foreach ($values as $value) {
            if (is_string($value) || is_numeric($value)) {
                $value = trim((string) $value);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    protected function baseUrl(): string
    {
        $url = preg_replace('#/includes/api\.php$#i', '', rtrim($this->connection->api_url, '/'));
        return $url ?: rtrim(config('app.url'), '/');
    }

    protected function parseDate(mixed $value): ?Carbon
    {
        $value = $this->scalarString($value);

        if (! $value || $value === '0000-00-00') {
            return null;
        }

        return Carbon::parse($value, 'America/Sao_Paulo')->startOfDay();
    }

    protected function parseDateTime(mixed $value): ?Carbon
    {
        $value = $this->scalarString($value);

        if (! $value || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        return Carbon::parse($value, 'America/Sao_Paulo');
    }

    protected function money(mixed $value): float
    {
        if (is_array($value) || is_object($value)) {
            return 0.0;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $normalized = preg_replace('/[^\d,.-]/', '', (string) $value);
        $lastComma = strrpos($normalized, ',');
        $lastDot = strrpos($normalized, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $decimalSeparator = $lastComma > $lastDot ? ',' : '.';
            $thousandSeparator = $decimalSeparator === ',' ? '.' : ',';
            $normalized = str_replace($thousandSeparator, '', $normalized);
            $normalized = str_replace($decimalSeparator, '.', $normalized);
        } elseif ($lastComma !== false) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } else {
            $normalized = str_replace(',', '', $normalized);
        }

        return round((float) $normalized, 2);
    }

    protected function cleanText(mixed $value, int $limit = 255): ?string
    {
        $value = $this->scalarString($value);

        return $value === '' ? null : Str::limit($value, $limit, '');
    }

    protected function scalarString(mixed $value): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }

        return trim((string) $this->normalizeEncoding((string) $value));
    }

    protected function normalizeEncoding(mixed $value): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->normalizeEncoding($item);
            }

            return $value;
        }

        if (! is_string($value) || $value === '') {
            return $value;
        }

        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'Windows-1252, ISO-8859-1, UTF-8');
    }

    protected function normalizePhone(mixed $phone, mixed $countryCode = null): ?string
    {
        if (is_array($phone) || is_object($phone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $phone);
        $countryCode = is_array($countryCode) || is_object($countryCode)
            ? ''
            : preg_replace('/\D+/', '', (string) $countryCode);

        if ($digits === '') {
            return null;
        }

        if ($countryCode !== '' && strlen($digits) >= 8 && ! str_starts_with($digits, $countryCode)) {
            $digits = $countryCode.$digits;
        } elseif (strlen($digits) >= 10 && ! str_starts_with($digits, '55')) {
            $digits = '55'.$digits;
        }

        return substr($digits, 0, 20);
    }

    protected function normalizeCep(mixed $cep): ?string
    {
        if (is_array($cep) || is_object($cep)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $cep) ?: '';
        if (strlen($digits) < 8) {
            return null;
        }

        $digits = substr($digits, 0, 8);

        return substr($digits, 0, 5).'-'.substr($digits, 5, 3);
    }

    protected function normalizeState(mixed $state): ?string
    {
        if (is_array($state) || is_object($state)) {
            return null;
        }

        $state = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $state));
        return strlen($state) === 2 ? $state : null;
    }

    protected function extractDocument(array $client): ?string
    {
        $custom = $this->extractWhmcsCustomFields($client);
        if (!empty($custom['document'])) {
            return substr($custom['document'], 0, 20);
        }

        foreach (['cpf_cnpj', 'tax_id', 'taxid', 'document', 'vat', 'cpf', 'cnpj'] as $field) {
            $document = preg_replace('/\D+/', '', (string) ($client[$field] ?? ''));
            if ($document !== '') {
                return substr($document, 0, 20);
            }
        }

        return null;
    }

    protected function extractWhmcsCustomFields(array $client): array
    {
        $extracted = [
            'person_type'    => null, // 'Fisica', 'Juridica'
            'cpf'            => null,
            'cnpj'           => null,
            'birth_date'     => null, // 'YYYY-MM-DD'
            'whatsapp_optin' => true,
            'document'       => null,
            'raw'            => [],
        ];

        $fields = [];

        // 1. Array 'customfields' (retornado por GetClientsDetails)
        $cfList = $client['customfields'] ?? $client['client']['customfields'] ?? null;
        if (is_array($cfList)) {
            if (isset($cfList['customfield'])) {
                $cfList = $this->normalizeRows($cfList['customfield']);
            }
            foreach ($this->normalizeRows($cfList) as $f) {
                if (is_array($f)) {
                    $fields[] = $f;
                }
            }
        }

        // 2. Chaves na raiz: customfields1, customfields2, etc.
        foreach ($client as $k => $v) {
            if (preg_match('/^customfields?(\d+)$/i', $k, $m) && is_scalar($v) && trim((string) $v) !== '') {
                $fields[] = [
                    'id'    => (int) $m[1],
                    'name'  => 'customfields' . $m[1],
                    'value' => (string) $v,
                ];
            }
        }

        // 3. Documentos nativos na raiz
        foreach (['tax_id', 'taxid', 'vat', 'document', 'cpf', 'cnpj', 'cpf_cnpj'] as $field) {
            if (!empty($client[$field]) && is_scalar($client[$field])) {
                $val = trim((string) $client[$field]);
                $digits = preg_replace('/\D+/', '', $val);
                if (strlen($digits) === 11 && !$extracted['cpf']) {
                    $extracted['cpf'] = $digits;
                } elseif (strlen($digits) === 14 && !$extracted['cnpj']) {
                    $extracted['cnpj'] = $digits;
                }
            }
        }

        foreach ($fields as $f) {
            $id = isset($f['id']) ? (int) $f['id'] : null;
            $name = Str::lower((string) ($f['name'] ?? $f['fieldname'] ?? ''));
            $rawVal = trim((string) ($f['value'] ?? ''));
            if ($rawVal === '') continue;

            $extracted['raw'][] = [
                'id'    => $id,
                'name'  => $f['name'] ?? $f['fieldname'] ?? ('field_' . $id),
                'value' => $rawVal,
            ];

            $cleanVal = preg_replace('/\D+/', '', $rawVal);

            // A) Tipo de Pessoa (ID 1, ou name contendo pessoa/tipo/natureza)
            if ($id === 1 || str_contains($name, 'pessoa') || str_contains($name, 'tipo') || str_contains($name, 'natureza')) {
                $lowerVal = Str::lower($rawVal);
                if (str_contains($lowerVal, 'jur') || str_contains($lowerVal, 'pj') || str_contains($lowerVal, 'cnpj') || str_contains($lowerVal, 'empresa')) {
                    $extracted['person_type'] = 'Juridica';
                } elseif (str_contains($lowerVal, 'fis') || str_contains($lowerVal, 'fís') || str_contains($lowerVal, 'pf') || str_contains($lowerVal, 'cpf')) {
                    $extracted['person_type'] = 'Fisica';
                }
            }

            // B) Data de Nascimento (ID 3, ou name contendo nasc/birth/aniversario)
            if ($id === 3 || str_contains($name, 'nasc') || str_contains($name, 'birth') || str_contains($name, 'anivers')) {
                $parsedDate = $this->parseBirthDate($rawVal);
                if ($parsedDate) {
                    $extracted['birth_date'] = $parsedDate;
                }
            } elseif (!$extracted['birth_date'] && preg_match('/^\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}$/', $rawVal)) {
                $parsedDate = $this->parseBirthDate($rawVal);
                if ($parsedDate) {
                    $extracted['birth_date'] = $parsedDate;
                }
            }

            // C) Quero Receber Mensagem WhatsApp (ID 13, ou contendo whatsapp/zap/receber/optin)
            $isOptinField = $id === 13 
                || str_contains($name, 'quero receber mensagem whatsapp')
                || str_contains($name, 'receber mensagem')
                || str_contains($name, 'receber whatsapp')
                || str_contains($name, 'mensagem whatsapp')
                || str_contains($name, 'optin')
                || str_contains($name, 'opt-in')
                || str_contains($name, 'autoriza');

            if ($isOptinField) {
                $lowerVal = Str::lower($rawVal);
                if (in_array($lowerVal, ['não', 'nao', 'no', '0', 'off', 'false', 'n', 'recusar', 'recusado', 'rejeitar'])) {
                    $extracted['whatsapp_optin'] = false;
                } elseif (in_array($lowerVal, ['sim', 'yes', '1', 'on', 'true', 's', 'y', 'aceito', 'autorizado'])) {
                    $extracted['whatsapp_optin'] = true;
                }
            }

            // D) CPF (ID 2, ou name contendo cpf, ou 11 dígitos numéricos válidos)
            if ($id === 2 || str_contains($name, 'cpf')) {
                if (strlen($cleanVal) === 11) {
                    $extracted['cpf'] = $cleanVal;
                }
            } elseif (strlen($cleanVal) === 11 && !$extracted['cpf'] && !preg_match('/^\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}$/', $rawVal)) {
                $extracted['cpf'] = $cleanVal;
            }

            // E) CNPJ (ID 4, ou name contendo cnpj, ou 14 dígitos numéricos válidos)
            if ($id === 4 || str_contains($name, 'cnpj')) {
                if (strlen($cleanVal) === 14) {
                    $extracted['cnpj'] = $cleanVal;
                }
            } elseif (strlen($cleanVal) === 14 && !$extracted['cnpj']) {
                $extracted['cnpj'] = $cleanVal;
            }
        }

        // Se tem CNPJ e CPF, prioriza CNPJ se for Jurídica
        if ($extracted['person_type'] === 'Juridica' && $extracted['cnpj']) {
            $extracted['document'] = $extracted['cnpj'];
        } elseif ($extracted['cpf']) {
            $extracted['document'] = $extracted['cpf'];
        } elseif ($extracted['cnpj']) {
            $extracted['document'] = $extracted['cnpj'];
        }

        return $extracted;
    }

    protected function parseBirthDate(?string $dateStr): ?string
    {
        if (!$dateStr) return null;
        $dateStr = trim($dateStr);
        
        // Formato brasileiro DD/MM/YYYY
        if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $dateStr, $m)) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = (int) $m[3];
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        } elseif (preg_match('/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})$/', $dateStr, $m)) {
            $year = (int) $m[1];
            $month = (int) $m[2];
            $day = (int) $m[3];
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        try {
            return \Carbon\Carbon::parse($dateStr)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function extractPhones(array $client): array
    {
        $whatsapp = null;
        $otherPhones = [];
        
        $customFields = $this->normalizeRows($client['customfields']['customfield'] ?? $client['customfields'] ?? []);
        foreach ($customFields as $field) {
            if (!is_array($field)) continue;
            $name = Str::lower((string) ($field['name'] ?? $field['fieldname'] ?? ''));
            $valRaw = trim((string) ($field['value'] ?? ''));

            // Ignora se for opt-in booleano
            if (in_array(Str::lower($valRaw), ['sim', 'nao', 'não', 'yes', 'no', 'on', 'off', 'true', 'false'])) {
                continue;
            }
            
            if (str_contains($name, 'whatsapp') || str_contains($name, 'wpp') || str_contains($name, 'zap')) {
                $val = $this->normalizePhone($valRaw, $client['phonecc'] ?? $client['countrycode'] ?? null);
                if ($val && !$whatsapp) {
                    $whatsapp = $val;
                } elseif ($val) {
                    $otherPhones[] = $val;
                }
            } elseif (str_contains($name, 'celular') || str_contains($name, 'telefone') || str_contains($name, 'phone')) {
                $val = $this->normalizePhone($valRaw, $client['phonecc'] ?? $client['countrycode'] ?? null);
                if ($val) {
                    $otherPhones[] = $val;
                }
            }
        }

        $mainPhone = $this->normalizePhone($client['phonenumber'] ?? $client['phone'] ?? null, $client['phonecc'] ?? $client['countrycode'] ?? null);
        if ($mainPhone) {
            $otherPhones[] = $mainPhone;
        }

        $otherPhones = array_values(array_unique(array_filter($otherPhones)));
        
        if (!$whatsapp && !empty($otherPhones)) {
            $whatsapp = $otherPhones[0];
        }
        
        $phone = $mainPhone ?: ($otherPhones[0] ?? null);

        return [
            'whatsapp' => $whatsapp,
            'phone' => $phone,
            'all' => array_values(array_unique(array_filter(array_merge([$whatsapp], $otherPhones))))
        ];
    }

    protected function hasErrors(array $stats): bool
    {
        foreach ($stats as $group) {
            if (($group['errors'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    protected function rememberTest(string $status, ?string $error = null): void
    {
        if (! $this->connection->exists) {
            return;
        }

        $this->connection->forceFill([
            'last_test_at' => now(),
            'last_test_status' => $status,
            'last_error' => $error,
        ])->save();
    }

    protected function rememberError(string $message): void
    {
        if (! $this->connection->exists) {
            return;
        }

        $this->connection->forceFill(['last_error' => $message])->save();
    }

    protected function sanitizePayload(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        foreach ($payload as $key => $value) {
            if (in_array(Str::lower((string) $key), ['secret', 'password', 'api_token', 'token', 'webhook_secret'], true)) {
                $payload[$key] = '***';
            } elseif (is_array($value)) {
                $payload[$key] = $this->sanitizePayload($value);
            } elseif (is_string($value)) {
                $payload[$key] = $this->normalizeEncoding($value);
            }
        }

        return $payload;
    }

    protected function log(
        string $direction,
        string $entity,
        mixed $whmcsId,
        mixed $localId,
        string $status,
        ?string $message = null,
        ?array $payload = null
    ): void {
        if (! $this->connection->exists || ! $this->connection->id) {
            return;
        }

        try {
            DB::table('whmcs_sync_log')->insert([
                'connection_id' => $this->connection->id,
                'direction' => $direction,
                'entity' => $entity,
                'whmcs_id' => is_numeric($whmcsId) ? (int) $whmcsId : null,
                'local_id' => is_numeric($localId) ? (int) $localId : null,
                'status' => $status,
                'message' => $message,
                'payload' => $payload ? json_encode($this->sanitizePayload($payload), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Log operacional nao pode derrubar sincronizacao.
        }
    }

    public function shouldProtectWeekendsHolidays(): bool
    {
        if ($this->connection->tenant_id) {
            $config = DB::table('tenant_billing_config')
                ->where('tenant_id', $this->connection->tenant_id)
                ->value('skip_weekends_holidays');
            if ($config !== null) {
                return (bool) $config;
            }
        }
        if (! empty($this->connection->prevent_weekend_holiday_suspension)) {
            return true;
        }
        return (bool) data_get($this->connection->settings ?: [], 'skip_weekends_holidays', false);
    }
}
