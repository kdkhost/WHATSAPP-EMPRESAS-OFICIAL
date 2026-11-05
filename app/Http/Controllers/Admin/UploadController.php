<?php

/**
 * @autor marcelo-brad rj
 *
 * @contato Tel: 21 981325441
 * @contato Email: contato@kdkhost.com.br
 * @contato Telegram: @MARCELO_BRAD
 * @contato Instagram: @marcelobradrj
 * @contato WhatsApp: 21981325441
 *
 * @copyright Copyright (c) KDK HOST / Marcelo Brad. Todos os direitos reservados.
 *
 * @propriedade-intelectual Este arquivo integra software proprietário. É proibida a cópia,
 * distribuição, sublicenciamento, engenharia reversa, revenda, cessão, publicação ou uso
 * fora do projeto autorizado sem permissão expressa e por escrito do titular.
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ManagedAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    /** Extensões absolutamente proibidas (executáveis e scripts). */
    protected array $blockedExtensions = [
        'php', 'php3', 'php4', 'php5', 'php6', 'php7', 'phtml', 'phar', 'pht',
        'jsp', 'jspx', 'asp', 'aspx', 'asa', 'asax', 'ascx', 'ashx', 'asmx',
        'cgi', 'exe', 'sh', 'bash', 'bat', 'cmd', 'com', 'msi', 'ps1', 'psm1',
        'py', 'pl', 'rb', 'lua', 'go', 'jar', 'war', 'ear', 'class',
    ];

    /** MIMEs permitidos por contexto. */
    protected array $allowedMimes = [
        'branding' => [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'image/svg+xml', 'image/x-icon', 'image/vnd.microsoft.icon',
        ],
        'seo' => [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'image/svg+xml',
        ],
        'pwa' => [
            'image/jpeg', 'image/png', 'image/webp',
            'image/x-icon', 'image/vnd.microsoft.icon',
        ],
        'avatars' => [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        ],
        'contracts' => [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'image/svg+xml', 'application/pdf',
        ],
        'general' => [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'image/svg+xml', 'image/x-icon', 'image/vnd.microsoft.icon',
            'application/pdf', 'text/plain', 'text/csv',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
    ];

    protected array $allowedExtensions = [
        'branding' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico'],
        'seo' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
        'pwa' => ['jpg', 'jpeg', 'png', 'webp', 'ico'],
        'avatars' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'contracts' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf'],
        'general' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'pdf', 'txt', 'csv', 'xls', 'xlsx'],
    ];

    public function store(Request $request, string $context = 'general'): JsonResponse
    {
        $maxMb = (int) config('modules.upload.max_size_mb', 50);

        $request->validate([
            'file' => 'required|file|max:'.($maxMb * 1024),
        ]);

        $file = $request->file('file');
        $replaceUrl = $request->input('replace_url');

        // 1. Bloquear extensões perigosas (dupla extensão inclusa)
        $originalName = $file->getClientOriginalName();
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $allowedExtensions = $this->allowedExtensions[$context] ?? $this->allowedExtensions['general'];
        if ($ext === '' || ! in_array($ext, $allowedExtensions, true)) {
            return response()->json([
                'ok' => false,
                'error' => 'Extensao de arquivo nao permitida.',
                'message' => 'Extensao de arquivo nao permitida para este contexto.',
            ], 422);
        }

        if (in_array($ext, $this->blockedExtensions, true)) {
            return response()->json([
                'ok' => false,
                'error' => 'Tipo de arquivo não permitido.',
                'message' => 'Tipo de arquivo não permitido.',
            ], 422);
        }

        // Bloquear dupla extensão perigosa (ex: shell.php.jpg)
        $nameParts = explode('.', $originalName);
        foreach ($nameParts as $part) {
            if (in_array(strtolower($part), $this->blockedExtensions, true)) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Nome de arquivo inválido.',
                    'message' => 'Nome de arquivo inválido.',
                ], 422);
            }
        }

        // 2. Validar MIME real (não confiar apenas na extensão)
        $realMime = $file->getMimeType();
        $allowed = $this->allowedMimes[$context] ?? $this->allowedMimes['general'];

        if (! in_array($realMime, $allowed, true)) {
            return response()->json([
                'ok' => false,
                'error' => 'Tipo de arquivo não suportado neste contexto.',
                'message' => 'Tipo de arquivo não suportado. Tipos aceitos: JPG, PNG, GIF, WebP, SVG.',
            ], 422);
        }

        // 3. Gerar nome seguro (nunca usar getClientOriginalName como nome final)
        $safeName = Str::uuid().'.'.$ext;
        $disk = 'public';
        $path = $file->storeAs("uploads/{$context}", $safeName, $disk);

        // Garantir que o link simbólico existe
        if (! is_link(public_path('storage'))) {
            try {
                Artisan::call('storage:link');
            } catch (\Throwable) {
            }
        }

        $url = Storage::disk($disk)->url($path);

        if (is_string($replaceUrl) && $replaceUrl !== '' && $replaceUrl !== $url) {
            ManagedAsset::deleteByUrl($replaceUrl);
        }

        // 4. Registrar no banco com nome original apenas como metadado
        try {
            $id = DB::table('uploads')->insertGetId([
                'owner_type' => $request->input('owner_type') ?? $context,
                'owner_id' => (int) $request->input('owner_id', 0),
                'disk' => $disk,
                'path' => $path,
                'original_name' => substr($originalName, 0, 255),
                'mime' => $realMime,
                'size_bytes' => $file->getSize(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable) {
            $id = null;
        }

        return response()->json([
            'ok' => true,
            'id' => $id,
            'url' => $url,
            'preview_url' => str_starts_with($realMime, 'image/') ? $url : null,
            'name' => $originalName,
            'mime' => $realMime,
            'size' => $file->getSize(),
            'size_human' => $this->humanSize((int) $file->getSize()),
        ]);
    }

    protected function humanSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1, ',', '.').' KB';
        }

        return number_format($bytes / 1048576, 1, ',', '.').' MB';
    }
}
