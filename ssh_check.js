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
      if ((val.startsWith('"') && val.endsWith('"')) || (val.startsWith("'") && val.endsWith("'"))) val = val.slice(1, -1);
      if (!process.env[key]) process.env[key] = val;
    }
  }
}

const conn = new Client();
conn.on('ready', () => {
  const php = '/opt/cpanel/ea-php84/root/usr/bin/php';
  const cmd = `cd ~/public_html && ${php} -r "
    require 'vendor/autoload.php';
    \\$app = require_once 'bootstrap/app.php';
    \\$kernel = \\$app->make(Illuminate\\\\Contracts\\\\Console\\\\Kernel::class);
    \\$kernel->bootstrap();
    \\$plans = DB::table('subscription_plans')->count();
    \\$subs = DB::table('subscriptions')->count();
    echo json_encode(['plans'=>\\$plans,'subs'=>\\$subs]);
  "`;
  conn.exec(cmd, (err, stream) => {
    if (err) { console.error(err); conn.end(); return; }
    stream.on('close', () => conn.end())
    .on('data', d => process.stdout.write(d.toString()))
    .stderr.on('data', d => process.stderr.write(d.toString()));
  });
}).on('error', err => console.error(err.message))
.connect({
  host: process.env.DEPLOY_HOST || 'whatsapp.km.site.nom.br',
  port: Number(process.env.DEPLOY_PORT || 1979),
  username: process.env.DEPLOY_USER || 'whatsappsite',
  password: process.env.DEPLOY_PASSWORD || '',
});
