<?php

namespace App\Modules\LogViewer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Visualizador de logs Laravel sem precisar de SSH.
 * Apenas superadmin pode acessar.
 */
class LogViewerController extends Controller
{
    public function index()
    {
        $files = $this->listFiles();
        return view('log-viewer::index', compact('files'));
    }

    public function tail(Request $request): JsonResponse
    {
        $request->validate([
            'file'  => 'required|string',
            'lines' => 'nullable|integer|min:10|max:1000',
            'level' => 'nullable|in:emergency,alert,critical,error,warning,notice,info,debug',
        ]);

        $file = basename($request->input('file')); // sanitize
        $path = storage_path('logs/' . $file);

        if (!preg_match('/\.log$/', $file) || !is_file($path)) {
            return response()->json(['ok' => false, 'error' => 'Arquivo invalido'], 404);
        }

        $lines = (int) $request->input('lines', 200);
        $level = $request->input('level');
        $entries = $this->parseLog($path, $lines, $level);

        return response()->json([
            'ok'      => true,
            'file'    => $file,
            'size'    => filesize($path),
            'entries' => $entries,
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|string',
        ]);

        $file = basename($request->input('file'));
        $path = storage_path('logs/' . $file);
        
        // Validações de segurança
        if (!preg_match('/\.log$/', $file)) {
            return response()->json([
                'ok' => false, 
                'error' => 'Arquivo inválido'
            ], 400);
        }
        
        if (!is_file($path)) {
            return response()->json([
                'ok' => false, 
                'error' => 'Arquivo não encontrado'
            ], 404);
        }
        
        // Verificar permissões de escrita
        if (!is_writable($path)) {
            return response()->json([
                'ok' => false, 
                'error' => 'Sem permissão de escrita. Execute: chmod 666 ' . $path
            ], 403);
        }
        
        try {
            // Tentar truncar o arquivo (mais eficiente)
            $handle = fopen($path, 'w');
            if ($handle === false) {
                throw new \Exception('Não foi possível abrir o arquivo para escrita');
            }
            fclose($handle);
            
            // Verificar se realmente foi limpo
            clearstatcache(true, $path);
            $newSize = filesize($path);
            
            if ($newSize > 0) {
                throw new \Exception('O arquivo não foi completamente limpo');
            }
            
            return response()->json([
                'ok' => true,
                'message' => 'Log limpo com sucesso',
                'size' => $newSize,
                'file' => $file
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false, 
                'error' => 'Erro ao limpar: ' . $e->getMessage()
            ], 500);
        }
    }

    public function checkPermissions(Request $request): JsonResponse
    {
        $file = basename($request->input('file'));
        $path = storage_path('logs/' . $file);
        
        if (!is_file($path)) {
            return response()->json([
                'ok' => false,
                'error' => 'Arquivo não encontrado'
            ], 404);
        }
        
        $perms = fileperms($path);
        $info = [
            'file' => $file,
            'path' => $path,
            'size' => filesize($path),
            'readable' => is_readable($path),
            'writable' => is_writable($path),
            'permissions' => substr(sprintf('%o', $perms), -4),
            'owner' => function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($path))['name'] : 'N/A',
        ];
        
        return response()->json([
            'ok' => true,
            'info' => $info
        ]);
    }

    protected function listFiles(): array
    {
        $files = glob(storage_path('logs/*.log')) ?: [];
        return collect($files)->map(fn($f) => [
            'name' => basename($f),
            'size' => filesize($f),
            'size_kb' => round(filesize($f) / 1024, 2),
            'mtime' => date('Y-m-d H:i:s', filemtime($f)),
        ])->sortByDesc('mtime')->values()->all();
    }

    protected function parseLog(string $path, int $maxLines, ?string $filterLevel): array
    {
        // Le ultimas N linhas (tail)
        $size = filesize($path);
        if ($size === 0) return [];

        $chunkSize = min(512000, $size); // 512KB max
        $fp = fopen($path, 'r');
        fseek($fp, max(0, $size - $chunkSize));
        $content = fread($fp, $chunkSize);
        fclose($fp);

        // Parse formato Laravel: [YYYY-MM-DD HH:MM:SS] env.LEVEL: message
        $pattern = '/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \w+\.(\w+): (.+?)(?=\n\[\d{4}|$)/s';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

        $entries = [];
        foreach (array_reverse($matches) as $m) {
            $level = strtolower($m[2]);
            if ($filterLevel && $level !== $filterLevel) continue;
            $entries[] = [
                'timestamp' => $m[1],
                'level'     => $level,
                'message'   => trim(mb_substr($m[3], 0, 5000)),
            ];
            if (count($entries) >= $maxLines) break;
        }

        return $entries;
    }
}
