const { Client } = require('ssh2');
const fs = require('fs');

const envPath = '.env';
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

const conn = new Client();
conn.on('ready', () => {
  conn.exec('tail -n 100 public_html/storage/logs/laravel.log', (err, stream) => {
    if (err) throw err;
    stream.on('close', () => conn.end()).on('data', (data) => console.log(data.toString()));
  });
}).connect({ host: process.env.DEPLOY_HOST, port: parseInt(process.env.DEPLOY_PORT), username: process.env.DEPLOY_USER, password: process.env.DEPLOY_PASSWORD });
