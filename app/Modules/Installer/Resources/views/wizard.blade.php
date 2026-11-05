<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Instalador - {{ config('app.name', 'Cobranca WhatsApp') }}</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
body { background: linear-gradient(135deg, #0d6efd, #6610f2); min-height: 100vh; display: flex; align-items: center; padding: 1rem 0; }
.wizard-card { max-width: 700px; margin: auto; border-radius: 1rem; }
.wizard-step { display: none; }
.wizard-step.is-active { display: block !important; }
.step-indicator { display: flex; gap: .25rem; }
.step-indicator .dot { width: 2.5rem; height: 4px; border-radius: 2px; background: rgba(0,0,0,.1); transition: all .3s; }
.step-indicator .dot.done { background: #198754; }
.step-indicator .dot.current { background: #0d6efd; }
.check-item { display: flex; justify-content: space-between; align-items: center; padding: .5rem 0; border-bottom: 1px solid #eee; }
.spinner-inline { width: 1rem; height: 1rem; border-width: 2px; }
</style>
</head>
<body>
<div class="container py-4">
    <div class="card wizard-card shadow-lg">
        <div class="card-header bg-white p-4">
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-whatsapp text-success fs-2"></i>
                <div>
                    <h4 class="m-0">Instalador do Sistema</h4>
                    <small class="text-muted">Siga os passos para configurar o sistema</small>
                </div>
            </div>
            <div class="step-indicator mt-3" id="indicators">
                <div class="dot current" id="dot-1"></div>
                <div class="dot" id="dot-2"></div>
                <div class="dot" id="dot-3"></div>
                <div class="dot" id="dot-4"></div>
                <div class="dot" id="dot-5"></div>
            </div>
        </div>

        <div class="card-body p-4">
            {{-- Step 1: Requisitos --}}
            <div class="wizard-step is-active" id="step-1">
                <h5><i class="bi bi-gear-wide-connected"></i> Verificacao de requisitos</h5>
                <p class="text-muted small">Verificando se o servidor atende aos requisitos minimos.</p>
                <div id="requirements-list" class="mb-3">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 text-muted">Verificando...</p>
                    </div>
                </div>
                <div class="text-end">
                    <button class="btn btn-primary" id="btn-next-1" disabled>Proximo <i class="bi bi-arrow-right"></i></button>
                </div>
            </div>

            {{-- Step 2: Banco de dados --}}
            <div class="wizard-step" id="step-2">
                <h5><i class="bi bi-database"></i> Configuracao do banco de dados</h5>
                <p class="text-muted small">Informe os dados de conexao MySQL/MariaDB.</p>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Host</label>
                        <input type="text" id="db_host" class="form-control" value="localhost">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Porta</label>
                        <input type="number" id="db_port" class="form-control" value="3306">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Nome do banco</label>
                        <input type="text" id="db_database" class="form-control" placeholder="cobranca_whatsapp">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Usuario</label>
                        <input type="text" id="db_username" class="form-control" placeholder="usuario_mysql">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Senha</label>
                        <input type="password" id="db_password" class="form-control">
                    </div>
                </div>
                <div id="db-result" class="mt-3"></div>
                <div class="d-flex justify-content-between mt-3">
                    <button class="btn btn-outline-secondary" onclick="goTo(1)"><i class="bi bi-arrow-left"></i> Voltar</button>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary" id="btn-test-db"><i class="bi bi-plug"></i> Testar conexao</button>
                        <button class="btn btn-primary" id="btn-next-2" disabled>Proximo <i class="bi bi-arrow-right"></i></button>
                    </div>
                </div>
            </div>

            {{-- Step 3: Configuracoes gerais --}}
            <div class="wizard-step" id="step-3">
                <h5><i class="bi bi-sliders"></i> Configuracoes gerais</h5>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Nome do sistema</label>
                        <input type="text" id="app_name" class="form-control" value="Cobranca WhatsApp">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">URL do site (com https://)</label>
                        <input type="url" id="app_url" class="form-control" placeholder="https://meusite.com.br" value="{{ url('/') }}">
                    </div>
                </div>
                <div class="d-flex justify-content-between mt-3">
                    <button class="btn btn-outline-secondary" onclick="goTo(2)"><i class="bi bi-arrow-left"></i> Voltar</button>
                    <button class="btn btn-primary" onclick="goTo(4)">Proximo <i class="bi bi-arrow-right"></i></button>
                </div>
            </div>

            {{-- Step 4: Superadmin --}}
            <div class="wizard-step" id="step-4">
                <h5><i class="bi bi-person-badge"></i> Criar superadmin (dono do sistema)</h5>
                <p class="text-muted small">Este sera o usuario com acesso TOTAL ao sistema.</p>
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label">Nome completo</label>
                        <input type="text" id="admin_name" class="form-control" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">E-mail</label>
                        <input type="email" id="admin_email" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Senha (min 8 caracteres)</label>
                        <input type="password" id="admin_password" class="form-control" minlength="8" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Confirmar senha</label>
                        <input type="password" id="admin_password_confirmation" class="form-control" required>
                    </div>
                </div>
                <div class="d-flex justify-content-between mt-3">
                    <button class="btn btn-outline-secondary" onclick="goTo(3)"><i class="bi bi-arrow-left"></i> Voltar</button>
                    <button class="btn btn-primary" id="btn-install"><i class="bi bi-rocket-takeoff"></i> Instalar sistema</button>
                </div>
            </div>

            {{-- Step 5: Finalizacao --}}
            <div class="wizard-step" id="step-5">
                <div id="install-progress">
                    <h5><i class="bi bi-hourglass-split"></i> Instalando...</h5>
                    <div class="mt-4">
                        <div class="check-item" id="prog-config"><span>Salvando configuracoes...</span><span class="spinner-border spinner-inline text-primary"></span></div>
                        <div class="check-item" id="prog-migrate"><span>Criando tabelas...</span><span class="text-muted">Aguardando</span></div>
                        <div class="check-item" id="prog-admin"><span>Criando superadmin...</span><span class="text-muted">Aguardando</span></div>
                        <div class="check-item" id="prog-finalize"><span>Finalizando...</span><span class="text-muted">Aguardando</span></div>
                    </div>
                </div>
                <div id="install-done" class="text-center py-4" style="display:none;">
                    <i class="bi bi-check-circle-fill text-success" style="font-size:4rem;"></i>
                    <h4 class="mt-3 text-success">Sistema instalado com sucesso!</h4>
                    <p class="text-muted">O sistema esta pronto para uso.</p>
                    <a href="/admin/login" class="btn btn-primary btn-lg mt-2">
                        <i class="bi bi-box-arrow-in-right"></i> Acessar o painel
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var csrf = document.querySelector('meta[name=csrf-token]').content;

function goTo(step) {
    // Esconde todos
    var steps = document.querySelectorAll('.wizard-step');
    for (var i = 0; i < steps.length; i++) {
        steps[i].classList.remove('is-active');
    }
    // Mostra o step alvo
    var target = document.getElementById('step-' + step);
    if (target) {
        target.classList.add('is-active');
    }
    // Atualiza indicadores
    for (var d = 1; d <= 5; d++) {
        var dot = document.getElementById('dot-' + d);
        if (dot) {
            dot.className = 'dot ' + (d < step ? 'done' : (d === step ? 'current' : ''));
        }
    }
    window.scrollTo(0, 0);
}

// Step 1: Check requirements
(function() {
    fetch('/install/requirements')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var html = '';
            var keys = Object.keys(data.checks);
            for (var i = 0; i < keys.length; i++) {
                var check = data.checks[keys[i]];
                html += '<div class="check-item"><span>' + check.label + '</span><span>';
                if (check.ok) {
                    html += '<i class="bi bi-check-circle-fill text-success"></i> ' + check.current;
                } else {
                    html += '<i class="bi bi-x-circle-fill text-danger"></i> ' + check.current;
                }
                html += '</span></div>';
            }
            document.getElementById('requirements-list').innerHTML = html;
            if (data.ok) {
                document.getElementById('btn-next-1').disabled = false;
            }
        })
        .catch(function(e) {
            document.getElementById('requirements-list').innerHTML = '<div class="alert alert-danger">Erro ao verificar requisitos: ' + e.message + '</div>';
        });
})();

document.getElementById('btn-next-1').onclick = function() { goTo(2); };

// Step 2: Test DB
document.getElementById('btn-test-db').onclick = function() {
    var payload = {
        db_host: document.getElementById('db_host').value,
        db_port: parseInt(document.getElementById('db_port').value),
        db_database: document.getElementById('db_database').value,
        db_username: document.getElementById('db_username').value,
        db_password: document.getElementById('db_password').value
    };
    fetch('/install/database', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        var el = document.getElementById('db-result');
        if (data.ok) {
            el.innerHTML = '<div class="alert alert-success small"><i class="bi bi-check-circle"></i> Conexao OK - ' + data.version + '</div>';
            document.getElementById('btn-next-2').disabled = false;
        } else {
            el.innerHTML = '<div class="alert alert-danger small"><i class="bi bi-x-circle"></i> Falha: ' + data.error + '</div>';
        }
    })
    .catch(function(e) {
        document.getElementById('db-result').innerHTML = '<div class="alert alert-danger small">Erro: ' + e.message + '</div>';
    });
};

document.getElementById('btn-next-2').onclick = function() { goTo(3); };

// Step 4: Install
document.getElementById('btn-install').onclick = function() {
    var pwd = document.getElementById('admin_password').value;
    var conf = document.getElementById('admin_password_confirmation').value;
    if (pwd !== conf) { Swal.fire({icon:'error', title:'Erro', text:'As senhas nao conferem.'}); return; }
    if (pwd.length < 8) { Swal.fire({icon:'error', title:'Erro', text:'A senha deve ter pelo menos 8 caracteres.'}); return; }

    goTo(5);

    function updateProg(id, ok, msg) {
        var el = document.getElementById(id);
        var span = el.querySelectorAll('span')[1];
        if (ok) {
            span.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> ' + (msg || 'OK');
        } else {
            span.innerHTML = '<i class="bi bi-x-circle-fill text-danger"></i> ' + (msg || 'Falhou');
        }
    }

    function setSpinner(id) {
        var el = document.getElementById(id);
        var span = el.querySelectorAll('span')[1];
        span.innerHTML = '<span class="spinner-border spinner-inline text-primary"></span>';
    }

    // Config
    var configPayload = {
        app_name: document.getElementById('app_name').value,
        app_url: document.getElementById('app_url').value || window.location.origin,
        db_host: document.getElementById('db_host').value,
        db_port: parseInt(document.getElementById('db_port').value),
        db_database: document.getElementById('db_database').value,
        db_username: document.getElementById('db_username').value,
        db_password: document.getElementById('db_password').value
    };

    fetch('/install/config', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify(configPayload)
    })
    .then(function(r) { updateProg('prog-config', r.ok); if (!r.ok) throw new Error('Config falhou'); return r.json(); })
    .then(function() {
        // Migrate
        setSpinner('prog-migrate');
        return fetch('/install/migrate', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
        });
    })
    .then(function(r) { updateProg('prog-migrate', r.ok); if (!r.ok) throw new Error('Migrations falharam'); return r.json(); })
    .then(function() {
        // Admin
        setSpinner('prog-admin');
        return fetch('/install/admin', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({
                name: document.getElementById('admin_name').value,
                email: document.getElementById('admin_email').value,
                password: pwd,
                password_confirmation: conf
            })
        });
    })
    .then(function(r) { updateProg('prog-admin', r.ok); if (!r.ok) throw new Error('Admin falhou'); return r.json(); })
    .then(function() {
        // Finalize
        setSpinner('prog-finalize');
        return fetch('/install/finalize', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
        });
    })
    .then(function(r) {
        updateProg('prog-finalize', r.ok);
        document.getElementById('install-progress').style.display = 'none';
        document.getElementById('install-done').style.display = 'block';
    })
    .catch(function(e) {
        Swal.fire({icon:'error', title:'Erro', text:'Erro na instalacao: ' + e.message});
    });
};
</script>
</body>
</html>
