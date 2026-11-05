const { Client } = require('ssh2');
const fs = require('fs');
const path = require('path');

const envContent = fs.readFileSync('.env', 'utf8');
const env = {};
envContent.split(/\r?\n/).forEach(line => {
  const t = line.trim();
  if (t && !t.startsWith('#')) {
    const idx = t.indexOf('=');
    if (idx > 0) {
      env[t.slice(0, idx).trim()] = t.slice(idx + 1).trim().replace(/^['"]|['"]$/g, '');
    }
  }
});

const keyFile = path.join(__dirname, '..', '.ssh', 'deploy_whatsapp');
const keyPath = fs.existsSync(keyFile) ? keyFile : path.join(require('os').homedir(), '.ssh', 'deploy_whatsapp');

const conn = new Client();
conn.on('ready', () => {
  const cmd = "cd ~/public_html && tail -n 80 storage/logs/laravel.log 2>/dev/null || echo 'Sem log'";
  conn.exec(cmd, (err, stream) => {
    if (err) throw err;
    stream.on('data', d => process.stdout.write(d));
    stream.stderr.on('data', d => process.stderr.write(d));
    stream.on('close', () => {
      conn.end();
      fs.unlinkSync('temp_check_logs.js');
    });
  });
}).connect({
  host: env.DEPLOY_HOST,
  port: parseInt(env.DEPLOY_PORT || '22'),
  username: env.DEPLOY_USER,
  privateKey: fs.readFileSync(keyPath)
});

