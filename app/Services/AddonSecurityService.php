<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AddonSecurityService
{
    protected array $allowedDomains = [];

    protected array $protectedPaths = [
        'app/Modules/WhatsApp',
        'app/Modules/Tenancy',
        'app/Models',
        'config',
        'routes',
        '.env',
        'vendor',
    ];

    public function __construct()
    {
        $this->allowedDomains = array_values(array_filter(array_map('trim', explode(',', (string) env('ADDON_ALLOWED_DOMAINS', '')))));
    }

    public function validateInstall(string $path, bool $allowSandboxPath = false): array
    {
        $errors = [];

        $checksumResult = $this->verifyChecksum($path);
        if (! $checksumResult['ok']) {
            $errors[] = $checksumResult['error'];
        }

        $pathTraversalResult = $this->checkPathTraversal($path, $allowSandboxPath);
        if (! $pathTraversalResult['ok']) {
            $errors[] = $pathTraversalResult['error'];
        }

        $coreOverwriteResult = $this->checkCoreOverwrite($path);
        if (! $coreOverwriteResult['ok']) {
            $errors[] = $coreOverwriteResult['error'];
        }

        $envAccessResult = $this->checkEnvAccess($path);
        if (! $envAccessResult['ok']) {
            $errors[] = $envAccessResult['error'];
        }

        if (empty($errors)) {
            return ['ok' => true, 'message' => 'Addon validado com sucesso.'];
        }

        return ['ok' => false, 'error' => implode('; ', $errors), 'errors' => $errors];
    }

    public function verifyChecksum(string $path): array
    {
        $manifest = $this->loadManifest($path);
        if (! $manifest) {
            return ['ok' => false, 'error' => 'Manifesto nao encontrado ou invalido.'];
        }

        $expectedChecksum = $manifest['checksum'] ?? $manifest['sha256'] ?? null;
        if (! $expectedChecksum) {
            return ['ok' => true, 'message' => 'Sem checksum para verificar.'];
        }

        $actualChecksum = $this->calculateDirectoryChecksum($path);

        if ($expectedChecksum !== $actualChecksum) {
            Log::warning('[AddonSecurity] Checksum invalido', [
                'path' => $path,
                'expected' => $expectedChecksum,
                'actual' => $actualChecksum,
            ]);

            return ['ok' => false, 'error' => 'Checksum SHA256 nao corresponde. O addon pode ter sido adulterado.'];
        }

        return ['ok' => true, 'message' => 'Checksum verificado com sucesso.'];
    }

    public function checkDomainAllowlist(string $url): bool
    {
        if (empty($this->allowedDomains)) {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            return false;
        }

        foreach ($this->allowedDomains as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    public function sandboxExtract(string $zipPath, string $extractTo): array
    {
        if (! File::exists($zipPath)) {
            return ['ok' => false, 'error' => 'Arquivo zip nao encontrado.'];
        }

        $tempDir = storage_path('app/addons_sandbox/'.Str::random(16));
        File::ensureDirectoryExists($tempDir);

        try {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) !== true) {
                return ['ok' => false, 'error' => 'Falha ao abrir arquivo zip.'];
            }

            for ($i = 0; $i < $zip->numEntries; $i++) {
                $entry = $zip->getNameIndex($i);
                if ($entry === false) {
                    continue;
                }

                $normalized = str_replace('\\', '/', $entry);
                if (str_contains($normalized, '..') || str_starts_with($normalized, '/')) {
                    $zip->close();
                    File::deleteDirectory($tempDir);

                    return ['ok' => false, 'error' => 'Path traversal detectado na entrada: '.$entry];
                }
            }

            $zip->extractTo($tempDir);
            $zip->close();

            $sourceDir = $this->resolveAddonRoot($tempDir);
            if (! $sourceDir) {
                File::deleteDirectory($tempDir);

                return ['ok' => false, 'error' => 'Pacote sem module.json ou addon.json valido.'];
            }

            $targetValidation = $this->validateExtractionTarget($extractTo);
            if (! $targetValidation['ok']) {
                File::deleteDirectory($tempDir);

                return $targetValidation;
            }

            $validation = $this->validateInstall($sourceDir, true);
            if (! $validation['ok']) {
                File::deleteDirectory($tempDir);

                return $validation;
            }

            File::moveDirectory($sourceDir, $extractTo, true);
            File::deleteDirectory($tempDir);

            return ['ok' => true, 'message' => 'Addon extraido e validado com sucesso.'];
        } catch (\Throwable $e) {
            File::deleteDirectory($tempDir);

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    protected function checkPathTraversal(string $path, bool $allowSandboxPath = false): array
    {
        if (str_contains($path, '..')) {
            return ['ok' => false, 'error' => 'Path traversal detectado no caminho do addon.'];
        }

        $realPath = realpath($path);
        if ($realPath === false) {
            return ['ok' => false, 'error' => 'Caminho do addon nao existe.'];
        }

        $basePath = realpath(base_path());
        if (! str_starts_with($realPath, $basePath)) {
            return ['ok' => false, 'error' => 'Caminho do addon fora do diretorio base do sistema.'];
        }

        $addonPath = realpath(app_path('Addons'));
        if ($allowSandboxPath) {
            $sandboxPath = realpath(storage_path('app/addons_sandbox'));
            if ($sandboxPath && str_starts_with($realPath, $sandboxPath)) {
                return ['ok' => true];
            }
        }

        if ($addonPath && ! str_starts_with($realPath, $addonPath) && ! str_starts_with($realPath, realpath(app_path('Modules')))) {
            return ['ok' => false, 'error' => 'Addon deve estar em app/Addons ou app/Modules.'];
        }

        return ['ok' => true];
    }

    protected function validateExtractionTarget(string $extractTo): array
    {
        if (str_contains($extractTo, '..')) {
            return ['ok' => false, 'error' => 'Path traversal detectado no destino do addon.'];
        }

        $targetParent = realpath(dirname($extractTo));
        if ($targetParent === false) {
            return ['ok' => false, 'error' => 'Diretorio de destino do addon nao existe.'];
        }

        $allowedRoots = array_filter([
            realpath(app_path('Addons')),
            realpath(app_path('Modules')),
        ]);

        foreach ($allowedRoots as $root) {
            if (str_starts_with($targetParent, $root)) {
                return ['ok' => true];
            }
        }

        return ['ok' => false, 'error' => 'Destino do addon deve estar em app/Addons ou app/Modules.'];
    }

    protected function resolveAddonRoot(string $extractDir): ?string
    {
        if (File::exists($extractDir.DIRECTORY_SEPARATOR.'module.json') || File::exists($extractDir.DIRECTORY_SEPARATOR.'addon.json')) {
            return $extractDir;
        }

        foreach (File::directories($extractDir) as $dir) {
            if (File::exists($dir.DIRECTORY_SEPARATOR.'module.json') || File::exists($dir.DIRECTORY_SEPARATOR.'addon.json')) {
                return $dir;
            }
        }

        return null;
    }

    protected function checkCoreOverwrite(string $path): array
    {
        foreach (File::allFiles($path) as $file) {
            $relativePath = str_replace('\\', '/', $file->getRelativePathname());

            foreach ($this->protectedPaths as $protected) {
                if (str_starts_with($relativePath, $protected)) {
                    return [
                        'ok' => false,
                        'error' => "Addon tenta sobrescrever area protegida: {$relativePath}",
                    ];
                }
            }
        }

        return ['ok' => true];
    }

    protected function checkEnvAccess(string $path): array
    {
        foreach (File::allFiles($path) as $file) {
            $filename = strtolower($file->getFilename());
            if (in_array($filename, ['.env', '.env.example', '.env.production'], true)) {
                return [
                    'ok' => false,
                    'error' => "Addon contem arquivo .env proibido: {$file->getFilename()}",
                ];
            }

            $content = $file->getContents();
            if (preg_match('/APP_KEY|DB_PASSWORD|META_ACCESS_TOKEN|MERCADOPAGO_ACCESS_TOKEN/i', $content)) {
                Log::warning('[AddonSecurity] Possivel vazamento de credenciais', [
                    'file' => $file->getPathname(),
                ]);
            }
        }

        return ['ok' => true];
    }

    protected function calculateDirectoryChecksum(string $path): string
    {
        $files = File::allFiles($path);
        $hashes = [];

        foreach ($files as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $hashes[] = $relative.'|'.hash_file('sha256', $file->getPathname());
        }

        sort($hashes);

        return hash('sha256', implode("\n", $hashes));
    }

    protected function loadManifest(string $path): ?array
    {
        foreach (['module.json', 'addon.json'] as $file) {
            $manifestPath = $path.DIRECTORY_SEPARATOR.$file;
            if (File::exists($manifestPath)) {
                $data = json_decode(File::get($manifestPath), true);

                return is_array($data) ? $data : null;
            }
        }

        return null;
    }
}
