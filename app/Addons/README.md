# Addons de terceiros — Guia para desenvolvedores

## Estrutura obrigatoria

```
app/Addons/meu-addon/
├── addon.json                  # Metadados (OBRIGATORIO)
├── AddonServiceProvider.php    # Service Provider Laravel (OBRIGATORIO)
├── Install.php                 # Lifecycle hooks (opcional)
├── routes/
│   └── web.php                 # Rotas do addon (opcional)
├── Resources/
│   └── views/                  # Views Blade (opcional)
├── Models/                     # Eloquent Models (opcional)
├── Http/
│   └── Controllers/            # Controllers (opcional)
├── Services/                   # Services/Logica de negocio (opcional)
└── database/
    └── migrations/             # Migrations proprias (opcional)
```

## addon.json (schema completo)

```json
{
    "name": "Meu Addon Incrivel",
    "slug": "meu-addon",
    "version": "1.2.0",
    "author": "Seu Nome",
    "email": "dev@exemplo.com",
    "website": "https://meusite.com",
    "description": "Descricao do que o addon faz",
    "provider": "App\\Addons\\meu_addon\\AddonServiceProvider",
    "enabled": true,
    "min_system_version": "1.0.0",
    "requires": ["billing", "whatsapp"],
    "permissions": [
        {"action": "view", "label": "Visualizar meu addon"},
        {"action": "create", "label": "Criar itens no meu addon"},
        {"action": "edit", "label": "Editar itens no meu addon"},
        {"action": "delete", "label": "Excluir itens no meu addon"},
        {"action": "manage", "label": "Gerenciar meu addon (config)"}
    ],
    "hooks": {
        "install": "App\\Addons\\meu_addon\\Install::install",
        "uninstall": "App\\Addons\\meu_addon\\Install::uninstall",
        "enable": "App\\Addons\\meu_addon\\Install::enable",
        "disable": "App\\Addons\\meu_addon\\Install::disable",
        "update": "App\\Addons\\meu_addon\\Install::update"
    },
    "settings": {
        "api_key": "",
        "max_items": 100
    },
    "menu": {
        "label": "Meu Addon",
        "icon": "bi-puzzle",
        "route": "admin.meu-addon.index"
    }
}
```

## Campos do addon.json

| Campo | Tipo | Obrigatorio | Descricao |
|-------|------|-------------|-----------|
| `name` | string | Sim | Nome de exibicao |
| `slug` | string | Sim | Identificador unico (lowercase, sem espacos) |
| `version` | string | Sim | Versao semver (ex.: 1.0.0) |
| `author` | string | Nao | Nome do autor |
| `provider` | string | Sim | FQCN do ServiceProvider |
| `enabled` | bool | Nao | Se o addon esta ativo (default: true) |
| `requires` | array | Nao | Slugs de modulos/addons dos quais depende |
| `permissions` | array | Nao | Permissions que o addon declara (auto-registradas na instalacao) |
| `hooks` | object | Nao | Callables para lifecycle (install, uninstall, enable, disable, update) |
| `settings` | object | Nao | Config padrao do addon |
| `menu` | object | Nao | Item de menu no sidebar (label, icon, route) |

## AddonServiceProvider.php (exemplo)

```php
<?php

namespace App\Addons\meu_addon;

use App\Modules\Permissions\Models\Permission;
use App\Services\CronManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AddonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bindings, singletons
    }

    public function boot(): void
    {
        // Rotas (protegidas por permission)
        Route::middleware(['web', 'admin', 'module.permission:meu-addon.view'])
            ->prefix('admin/meu-addon')
            ->name('admin.meu-addon.')
            ->group(__DIR__.'/routes/web.php');

        // Views
        $this->loadViewsFrom(__DIR__.'/Resources/views', 'meu-addon');

        // Cron job
        CronManager::register(
            name: 'meu-addon.sync',
            callback: fn() => $this->syncData(),
            intervalMinutes: 30,
            description: 'Sincroniza dados do meu addon'
        );
    }
}
```

## Install.php (lifecycle hooks)

```php
<?php

namespace App\Addons\meu_addon;

class Install
{
    /** Executado na primeira instalacao. */
    public static function install(array $addon): void
    {
        // Criar configuracoes padrao, popular dados iniciais, etc.
    }

    /** Executado ao desinstalar (CUIDADO: dados serao perdidos). */
    public static function uninstall(array $addon): void
    {
        // Limpar tabelas, configs, cache, etc.
    }

    /** Executado ao ativar. */
    public static function enable(array $addon): void {}

    /** Executado ao desativar. */
    public static function disable(array $addon): void {}

    /** Executado ao atualizar versao. */
    public static function update(array $addon): void {}
}
```

## Permissions

As permissions declaradas no `addon.json` sao automaticamente registradas
durante a instalacao. Nos controllers, use o middleware:

```php
Route::middleware('module.permission:meu-addon.view')
```

Ou nos templates Blade:

```blade
@permission('meu-addon.manage')
    <button>Gerenciar</button>
@endpermission
```

## Regras importantes

1. **Namespace**: `App\Addons\<slug_com_underline>\`
2. **Tabelas**: prefixe com `addon_<slug>_` (ex.: `addon_meu_addon_dados`)
3. **NUNCA modifique** arquivos do core ou de outros modulos
4. **Use hooks** do CronManager para tarefas agendadas
5. **Declare dependencies** no `requires` se depender de outro modulo
6. O **superadmin** sempre tem acesso total — nao precisa de permission check

## Gerenciamento pelo painel

O addon eh gerenciado em **Admin → Sistema → Addons**:
- Instalar (roda migrations + registra permissions)
- Ativar/Desativar (sem perda de dados)
- Atualizar (roda novas migrations)
- Desinstalar (rollback migrations + remove permissions)
