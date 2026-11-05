const { Client } = require('ssh2');
const fs = require('fs');
const path = require('path');

// Carrega .env manualmente
const envPath = path.join(__dirname, '.env');
if (fs.existsSync(envPath)) {
  const lines = fs.readFileSync(envPath, 'utf8').split(/\r?\n/);
  for (const line of lines) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) continue;
    const eq = trimmed.indexOf('=');
    if (eq > 0) {
      const key = trimmed.slice(0, eq).trim();
      let val = trimmed.slice(eq + 1).trim();
      if ((val.startsWith('"') && val.endsWith('"')) || (val.startsWith("'") && val.endsWith("'"))) {
        val = val.slice(1, -1);
      }
      if (!process.env[key]) {
        process.env[key] = val;
      }
    }
  }
}

const keyFile = path.join(__dirname, '..', '.ssh', 'deploy_whatsapp');
const keyPath = fs.existsSync(keyFile) ? keyFile : path.join(require('os').homedir(), '.ssh', 'deploy_whatsapp');

const conn = new Client();
conn.on('ready', () => {
  console.log('✓ Conectado ao servidor remoto');

  const PHP = '/opt/cpanel/ea-php84/root/usr/bin/php';
  const command = [
    'cd ~/public_html',
    'git fetch origin main',
    'git reset --hard origin/main',
    'rm -rf storage/framework/views/*.php 2>/dev/null; echo "View cache removido"',
    'rm -rf bootstrap/cache/*.php 2>/dev/null; echo "Bootstrap cache removido"',
    PHP + ' artisan config:clear',
    PHP + ' artisan cache:clear',
    PHP + ' artisan view:clear',
    PHP + ' artisan route:clear',
    PHP + ' artisan event:clear',
    PHP + ' artisan optimize:clear',
    PHP + ' artisan migrate --force',
    PHP + ' artisan storage:link --force 2>/dev/null || echo "storage:link skipped"',
    'killall -u whatsappsite lsphp 2>/dev/null || true',
    'touch storage/framework/restart 2>/dev/null || true',
    PHP + ' test_search_customers.php',
    'echo "=== DEPLOY CONCLUIDO COM SUCESSO ==="'
  ].join(' && ');

  conn.exec(command, (err, stream) => {
    if (err) {
      console.error('✗ Erro ao executar comando:', err);
      conn.end();
      return;
    }

    stream.on('close', (code, signal) => {
      console.log('\n========================================');
      console.log('Deploy finalizado!');
      console.log('Código de saída:', code);
      console.log('========================================');
      conn.end();
    }).on('data', (data) => {
      console.log('STDOUT:', data.toString());
    }).stderr.on('data', (data) => {
      console.log('STDERR:', data.toString());
    });
  });
}).on('error', (err) => {
  console.error('✗ Erro de conexão SSH:', err.message);
}).connect({
  host: process.env.DEPLOY_HOST || 'whatsapp.km.site.nom.br',
  port: Number(process.env.DEPLOY_PORT || 1979),
  username: process.env.DEPLOY_USER || 'whatsappsite',
  password: process.env.DEPLOY_PASSWORD || '',
  privateKey: fs.existsSync(keyPath) ? fs.readFileSync(keyPath, 'utf8') : undefined
});
