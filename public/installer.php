<?php
/**
 * Instalador standalone - PHP puro, sem dependencia do Laravel.
 * Acesse: https://seusite.com/installer.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');

$basePath = dirname(__DIR__);

// Ja instalado?
if (file_exists($basePath . '/storage/installed.lock')) {
    header('Location: /admin/login');
    exit;
}

$step = $_GET['step'] ?? '1';
$error = '';
$success = '';

// ========== PROCESSAR POST ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'test_db') {
        try {
            $dsn = "mysql:host={$_POST['db_host']};port={$_POST['db_port']};dbname={$_POST['db_database']}";
            $pdo = new PDO($dsn, $_POST['db_username'], $_POST['db_password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();
            $success = "Conexao OK! MySQL $version";
            // Salvar credenciais na sessao
            session_start();
            $_SESSION['db'] = [
                'host' => $_POST['db_host'],
                'port' => $_POST['db_port'],
                'database' => $_POST['db_database'],
                'username' => $_POST['db_username'],
                'password' => $_POST['db_password'],
            ];
        } catch (Throwable $e) {
            $error = "Falha: " . $e->getMessage();
        }
        $step = '2';
    }

    if ($action === 'save_config') {
        session_start();
        $db = $_SESSION['db'] ?? $_POST;
        // Atualizar .env
        $envPath = $basePath . '/.env';
        if (is_file($envPath)) {
            $env = file_get_contents($envPath);
        } else {
            $env = file_get_contents($basePath . '/.env.example');
        }
        $replacements = [
            'APP_NAME' => '"' . ($_POST['app_name'] ?? 'Cobranca WhatsApp') . '"',
            'APP_URL' => $_POST['app_url'] ?? ('https://' . $_SERVER['HTTP_HOST']),
            'DB_HOST' => $db['host'] ?? $db['db_host'] ?? 'localhost',
            'DB_PORT' => $db['port'] ?? $db['db_port'] ?? '3306',
            'DB_DATABASE' => $db['database'] ?? $db['db_database'] ?? '',
            'DB_USERNAME' => $db['username'] ?? $db['db_username'] ?? '',
            'DB_PASSWORD' => $db['password'] ?? $db['db_password'] ?? '',
        ];
        foreach ($replacements as $key => $val) {
            $env = preg_replace("/^{$key}=.*/m", "{$key}={$val}", $env);
        }
        file_put_contents($envPath, $env);
        $success = "Configuracoes salvas!";
        $step = '4';
    }

    if ($action === 'install') {
        session_start();
        $pwd = $_POST['admin_password'] ?? '';
        $conf = $_POST['admin_password_confirmation'] ?? '';

        if ($pwd !== $conf) { $error = 'As senhas nao conferem.'; $step = '4'; }
        elseif (strlen($pwd) < 8) { $error = 'Senha deve ter pelo menos 8 caracteres.'; $step = '4'; }
        else {
            try {
                // Ler credenciais do .env (ja salvo no step 3)
                $envContent = file_get_contents($basePath . '/.env');
                preg_match('/^DB_HOST=(.*)$/m', $envContent, $m); $dbHost = trim($m[1] ?? 'localhost');
                preg_match('/^DB_PORT=(.*)$/m', $envContent, $m); $dbPort = trim($m[1] ?? '3306');
                preg_match('/^DB_DATABASE=(.*)$/m', $envContent, $m); $dbName = trim($m[1] ?? '');
                preg_match('/^DB_USERNAME=(.*)$/m', $envContent, $m); $dbUser = trim($m[1] ?? '');
                preg_match('/^DB_PASSWORD=(.*)$/m', $envContent, $m); $dbPass = trim($m[1] ?? '');

                // Rodar artisan migrate
                $php = PHP_BINARY ?: 'php';
                exec("cd {$basePath} && {$php} artisan migrate --force 2>&1", $output, $code);

                // Rodar migrations dos modulos
                $moduleDirs = glob($basePath . '/app/Modules/*/database/migrations');
                foreach ($moduleDirs as $dir) {
                    $relPath = str_replace($basePath . '/', '', $dir);
                    exec("cd {$basePath} && {$php} artisan migrate --force --path={$relPath} 2>&1", $out2, $c2);
                }

                // Seed (regras WAF, settings, etc)
                exec("cd {$basePath} && {$php} artisan db:seed --force 2>&1", $out3, $c3);

                // Conectar ao banco
                $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName}";
                $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

                // Remover admin padrao criado pelo seeder
                $pdo->exec("DELETE FROM admin_users WHERE email = 'admin@exemplo.com'");

                // Criar admin definido pelo usuario
                $hash = password_hash($pwd, PASSWORD_BCRYPT);
                $now = date('Y-m-d H:i:s');
                $stmt = $pdo->prepare("INSERT INTO admin_users (name, email, password, role, active, created_at, updated_at) VALUES (?, ?, ?, 'superadmin', 1, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), password=VALUES(password), updated_at=VALUES(updated_at)");
                $stmt->execute([$_POST['admin_name'], $_POST['admin_email'], $hash, $now, $now]);

                // Gerar APP_KEY se nao existir
                exec("cd {$basePath} && {$php} artisan key:generate --force 2>&1");

                // Limpar caches
                exec("cd {$basePath} && {$php} artisan optimize:clear 2>&1");

                // Marcar como instalado
                file_put_contents($basePath . '/storage/installed.lock', $now);

                $step = 'done';
            } catch (Throwable $e) {
                $error = $e->getMessage();
                $step = '4';
            }
        }
    }
}

// ========== VERIFICAR REQUISITOS ==========
function checkReqs($basePath) {
    return [
        ['PHP >= 8.2', version_compare(PHP_VERSION, '8.2.0', '>='), PHP_VERSION],
        ['PDO MySQL', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? 'OK' : 'Faltando'],
        ['Mbstring', extension_loaded('mbstring'), extension_loaded('mbstring') ? 'OK' : 'Faltando'],
        ['OpenSSL', extension_loaded('openssl'), extension_loaded('openssl') ? 'OK' : 'Faltando'],
        ['cURL', extension_loaded('curl'), extension_loaded('curl') ? 'OK' : 'Faltando'],
        ['JSON', extension_loaded('json'), extension_loaded('json') ? 'OK' : 'Faltando'],
        ['Fileinfo', extension_loaded('fileinfo'), extension_loaded('fileinfo') ? 'OK' : 'Faltando'],
        ['GD ou Imagick', extension_loaded('gd') || extension_loaded('imagick'), extension_loaded('gd') ? 'GD' : (extension_loaded('imagick') ? 'Imagick' : 'Faltando')],
        ['storage/ gravavel', is_writable($basePath.'/storage'), is_writable($basePath.'/storage') ? 'OK' : 'Sem permissao'],
        ['.env existe', is_file($basePath.'/.env'), is_file($basePath.'/.env') ? 'OK' : 'Copie .env.example para .env'],
    ];
}

$checks = checkReqs($basePath);
$allOk = true;
foreach ($checks as $c) { if (!$c[1]) $allOk = false; }

// Sessao para manter dados entre steps
if ($step !== '1') { session_start(); }
$db = $_SESSION['db'] ?? [];
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Instalador - Cobranca WhatsApp</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
body{background:linear-gradient(135deg,#0d6efd,#6610f2);min-height:100vh;display:flex;align-items:center;padding:1rem 0}
.wc{max-width:700px;margin:auto;border-radius:1rem}
.ci{display:flex;justify-content:space-between;align-items:center;padding:.5rem 0;border-bottom:1px solid #eee}
.dots{display:flex;gap:.25rem;margin-top:1rem}
.dots span{width:2.5rem;height:4px;border-radius:2px;background:rgba(0,0,0,.1)}
.dots span.d{background:#198754}
.dots span.a{background:#0d6efd}
</style>
</head>
<body>
<div class="container">
<div class="card wc shadow-lg">
<div class="card-header bg-white p-4">
<div class="d-flex align-items-center gap-3">
<i class="bi bi-whatsapp text-success fs-2"></i>
<div><h4 class="m-0">Instalador do Sistema</h4><small class="text-muted">Passo <?php echo $step==='done'?'5':$step; ?> de 5</small></div>
</div>
<div class="dots"><?php $s=$step==='done'?5:(int)$step; for($i=1;$i<=5;$i++){echo '<span class="'.($i<$s?'d':($i===$s?'a':'')).'"></span>';} ?></div>
</div>
<div class="card-body p-4">
<?php if($error):?><div class="alert alert-danger small"><i class="bi bi-x-circle"></i> <?php echo htmlspecialchars($error);?></div><?php endif;?>
<?php if($success):?><div class="alert alert-success small"><i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success);?></div><?php endif;?>

<?php if($step==='1'):?>
<h5><i class="bi bi-gear-wide-connected"></i> Verificacao de requisitos</h5>
<p class="text-muted small">Verificando se o servidor atende aos requisitos minimos.</p>
<?php foreach($checks as $c):?>
<div class="ci"><span><?php echo $c[0];?></span><span><?php echo $c[1]?'<i class="bi bi-check-circle-fill text-success"></i> '.$c[2]:'<i class="bi bi-x-circle-fill text-danger"></i> '.$c[2];?></span></div>
<?php endforeach;?>
<?php if($allOk):?>
<div class="text-end mt-3"><a href="?step=2" class="btn btn-primary">Proximo <i class="bi bi-arrow-right"></i></a></div>
<?php else:?>
<div class="alert alert-danger mt-3 small">Corrija os itens em vermelho.</div>
<?php endif;?>

<?php elseif($step==='2'):?>
<h5><i class="bi bi-database"></i> Banco de dados</h5>
<p class="text-muted small">Informe os dados MySQL.</p>
<form method="POST">
<input type="hidden" name="action" value="test_db">
<div class="row g-3">
<div class="col-8"><label class="form-label">Host</label><input type="text" name="db_host" class="form-control" value="<?php echo htmlspecialchars($db['host']??'localhost');?>"></div>
<div class="col-4"><label class="form-label">Porta</label><input type="number" name="db_port" class="form-control" value="<?php echo htmlspecialchars($db['port']??'3306');?>"></div>
<div class="col-12"><label class="form-label">Nome do banco</label><input type="text" name="db_database" class="form-control" value="<?php echo htmlspecialchars($db['database']??'');?>" required></div>
<div class="col-6"><label class="form-label">Usuario</label><input type="text" name="db_username" class="form-control" value="<?php echo htmlspecialchars($db['username']??'');?>" required></div>
<div class="col-6"><label class="form-label">Senha</label><input type="password" name="db_password" class="form-control" value="<?php echo htmlspecialchars($db['password']??'');?>"></div>
</div>
<div class="d-flex justify-content-between mt-3">
<a href="?step=1" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
<div class="d-flex gap-2">
<button type="submit" class="btn btn-outline-primary"><i class="bi bi-plug"></i> Testar</button>
<?php if($success):?><a href="?step=3" class="btn btn-primary">Proximo <i class="bi bi-arrow-right"></i></a><?php endif;?>
</div>
</div>
</form>

<?php elseif($step==='3'):?>
<h5><i class="bi bi-sliders"></i> Configuracoes</h5>
<form method="POST">
<input type="hidden" name="action" value="save_config">
<div class="row g-3">
<div class="col-12"><label class="form-label">Nome do sistema</label><input type="text" name="app_name" class="form-control" value="Cobranca WhatsApp"></div>
<div class="col-12"><label class="form-label">URL do site</label><input type="url" name="app_url" class="form-control" value="<?php echo(isset($_SERVER['HTTPS'])?'https':'http').'://'.$_SERVER['HTTP_HOST'];?>"></div>
</div>
<div class="d-flex justify-content-between mt-3">
<a href="?step=2" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
<button type="submit" class="btn btn-primary">Salvar e continuar <i class="bi bi-arrow-right"></i></button>
</div>
</form>

<?php elseif($step==='4'):?>
<h5><i class="bi bi-person-badge"></i> Criar superadmin</h5>
<p class="text-muted small">Usuario com acesso total ao sistema.</p>
<form method="POST">
<input type="hidden" name="action" value="install">
<div class="row g-3">
<div class="col-12"><label class="form-label">Nome</label><input type="text" name="admin_name" class="form-control" required></div>
<div class="col-12"><label class="form-label">E-mail</label><input type="email" name="admin_email" class="form-control" required></div>
<div class="col-6"><label class="form-label">Senha (min 8)</label><input type="password" name="admin_password" class="form-control" minlength="8" required></div>
<div class="col-6"><label class="form-label">Confirmar</label><input type="password" name="admin_password_confirmation" class="form-control" required></div>
</div>
<div class="d-flex justify-content-between mt-3">
<a href="?step=3" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Voltar</a>
<button type="submit" class="btn btn-success"><i class="bi bi-rocket-takeoff"></i> Instalar</button>
</div>
</form>

<?php elseif($step==='done'):?>
<div class="text-center py-4">
<i class="bi bi-check-circle-fill text-success" style="font-size:4rem;"></i>
<h4 class="mt-3 text-success">Instalado com sucesso!</h4>
<p class="text-muted">Sistema pronto para uso.</p>
<a href="/admin/login" class="btn btn-primary btn-lg mt-2"><i class="bi bi-box-arrow-in-right"></i> Acessar painel</a>
</div>
<?php endif;?>

</div>
</div>
</div>
</body>
</html>
