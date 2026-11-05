const { Client } = require('ssh2');
const fs = require('fs');
const path = require('path');

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
      if (!process.env[key]) process.env[key] = val;
    }
  }
}

const keyFile = path.join(__dirname, '..', '.ssh', 'deploy_whatsapp');
const keyPath = fs.existsSync(keyFile) ? keyFile : path.join(require('os').homedir(), '.ssh', 'deploy_whatsapp');

const conn = new Client();
conn.on('ready', () => {
  console.log('Conectado ao servidor remoto');

  const PHP = '/opt/cpanel/ea-php84/root/usr/bin/php';
  const command = [
    'cd ~/public_html',
    'echo "=== VERIFICANDO SE O CODIGO CHEGOU ==="',
    'grep -q "kdkhost-whatsapp-pwa-v2" app/Modules/Pwa/Http/Controllers/PwaController.php && echo "[OK] Service Worker atualizado" || echo "[ERRO] Service Worker NAO atualizado"',
    'grep -q "rawurlencode" app/Modules/WhatsApp/Services/EvolutionClient.php && echo "[OK] EvolutionClient atualizado" || echo "[ERRO] EvolutionClient NAO atualizado"',
    'echo ""',
    'echo "=== LIMPANDO TODOS OS CACHES ==="',
    PHP + ' artisan optimize:clear',
    PHP + ' artisan config:clear',
    PHP + ' artisan cache:clear',
    PHP + ' artisan view:clear',
    PHP + ' artisan route:clear',
    PHP + ' artisan event:clear',
    PHP + ' -r "if (function_exists(\\\'opcache_reset\\\')) { opcache_reset(); echo \"[OK] OPcache resetado\\n\"; } else { echo \"[INFO] OPcache nao disponivel\\n\"; }"',
    'echo ""',
    'echo "=== VERIFICANDO CACHE LITESPEED ==="',
    'if [ -d ~/public_html/lscache ]; then rm -rf ~/public_html/lscache/* && echo "[OK] LiteSpeed cache limpo"; else echo "[INFO] Sem lscache"; fi',
    'echo ""',
    'echo "=== VERSAO DO LARAVEL ==="',
    PHP + ' artisan --version',
    'echo ""',
    'echo "=== DIAGNOSTICO CONCLUIDO ==="'
  ].join(' && ');

  conn.exec(command, (err, stream) => {
    if (err) {
      console.error('Erro:', err);
      conn.end();
      return;
    }

    stream.on('close', (code) => {
      console.log('\nCodigo de saida:', code);
      conn.end();
    }).on('data', (data) => {
      process.stdout.write(data.toString());
    }).stderr.on('data', (data) => {
      process.stderr.write(data.toString());
    });
  });
}).on('error', (err) => {
  console.error('Erro SSH:', err.message);
}).connect({
  host: process.env.DEPLOY_HOST || 'whatsapp.km.site.nom.br',
  port: Number(process.env.DEPLOY_PORT || 1979),
  username: process.env.DEPLOY_USER || 'whatsappsite',
  password: process.env.DEPLOY_PASSWORD || '',
  privateKey: fs.existsSync(keyPath) ? fs.readFileSync(keyPath, 'utf8') : undefined
});
