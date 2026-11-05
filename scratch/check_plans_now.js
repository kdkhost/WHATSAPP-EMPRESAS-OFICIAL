const { Client } = require('ssh2');
const fs = require('fs'), path = require('path'), os = require('os');
const keyFile = path.join(__dirname, '..', '.ssh', 'deploy_whatsapp');
const keyPath = fs.existsSync(keyFile) ? keyFile : path.join(os.homedir(), '.ssh', 'deploy_whatsapp');

const conn = new Client();
conn.on('ready', () => {
  const APP = '/home/whatsappsite/public_html';
  const PHP = '/opt/cpanel/ea-php84/root/usr/bin/php';
  const cmd = `cat << 'EOF' > /tmp/check_plans_now.php
<?php
require '${APP}/vendor/autoload.php';
$app = require_once '${APP}/bootstrap/app.php';
$kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
$kernel->bootstrap();

$plans = \\DB::table('plans')->get();
echo 'PLANOS NO BANCO AGORA:' . PHP_EOL;
foreach ($plans as $p) {
    echo '- Plano #' . $p->id . ' [' . $p->name . ']: features=' . $p->features . PHP_EOL;
}

echo PHP_EOL . 'TENANTS:' . PHP_EOL;
$tenants = \\DB::table('tenants')->get();
foreach ($tenants as $t) {
    echo '- Tenant #' . $t->id . ' [' . $t->company_name . ']: plan_id=' . $t->plan_id . ' user_id=' . $t->user_id . PHP_EOL;
}
$user = \\App\\Models\\User::find(7);
$tenant = \\App\\Modules\\Tenancy\\Models\Tenant::find(4);
$service = app(\\App\\Modules\\Contracts\\Services\\ContractAccessService::class);

echo PHP_EOL . "TESTE ACESSO TENANT #4:" . PHP_EOL;
echo "User: " . ($user ? $user->name . ' (' . $user->email . ')' : 'NULL') . PHP_EOL;
echo "Tenant: " . ($tenant ? $tenant->company_name . ' (plan_id=' . $tenant->plan_id . ')' : 'NULL') . PHP_EOL;
echo "Tenant Plan: " . ($tenant->plan ? $tenant->plan->name : 'NULL') . PHP_EOL;
echo "Plan features raw: " . var_export($tenant->plan?->features, true) . PHP_EOL;
echo "tenantHasAccess: " . var_export($service->tenantHasAccess($tenant), true) . PHP_EOL;

// Simular Blade::if
\Illuminate\Support\Facades\Auth::guard('web')->login($user);
echo "Auth::guard('web')->check(): " . var_export(\Illuminate\Support\Facades\Auth::guard('web')->check(), true) . PHP_EOL;
$resolvedUser = \Illuminate\Support\Facades\Auth::guard('web')->user();
$resolvedTenant = \App\Modules\Tenancy\Models\Tenant::where('user_id', $resolvedUser->id)->first();
echo "Resolved Tenant: " . ($resolvedTenant ? $resolvedTenant->company_name : 'NULL') . PHP_EOL;
echo "Resolved tenantHasAccess: " . var_export($service->tenantHasAccess($resolvedTenant), true) . PHP_EOL;
EOF
${PHP} /tmp/check_plans_now.php
rm -f /tmp/check_plans_now.php
`;
  conn.exec(cmd, (err, stream) => {
    let out = '';
    stream.on('data', d => out += d);
    stream.on('close', () => {
      console.log(out);
      conn.end();
    });
  });
}).connect({
  host: 'whatsapp.km.site.nom.br',
  port: 1979,
  username: 'whatsappsite',
  privateKey: fs.readFileSync(keyPath, 'utf8')
});
