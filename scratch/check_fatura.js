const { Client } = require('ssh2');
const fs = require('fs'), path = require('path'), os = require('os');

// Carrega .env
const lines = fs.readFileSync(path.join(__dirname, '..', '.env'), 'utf8').split(/\r?\n/);
lines.forEach(l => {
  const e = l.indexOf('='); if (e > 0 && !l.trim().startsWith('#')) {
    const k = l.slice(0, e).trim(); let v = l.slice(e + 1).trim();
    if ((v.startsWith('"') && v.endsWith('"')) || (v.startsWith("'") && v.endsWith("'"))) v = v.slice(1, -1);
    if (!process.env[k]) process.env[k] = v;
  }
});

const pk = path.join(os.homedir(), '.ssh', 'deploy_whatsapp');
const conn = new Client();

function run(cmd) {
  return new Promise((res, rej) => {
    conn.exec(cmd, (err, s) => {
      if (err) return rej(err);
      let o = ''; s.on('data', d => o += d); s.stderr.on('data', () => {});
      s.on('close', () => res(o));
    });
  });
}

conn.on('ready', async () => {
  console.log('✓ Conectado\n');

  // Buscar a fatura via SQL direto (sem tinker)
  const PHP = '/opt/cpanel/ea-php84/root/usr/bin/php';
  const APP = '/home/whatsappsite/public_html';

  const phpScript = `<?php
require '${APP}/vendor/autoload.php';
$app = require '${APP}/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();

// Buscar fatura pelo número
$inv = DB::table('invoices')->where('number','WHMCS-482178')->orWhere('number','482178')->orWhere('id', 1405)->first();
if (!$inv) {
    // Buscar as ultimas 5
    $invs = DB::table('invoices')->orderByDesc('id')->take(5)->get(['id','number','status','gateway','webhook_token']);
    echo "ULTIMAS: " . json_encode($invs, JSON_PRETTY_PRINT);
} else {
    $meta = json_decode($inv->meta ?? '[]', true);
    $gw = $meta['gateway_payment'] ?? [];
    echo json_encode([
        'id' => $inv->id,
        'number' => $inv->number,
        'status' => $inv->status,
        'gateway' => $inv->gateway,
        'webhook_token' => $inv->webhook_token,
        'pix_code' => substr($gw['pix_copia_cola'] ?? '', 0, 60) ?: 'VAZIO',
        'qrcode' => substr($gw['qrcode_url'] ?? '', 0, 60) ?: 'VAZIO',
        'meta_keys' => array_keys($meta),
        'gw_keys' => array_keys($gw),
    ], JSON_PRETTY_PRINT);
}
`;

  // Salvar script temporário no servidor e executar
  const tmpScript = '/tmp/check_inv.php';
  await run(`cat > ${tmpScript} << 'PHPEOF'
${phpScript}
PHPEOF`);

  const result = await run(`${PHP} ${tmpScript} 2>/dev/null`);
  console.log('=== FATURA ===');
  console.log(result);

  // Se encontrou o token, testar o endpoint /pix com o token correto
  const match = result.match(/"webhook_token"\s*:\s*"([^"]+)"/);
  if (match) {
    const realToken = match[1];
    console.log('\n=== TESTE /pix com token CORRETO ===');
    console.log('Token:', realToken.substring(0, 30) + '...');
    const curlResult = await run(
      `curl -sk -X POST https://whatsapp.km.site.nom.br/fatura/${realToken}/pix -H "Accept: application/json" -H "Content-Type: application/json" -w "\\n[HTTP_STATUS:%{http_code}]" 2>&1`
    );
    console.log(curlResult.substring(0, 600));
  }

  // Limpar
  await run(`rm -f ${tmpScript}`);

  conn.end();
  console.log('\n✓ Concluído');
});

conn.on('error', err => console.error('✗ SSH Error:', err.message));
conn.connect({
  host: process.env.DEPLOY_HOST || 'whatsapp.km.site.nom.br',
  port: Number(process.env.DEPLOY_PORT || 1979),
  username: process.env.DEPLOY_USER || 'whatsappsite',
  password: process.env.DEPLOY_PASSWORD || '',
  privateKey: fs.existsSync(pk) ? fs.readFileSync(pk, 'utf8') : undefined,
});
