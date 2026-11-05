const { Client } = require('ssh2');
const fs = require('fs');
const path = require('path');
const os = require('os');

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
const keyPath = fs.existsSync(keyFile) ? keyFile : path.join(os.homedir(), '.ssh', 'deploy_whatsapp');

const cmd = process.argv[2] || 'uptime';

const conn = new Client();
conn.on('ready', () => {
  const fullCmd = `cd ~/public_html && ${cmd}`;
  conn.exec(fullCmd, (err, stream) => {
    if (err) {
      console.error(err);
      conn.end();
      return;
    }
    stream.on('close', (code) => {
      conn.end();
      process.exit(code);
    }).on('data', (d) => {
      process.stdout.write(d);
    }).stderr.on('data', (d) => {
      process.stderr.write(d);
    });
  });
}).on('error', err => {
  console.error(err);
  process.exit(1);
}).connect({
  host: process.env.DEPLOY_HOST || 'whatsapp.km.site.nom.br',
  port: Number(process.env.DEPLOY_PORT || 1979),
  username: process.env.DEPLOY_USER || 'whatsappsite',
  password: process.env.DEPLOY_PASSWORD || '',
  privateKey: fs.existsSync(keyPath) ? fs.readFileSync(keyPath, 'utf8') : undefined
});
