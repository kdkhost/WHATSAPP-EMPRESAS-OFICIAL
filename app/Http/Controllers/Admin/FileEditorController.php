<?php

/**
 * @autor marcelo-brad rj
 * @contato Tel: 21 981325441
 * @contato Email: contato@kdkhost.com.br
 * @contato Telegram: @MARCELO_BRAD
 * @contato Instagram: @marcelobradrj
 * @contato WhatsApp: 21981325441
 *
 * @copyright Copyright (c) KDK HOST / Marcelo Brad. Todos os direitos reservados.
 * @propriedade-intelectual Este arquivo integra software proprietário. É proibida a cópia,
 * distribuição, sublicenciamento, engenharia reversa, revenda, cessão, publicação ou uso
 * fora do projeto autorizado sem permissão expressa e por escrito do titular.
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class FileEditorController extends Controller
{
    /** Diretórios permitidos para leitura/escrita (relativos a base_path). */
    private array $allowedPaths = [
        'resources/views',
        'frontend/src',
        'config',
        'app/Modules',
    ];

    /** Extensões permitidas para edição. */
    private array $allowedExtensions = [
        'php','json','ts','tsx','css','html','js','md','blade',
    ];

    /** Arquivos absolutamente proibidos de editar. */
    private array $blockedFiles = [
        '.env','.env.production','.env.local',
        '.htaccess',
        'composer.json','composer.lock',
        'package.json','package-lock.json',
    ];

    /** Diretórios absolutamente proibidos. */
    private array $blockedDirs = [
        'vendor','node_modules','storage','bootstrap/cache',
        '.git','.github',
    ];

    public function index()
    {
        $files = $this->getFileTree();
        return view('admin.system.file-editor', compact('files'));
    }

    public function getFileTree(): array
    {
        $tree = [];
        $base = base_path();

        foreach ($this->allowedPaths as $relPath) {
            $fullPath = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
            if (File::exists($fullPath)) {
                $tree[] = $this->buildBranch($fullPath, $base);
            }
        }

        return $tree;
    }

    private function buildBranch(string $dirPath, string $basePath): array
    {
        $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $dirPath);
        $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

        $branch = [
            'name'     => basename($dirPath),
            'path'     => $relativePath,
            'is_dir'   => true,
            'children' => [],
        ];

        foreach (File::directories($dirPath) as $item) {
            $branch['children'][] = $this->buildBranch($item, $basePath);
        }

        foreach (File::files($dirPath) as $file) {
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, $this->allowedExtensions, true)) continue;
            if (in_array($file->getFilename(), $this->blockedFiles, true)) continue;

            $filePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getRealPath());
            $filePath = str_replace(DIRECTORY_SEPARATOR, '/', $filePath);

            $branch['children'][] = [
                'name'   => $file->getFilename(),
                'path'   => $filePath,
                'is_dir' => false,
            ];
        }

        return $branch;
    }

    public function loadFile(Request $request): JsonResponse
    {
        $path = $request->input('path', '');

        $check = $this->validatePath($path);
        if ($check !== true) {
            return response()->json(['ok' => false, 'message' => $check], 403);
        }

        $fullPath = $this->resolveAndVerify($path);
        if (!$fullPath) {
            return response()->json(['ok' => false, 'message' => 'Caminho inválido ou fora da área permitida.'], 403);
        }

        if (!File::exists($fullPath)) {
            return response()->json(['ok' => false, 'message' => 'Arquivo não encontrado.'], 404);
        }

        return response()->json([
            'ok'        => true,
            'content'   => File::get($fullPath),
            'extension' => File::extension($fullPath),
        ]);
    }

    public function saveFile(Request $request): JsonResponse
    {
        $path    = $request->input('path', '');
        $content = $request->input('content', '');

        $check = $this->validatePath($path);
        if ($check !== true) {
            return response()->json(['ok' => false, 'message' => $check], 403);
        }

        $fullPath = $this->resolveAndVerify($path);
        if (!$fullPath) {
            return response()->json(['ok' => false, 'message' => 'Caminho inválido ou fora da área permitida.'], 403);
        }

        if (!File::exists($fullPath)) {
            return response()->json(['ok' => false, 'message' => 'Arquivo não encontrado.'], 404);
        }

        try {
            // Criar backup antes de salvar
            $backupDir  = storage_path('app/file-editor-backups');
            $backupName = date('Ymd_His') . '_' . md5($path) . '_' . basename($path);
            File::ensureDirectoryExists($backupDir);
            File::copy($fullPath, $backupDir . DIRECTORY_SEPARATOR . $backupName);

            File::put($fullPath, $content);

            // Registrar auditoria
            $this->auditSave($path, $request);

            return response()->json(['ok' => true, 'message' => 'Arquivo salvo com sucesso!']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Erro ao salvar: ' . $e->getMessage()], 500);
        }
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    /** Valida path: extensão, arquivo bloqueado, diretório bloqueado. */
    private function validatePath(string $path): true|string
    {
        $path = str_replace('\\', '/', $path);

        // Bloquear arquivos proibidos
        $basename = basename($path);
        if (in_array($basename, $this->blockedFiles, true)) {
            return 'Este arquivo não pode ser editado por segurança.';
        }

        // Bloquear extensão não permitida
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, $this->allowedExtensions, true)) {
            return 'Extensão não permitida.';
        }

        // Bloquear diretórios proibidos
        foreach ($this->blockedDirs as $blocked) {
            if (str_contains($path, $blocked . '/') || str_starts_with($path, $blocked)) {
                return 'Diretório não permitido.';
            }
        }

        // Verificar se está dentro de um allowedPath
        $inAllowed = false;
        foreach ($this->allowedPaths as $allowed) {
            if (str_starts_with($path, $allowed)) {
                $inAllowed = true;
                break;
            }
        }
        if (!$inAllowed) {
            return 'Caminho fora da área permitida.';
        }

        return true;
    }

    /** Resolve o path real e verifica que está dentro de base_path(). */
    private function resolveAndVerify(string $path): string|false
    {
        $base     = realpath(base_path());
        $fullPath = realpath($base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));

        if (!$fullPath || !str_starts_with($fullPath, $base . DIRECTORY_SEPARATOR)) {
            return false;
        }

        return $fullPath;
    }

    private function auditSave(string $path, Request $request): void
    {
        try {
            $admin = Auth::guard('admin')->user();
            DB::table('audit_logs')->insert([
                'admin_user_id' => $admin?->id,
                'action'        => 'file_edit',
                'subject_type'  => 'file',
                'subject_id'    => null,
                'changes'       => json_encode(['path' => $path]),
                'ip'            => $request->ip(),
                'user_agent'    => substr((string) $request->userAgent(), 0, 500),
                'created_at'    => now(),
            ]);
        } catch (\Throwable) {}
    }
}
