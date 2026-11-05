<?php

namespace App\Modules\Installer\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

/**
 * Instalador web - wizard em 5 etapas:
 *   1. Verificacao de requisitos do servidor
 *   2. Configuracao do banco de dados
 *   3. Criacao do superadmin (dono do sistema)
 *   4. Execucao de migrations + seeders
 *   5. Finalizacao (grava installed.lock)
 *
 * CRUD 100% AJAX sem refresh.
 */
class InstallerController extends Controller
{
    public function index()
    {
        return view('installer::wizard');
    }

    /** Step 1: Verifica requisitos do servidor */
    public function checkRequirements(): JsonResponse
    {
        $checks = [
            'php_version' => [
                'label'    => 'PHP >= 8.4',
                'ok'       => version_compare(PHP_VERSION, '8.4.0', '>='),
                'current'  => PHP_VERSION,
            ],
            'pdo_mysql' => [
                'label'   => 'Extensao PDO MySQL',
                'ok'      => extension_loaded('pdo_mysql'),
                'current' => extension_loaded('pdo_mysql') ? 'Instalada' : 'Ausente',
            ],
            'mbstring' => [
                'label'   => 'Extensao Mbstring',
                'ok'      => extension_loaded('mbstring'),
                'current' => extension_loaded('mbstring') ? 'Instalada' : 'Ausente',
            ],
            'openssl' => [
                'label'   => 'Extensao OpenSSL',
                'ok'      => extension_loaded('openssl'),
                'current' => extension_loaded('openssl') ? 'Instalada' : 'Ausente',
            ],
            'curl' => [
                'label'   => 'Extensao cURL',
                'ok'      => extension_loaded('curl'),
                'current' => extension_loaded('curl') ? 'Instalada' : 'Ausente',
            ],
            'json' => [
                'label'   => 'Extensao JSON',
                'ok'      => extension_loaded('json'),
                'current' => extension_loaded('json') ? 'Instalada' : 'Ausente',
            ],
            'fileinfo' => [
                'label'   => 'Extensao Fileinfo',
                'ok'      => extension_loaded('fileinfo'),
                'current' => extension_loaded('fileinfo') ? 'Instalada' : 'Ausente',
            ],
            'gd_or_imagick' => [
                'label'   => 'GD ou Imagick',
                'ok'      => extension_loaded('gd') || extension_loaded('imagick'),
                'current' => extension_loaded('gd') ? 'GD' : (extension_loaded('imagick') ? 'Imagick' : 'Ausente'),
            ],
            'storage_writable' => [
                'label'   => 'storage/ gravavel',
                'ok'      => is_writable(storage_path()),
                'current' => is_writable(storage_path()) ? 'OK' : 'Sem permissao',
            ],
            'env_writable' => [
                'label'   => '.env gravavel',
                'ok'      => is_writable(base_path('.env')) || is_writable(base_path()),
                'current' => (is_writable(base_path('.env')) || is_writable(base_path())) ? 'OK' : 'Sem permissao',
            ],
        ];

        $allOk = collect($checks)->every(fn ($c) => $c['ok']);

        return response()->json([
            'ok'     => $allOk,
            'checks' => $checks,
        ]);
    }

    /** Step 2: Testa conexao com o banco */
    public function testDatabase(Request $request): JsonResponse
    {
        $data = $request->validate([
            'db_host'     => 'required|string',
            'db_port'     => 'required|integer',
            'db_database' => 'required|string',
            'db_username' => 'required|string',
            'db_password' => 'nullable|string',
        ]);

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $data['db_host'], $data['db_port'], $data['db_database']
            );
            $pdo = new \PDO($dsn, $data['db_username'], $data['db_password'] ?? '', [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();
            return response()->json(['ok' => true, 'version' => $version]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** Step 3: Salva .env com dados do banco e config geral */
    public function saveConfig(Request $request): JsonResponse
    {
        $data = $request->validate([
            'app_name'    => 'required|string|max:120',
            'app_url'     => 'required|url',
            'db_host'     => 'required|string',
            'db_port'     => 'required|integer',
            'db_database' => 'required|string',
            'db_username' => 'required|string',
            'db_password' => 'nullable|string',
        ]);

        $envPath = base_path('.env');
        $envContent = file_exists($envPath) ? file_get_contents($envPath) : file_get_contents(base_path('.env.example'));

        $replacements = [
            'APP_NAME'    => '"' . $data['app_name'] . '"',
            'APP_URL'     => $data['app_url'],
            'APP_KEY'     => 'base64:' . base64_encode(random_bytes(32)),
            'DB_HOST'     => $data['db_host'],
            'DB_PORT'     => (string) $data['db_port'],
            'DB_DATABASE' => $data['db_database'],
            'DB_USERNAME' => $data['db_username'],
            'DB_PASSWORD' => $data['db_password'] ?? '',
            'CRON_EXTERNAL_TOKEN' => bin2hex(random_bytes(16)),
        ];

        foreach ($replacements as $key => $value) {
            $pattern = '/^' . preg_quote($key, '/') . '=.*/m';
            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $key . '=' . $value, $envContent);
            } else {
                $envContent .= "\n" . $key . '=' . $value;
            }
        }

        File::put($envPath, $envContent);

        // Recarrega config
        Artisan::call('config:clear');

        return response()->json(['ok' => true]);
    }

    /** Step 4: Roda migrations + seeders */
    public function runMigrations(): JsonResponse
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            $migrateOutput = Artisan::output();

            Artisan::call('db:seed', ['--force' => true]);
            $seedOutput = Artisan::output();

            return response()->json([
                'ok'        => true,
                'migrate'   => $migrateOutput,
                'seed'      => $seedOutput,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** Step 5: Cria o superadmin (dono do sistema) */
    public function createSuperAdmin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:120',
            'email'    => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {
            // Remove admin padrao do seeder se existir
            DB::table('admin_users')->where('email', 'admin@exemplo.com')->delete();

            $id = DB::table('admin_users')->insertGetId([
                'name'       => $data['name'],
                'email'      => $data['email'],
                'password'   => Hash::make($data['password']),
                'role'       => 'superadmin',
                'active'     => true,
                'theme_dark' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Atribui role superadmin se a tabela de roles ja existir
            try {
                $roleId = DB::table('roles')->where('slug', 'superadmin')->value('id');
                if ($roleId) {
                    DB::table('admin_user_roles')->updateOrInsert(
                        ['admin_user_id' => $id, 'role_id' => $roleId],
                        ['created_at' => now()]
                    );
                }
            } catch (\Throwable) {}

            return response()->json(['ok' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /** Step 6: Finaliza instalacao */
    public function finalize(): JsonResponse
    {
        // Grava lock para impedir re-instalacao
        File::put(storage_path('installed.lock'), json_encode([
            'installed_at' => now()->toIso8601String(),
            'php_version'  => PHP_VERSION,
            'laravel'      => app()->version(),
        ], JSON_PRETTY_PRINT));

        // Limpa caches
        try {
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
        } catch (\Throwable) {}

        return response()->json([
            'ok'       => true,
            'redirect' => '/admin/login',
            'message'  => 'Sistema instalado com sucesso!',
        ]);
    }
}
