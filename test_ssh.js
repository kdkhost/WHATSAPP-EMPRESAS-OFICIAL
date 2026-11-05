const { Client } = require('ssh2');
const fs = require('fs');
const env = fs.readFileSync('.env', 'utf8');
const host = env.match(/DEPLOY_HOST=(.*)/)[1].trim();
const port = env.match(/DEPLOY_PORT=(.*)/)[1].trim();
const user = env.match(/DEPLOY_USER=(.*)/)[1].trim();
const pass = env.match(/DEPLOY_PASSWORD=(.*)/)[1].trim();
const conn = new Client();
conn.on('ready', () => {
  conn.exec('tail -n 100 public_html/storage/logs/laravel.log', (err, stream) => {
    if (err) throw err;
    stream.on('close', () => conn.end()).on('data', (data) => console.log(data.toString()));
  });
}).connect({ host: host, port: parseInt(port), username: user, password: pass });
