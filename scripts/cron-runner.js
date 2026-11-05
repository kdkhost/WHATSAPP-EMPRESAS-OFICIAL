/**
 * @autor marcelo-brad rj
 * @contato Tel: 21 981325441 | contato@kdkhost.com.br | @MARCELO_BRAD
 * @copyright Copyright (c) KDK HOST / Marcelo Brad. Todos os direitos reservados.
 * @propriedade-intelectual Software proprietário — Runner de Cron Job nativo para cPanel sem Redis.
 */

const { execSync } = require('child_process');
const path = require('path');
const fs = require('fs');

console.log(`[${new Date().toISOString()}] [cPanel Cron Runner] Iniciando rotina de régua de cobrança oficial...`);

const baseDir = path.resolve(__dirname, '..');
const artisanPath = path.join(baseDir, 'artisan');

try {
    if (fs.existsSync(artisanPath)) {
        // 1. Processamento direto da régua de cobrança oficial (D-3, D0, D+1, D+3, D+5)
        console.log('[cPanel Cron Runner] Executando php artisan billing:process-schedule...');
        try {
            const billingOutput = execSync('php artisan billing:process-schedule', {
                cwd: baseDir,
                encoding: 'utf8',
                timeout: 120000
            });
            console.log('[cPanel Cron Runner] Saída Régua:', billingOutput.trim());
        } catch (e) {
            console.warn('[cPanel Cron Runner] Aviso na régua:', e.message);
        }

        // 2. Dispara o scheduler nativo do Laravel
        console.log('[cPanel Cron Runner] Executando php artisan schedule:run...');
        try {
            const scheduleOutput = execSync('php artisan schedule:run', {
                cwd: baseDir,
                encoding: 'utf8',
                timeout: 60000
            });
            console.log('[cPanel Cron Runner] Saída Schedule:', scheduleOutput.trim());
        } catch (e) {
            console.warn('[cPanel Cron Runner] Aviso no schedule:', e.message);
        }
    } else {
        console.log('[cPanel Cron Runner] Artisan não localizado na pasta raiz.');
    }
    console.log(`[${new Date().toISOString()}] [cPanel Cron Runner] Ciclo de régua concluído com sucesso.`);
} catch (error) {
    console.error(`[cPanel Cron Runner] Erro ao executar ciclo de cron:`, error.message);
    process.exit(1);
}
