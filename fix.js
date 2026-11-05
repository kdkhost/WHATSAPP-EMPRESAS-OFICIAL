const { Client } = require('ssh2');
const conn = new Client();
conn.on('ready', () => {
  conn.exec('cd ~/public_html && git fetch origin main && git reset --hard origin/main && echo FIXED', (err, stream) => {
    stream.on('close', () => { conn.end(); }).on('data', data => console.log(data.toString()));
  });
}).connect({ host: '51.222.153.58', port: 1979, username: 'whatsappsite', password: '83388601Mm...' });
