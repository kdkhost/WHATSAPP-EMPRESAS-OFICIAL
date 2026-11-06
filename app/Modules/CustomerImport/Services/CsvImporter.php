<?php

namespace App\Modules\CustomerImport\Services;

use App\Models\Customer;
use App\Support\Format;
use Illuminate\Http\UploadedFile;

/**
 * Importacao em lote de clientes via CSV/Excel.
 *
 * Suporta:
 *  - CSV (delimiter , ou ; auto-detectado)
 *  - Mapeamento flexivel de colunas (case-insensitive)
 *  - Validacao + dedup por documento/email
 *  - Sanitizacao de telefones para formato WhatsApp Meta
 */
class CsvImporter
{
    /** Aliases aceitos para cada campo do Customer. */
    protected array $columnMap = [
        'name'        => ['nome', 'name', 'cliente', 'razao social', 'razao_social'],
        'email'       => ['email', 'e-mail', 'mail'],
        'document'    => ['cpf', 'cnpj', 'documento', 'document', 'cpf/cnpj'],
        'phone'       => ['telefone', 'phone', 'fone', 'celular'],
        'whatsapp'    => ['whatsapp', 'wpp', 'zap'],
        'cep'         => ['cep', 'zipcode', 'zip'],
        'logradouro'  => ['logradouro', 'rua', 'endereco', 'address', 'street'],
        'numero'      => ['numero', 'number', 'nº', 'n°'],
        'complemento' => ['complemento', 'complement'],
        'bairro'      => ['bairro', 'neighborhood'],
        'cidade'      => ['cidade', 'city', 'localidade'],
        'uf'          => ['uf', 'estado', 'state'],
    ];

    /** Le um CSV e retorna preview + mapeamento sugerido. */
    public function preview(UploadedFile $file, int $rows = 5): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            throw new \RuntimeException('Nao foi possivel abrir o arquivo.');
        }

        $delimiter = $this->detectDelimiter($file->getRealPath());
        $headers = fgetcsv($handle, 0, $delimiter);
        if (!$headers) {
            fclose($handle);
            throw new \RuntimeException('Arquivo vazio ou invalido.');
        }

        // Sanitiza headers (trim, lower, BOM)
        $headers = array_map(fn($h) => trim(str_replace("\xEF\xBB\xBF", '', $h)), $headers);

        $preview = [];
        for ($i = 0; $i < $rows; $i++) {
            $row = fgetcsv($handle, 0, $delimiter);
            if (!$row) break;
            $preview[] = array_combine($headers, array_pad($row, count($headers), null));
        }
        fclose($handle);

        return [
            'headers'    => $headers,
            'preview'    => $preview,
            'mapping'    => $this->suggestMapping($headers),
            'delimiter'  => $delimiter,
        ];
    }

    /** Importa de fato. Retorna estatisticas. */
    public function import(UploadedFile $file, array $mapping, ?int $tenantId = null): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $delimiter = $this->detectDelimiter($file->getRealPath());
        $headers = fgetcsv($handle, 0, $delimiter);
        $headers = array_map(fn($h) => trim(str_replace("\xEF\xBB\xBF", '', $h)), $headers);

        $stats = ['imported' => 0, 'skipped' => 0, 'errors' => []];
        $line = 1;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;
            $assoc = array_combine($headers, array_pad($row, count($headers), null));
            $data = $this->mapRow($assoc, $mapping);

            if (empty($data['name'])) {
                $stats['skipped']++;
                $stats['errors'][] = "Linha {$line}: nome vazio";
                continue;
            }

            // Sanitiza telefones
            if (!empty($data['whatsapp'])) {
                $data['whatsapp'] = Format::onlyDigits($data['whatsapp']);
            }
            if (!empty($data['phone'])) {
                $data['phone'] = Format::onlyDigits($data['phone']);
            }

            if ($tenantId) {
                $data['tenant_id'] = $tenantId;
            }
            $data['active'] = true;

            try {
                // Dedup por documento ou email
                $existing = null;
                if (!empty($data['document'])) {
                    $existing = Customer::where('document', $data['document']);
                    if ($tenantId) $existing->where('tenant_id', $tenantId);
                    $existing = $existing->first();
                } elseif (!empty($data['email'])) {
                    $existing = Customer::where('email', $data['email']);
                    if ($tenantId) $existing->where('tenant_id', $tenantId);
                    $existing = $existing->first();
                }

                if ($existing) {
                    $existing->update($data);
                } else {
                    Customer::create($data);
                }
                $stats['imported']++;
            } catch (\Throwable $e) {
                $stats['skipped']++;
                $stats['errors'][] = "Linha {$line}: " . $e->getMessage();
            }
        }
        fclose($handle);

        return $stats;
    }

    protected function detectDelimiter(string $path): string
    {
        $sample = file_get_contents($path, false, null, 0, 4096) ?: '';
        $semicolons = substr_count($sample, ';');
        $commas = substr_count($sample, ',');
        return $semicolons > $commas ? ';' : ',';
    }

    protected function suggestMapping(array $headers): array
    {
        $mapping = [];
        foreach ($this->columnMap as $field => $aliases) {
            foreach ($headers as $h) {
                $hLower = mb_strtolower(trim($h));
                if (in_array($hLower, $aliases, true)) {
                    $mapping[$field] = $h;
                    break;
                }
            }
        }
        return $mapping;
    }

    protected function mapRow(array $row, array $mapping): array
    {
        $data = [];
        foreach ($mapping as $field => $csvCol) {
            if ($csvCol && isset($row[$csvCol])) {
                $data[$field] = trim((string) $row[$csvCol]);
            }
        }
        return $data;
    }
}
