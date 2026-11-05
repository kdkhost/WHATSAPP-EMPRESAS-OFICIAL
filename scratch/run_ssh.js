const { Client } = require('ssh2');
const fs = require('fs');
const path = require('path');

const envPath = path.join(__dirname, '..', '.env');
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

const keyPath = path.join(require('os').homedir(), '.ssh', 'deploy_whatsapp');

const phpScript = fs.readFileSync(path.join(__dirname, 'test_kpi2.php'), 'utf8');

const conn = new Client();
conn.on('ready', () => {
  const PHP = '/opt/cpanel/ea-php84/root/usr/bin/php';
  
  // Create the script on the server and then run it
  const command = `
    cd ~/public_html
    cat << 'EOF_SCRIPT' > scratch_whmcs_sync.php
${phpScript}
EOF_SCRIPT
    ${PHP} scratch_whmcs_sync.php
    rm scratch_whmcs_sync.php
  `;

  conn.exec(command, (err, stream) => {
    if (err) throw err;
    stream.on('close', (code, signal) => {
      conn.end();
    }).on('data', (data) => {
      process.stdout.write(data.toString());
    }).stderr.on('data', (data) => {
      process.stderr.write(data.toString());
    });
  });
}).on('error', (err) => {
  console.error('SSH Error:', err.message);
}).connect({
  host: process.env.DEPLOY_HOST || 'whatsapp.km.site.nom.br',
  port: Number(process.env.DEPLOY_PORT || 1979),
  username: process.env.DEPLOY_USER || 'whatsappsite',
  password: process.env.DEPLOY_PASSWORD || '',
  privateKey: fs.existsSync(keyPath) ? fs.readFileSync(keyPath, 'utf8') : undefined
});
