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
  const PHP = '/opt/cpanel/ea-php84/root/usr/bin/php';
  const command = [
    'cd ~/public_html',
    'echo === OPCACHE ===',
    PHP + " -r 'echo function_exists(\"opcache_get_status\") ? (opcache_get_status(false)[\"opcache_enabled\"] ? \"[WARN] OPcache ATIVO\\n\" : \"[OK] OPcache desativado\\n\") : \"[INFO] OPcache nao disponivel\\n\";'",
    'echo === LITECACHE ===',
    'if [ -d ~/public_html/lscache ]; then echo "[WARN] LiteSpeed cache existe"; ls ~/public_html/lscache | head -5; else echo "[OK] Sem LiteSpeed cache"; fi',
    'echo === DONE ==='
  ].join(' && ');

  conn.exec(command, (err, stream) => {
    if (err) { console.error(err); conn.end(); return; }
    stream.on('close', () => conn.end()).on('data', d => process.stdout.write(d.toString())).stderr.on('data', d => process.stderr.write(d.toString()));
  });
}).connect({
  host: process.env.DEPLOY_HOST || 'whatsapp.km.site.nom.br',
  port: Number(process.env.DEPLOY_PORT || 1979),
  username: process.env.DEPLOY_USER || 'whatsappsite',
  password: process.env.DEPLOY_PASSWORD || '',
  privateKey: fs.existsSync(keyPath) ? fs.readFileSync(keyPath, 'utf8') : undefined
});
