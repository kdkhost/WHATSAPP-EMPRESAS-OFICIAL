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
      if (!process.env[key]) {
        process.env[key] = val;
      }
    }
  }
}

const keyFile = path.join(__dirname, '..', '.ssh', 'deploy_whatsapp');
const keyPath = fs.existsSync(keyFile) ? keyFile : path.join(os.homedir(), '.ssh', 'deploy_whatsapp');

const conn = new Client();
conn.on('ready', () => {
  console.log('Client :: ready');
  conn.sftp((err, sftp) => {
    if (err) throw err;
    
    const defaultFiles = [
      'app/Modules/Billing/Resources/views/invoices/create.blade.php',
      'app/Modules/WhmcsIntegration/Services/WhmcsClient.php',
      'app/Modules/Billing/Http/Controllers/CalendarController.php'
    ];
    const filesToUpload = process.argv.slice(2).length > 0 ? process.argv.slice(2) : defaultFiles;

    let uploaded = 0;
    filesToUpload.forEach((file) => {
      const localFile = path.join(__dirname, file);
      const remoteFile = `/home/whatsappsite/public_html/${file}`;
      
      console.log(`Uploading ${file}...`);
      sftp.fastPut(localFile, remoteFile, (err) => {
        if (err) throw err;
        console.log(`Successfully uploaded ${file}`);
        uploaded++;
        if (uploaded === filesToUpload.length) {
          console.log('All files uploaded. Exiting.');
          conn.end();
        }
      });
    });
  });
}).on('error', err => {
  console.error(err);
}).connect({
  host: process.env.DEPLOY_HOST || 'whatsapp.km.site.nom.br',
  port: Number(process.env.DEPLOY_PORT || 1979),
  username: process.env.DEPLOY_USER || 'whatsappsite',
  password: process.env.DEPLOY_PASSWORD || '',
  privateKey: fs.existsSync(keyPath) ? fs.readFileSync(keyPath, 'utf8') : undefined
});
