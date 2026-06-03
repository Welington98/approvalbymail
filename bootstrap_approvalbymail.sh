#!/usr/bin/env bash
# Bootstrap do repositorio approval by mail (0.0.1-alpha / S0).
# Rode UMA vez dentro de ~/Publico/stag.
set -euo pipefail
cd "$(dirname "$0")"

rm -f config.class action.class config.form README CHANGELOG LICENCE
mkdir -p inc front tools css/src js/src locales deploy

cat > 'setup.php' <<'ABM_EOF'
<?php
/**
 * approval by mail — ação por e-mail no GLPI.
 * Fork modernizado (Padrão SDB) do plugin "SDB - Ação por e-mail" (GPLv3).
 */

define('PLUGIN_APPROVALBYMAIL_VERSION', '0.0.1-alpha');
define('PLUGIN_APPROVALBYMAIL_MIN_GLPI', '10.0.0');
define('PLUGIN_APPROVALBYMAIL_MAX_GLPI', '10.0.99');

/**
 * Inicialização do plugin (chamada em todo carregamento do GLPI).
 */
function plugin_init_approvalbymail(): void
{
    /** @var array $PLUGIN_HOOKS */
    global $PLUGIN_HOOKS;

    // Conformidade CSRF dos formulários do plugin.
    $PLUGIN_HOOKS['csrf_compliant']['approvalbymail'] = true;

    $plugin = new Plugin();
    if (!$plugin->isInstalled('approvalbymail') || !$plugin->isActivated('approvalbymail')) {
        return;
    }

    // Aba de configuração dentro de "Configurar > Geral".
    Plugin::registerClass(PluginApprovalbymailConfig::class, [
        'addtabon' => Config::class,
    ]);

    // Link de engrenagem na lista de plugins.
    $PLUGIN_HOOKS['config_page']['approvalbymail'] = 'front/config.form.php';

    // S1+: aqui entram os hooks 'item_add' (TicketValidation) e afins.
}

/**
 * Metadados do plugin.
 */
function plugin_version_approvalbymail(): array
{
    return [
        'name'           => 'Approval by Mail',
        'version'        => PLUGIN_APPROVALBYMAIL_VERSION,
        'author'         => '<seu nome / Verdanadesk>',
        'license'        => 'GPLv3',
        'homepage'       => '',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_APPROVALBYMAIL_MIN_GLPI,
                'max' => PLUGIN_APPROVALBYMAIL_MAX_GLPI,
            ],
        ],
    ];
}

/**
 * Pré-requisitos (a faixa de versão do GLPI já é validada via requirements).
 */
function plugin_approvalbymail_check_prerequisites(): bool
{
    return true;
}

/**
 * Verificação de configuração mínima para ativar.
 */
function plugin_approvalbymail_check_config($verbose = false): bool
{
    return true;
}

ABM_EOF

cat > 'hook.php' <<'ABM_EOF'
<?php
/**
 * Instalação/desinstalação do plugin approval by mail.
 * Padrão SDB: simétrico, idempotente, sem SQL com input concatenado.
 */

/**
 * Instalação: cria tabelas e popula o feature flag.
 */
function plugin_approvalbymail_install(): bool
{
    /** @var DBmysql $DB */
    global $DB;

    $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');

    // --- Tabela de configuração (feature flags) ---
    $config_table = PluginApprovalbymailConfig::getTable();
    if (!$DB->tableExists($config_table)) {
        $DB->doQuery(
            "CREATE TABLE IF NOT EXISTS `$config_table` (
                `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name`      VARCHAR(100) NOT NULL,
                `content`   VARCHAR(255) NULL DEFAULT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT '0',
                `date_mod`  TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        // Seed: somente a aprovação de TicketValidation no alpha.
        $DB->insert($config_table, [
            'id'        => PluginApprovalbymailConfig::TICKET_VALIDATION,
            'name'      => 'Ticket - Aprovação',
            'content'   => 'Envia e-mail para aprovar/recusar a validação de chamado',
            'is_active' => 1,
            'date_mod'  => $now,
        ]);
    }

    // --- Tabela de ações tokenizadas ---
    $action_table = PluginApprovalbymailAction::getTable();
    if (!$DB->tableExists($action_table)) {
        $userfk = User::getForeignKeyField();
        $DB->doQuery(
            "CREATE TABLE IF NOT EXISTS `$action_table` (
                `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `$userfk`       INT UNSIGNED NOT NULL DEFAULT '0',
                `items_id`      INT UNSIGNED NOT NULL DEFAULT '0',
                `itemtype`      VARCHAR(100) NOT NULL,
                `token`         VARCHAR(128) NOT NULL,
                `used_at`       TIMESTAMP NULL DEFAULT NULL,
                `date_creation` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `itemtype_items_id` (`itemtype`, `items_id`),
                KEY `token` (`token`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    // S2: instalação dos modelos de notificação entra aqui.

    return true;
}

/**
 * Desinstalação: remove tabelas (teardown completo).
 */
function plugin_approvalbymail_uninstall(): bool
{
    /** @var DBmysql $DB */
    global $DB;

    foreach ([
        PluginApprovalbymailAction::getTable(),
        PluginApprovalbymailConfig::getTable(),
    ] as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQuery("DROP TABLE `$table`");
        }
    }

    // S2: remoção dos modelos de notificação entra aqui.

    return true;
}

ABM_EOF

cat > 'inc/config.class.php' <<'ABM_EOF'
<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Configuração do plugin: feature flags + utilitários de cripto.
 */
class PluginApprovalbymailConfig extends CommonDBTM
{
    /** Feature flags (IDs fixos na tabela de config). */
    public const TICKET_VALIDATION = 1;
    // S2+/fases seguintes: CHANGE_VALIDATION, TICKET_SOLUTION, etc.

    public $dohistory = false;

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_approvalbymail_config';
    }

    static function getTypeName($nb = 0)
    {
        return __('Approval by Mail', 'approvalbymail');
    }

    static function canView()
    {
        return Session::haveRight('config', READ);
    }

    static function canCreate()
    {
        return false;
    }

    static function canUpdate()
    {
        return Session::haveRight('config', UPDATE);
    }

    static function canDelete()
    {
        return false;
    }

    static function canPurge()
    {
        return false;
    }

    // ---- Aba dentro de Config (Configurar > Geral) ----

    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!$withtemplate && $item instanceof Config) {
            return self::getTypeName();
        }
        return '';
    }

    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof Config) {
            self::showConfigForm();
        }
        return true;
    }

    private static function showConfigForm(): void
    {
        /** @var DBmysql $DB */
        global $DB;

        $action = Plugin::getWebDir('approvalbymail') . '/front/config.form.php';

        echo '<form name="approvalbymail_config" method="post" action="' . htmlspecialchars($action) . '">';
        echo '<table class="tab_cadre_fixe">';
        echo '<tr><th colspan="3">' . self::getTypeName() . '</th></tr>';
        echo '<tr class="tab_bg_1">';
        echo '<th>' . __('Action', 'glpi') . '</th>';
        echo '<th>' . __('Description', 'glpi') . '</th>';
        echo '<th>' . __('Active', 'glpi') . '</th>';
        echo '</tr>';

        foreach ($DB->request(['FROM' => self::getTable(), 'ORDER' => 'id']) as $row) {
            echo '<tr class="tab_bg_1">';
            echo '<td>' . htmlspecialchars((string) $row['name']) . '</td>';
            echo '<td>' . htmlspecialchars((string) ($row['content'] ?? '')) . '</td>';
            echo '<td>';
            Dropdown::showYesNo('is_active_' . (int) $row['id'], (int) $row['is_active']);
            echo '</td>';
            echo '</tr>';
        }

        echo '<tr class="tab_bg_2"><td colspan="3" class="center">';
        echo Html::submit(_x('button', 'Save'), [
            'name'  => 'update_config',
            'class' => 'btn btn-primary',
        ]);
        echo '</td></tr>';
        echo '</table>';
        Html::closeForm();
    }

    // ---- Cripto (Padrão SDB-1): chave gerenciada pelo GLPI ----

    /**
     * Cifra um texto usando a chave do GLPI (GLPIKey).
     */
    public static function encrypt(string $plaintext): string
    {
        return (new GLPIKey())->encrypt($plaintext);
    }

    /**
     * Decifra um texto; retorna null em caso de falha (token adulterado/ inválido).
     */
    public static function decrypt(string $ciphertext): ?string
    {
        try {
            // '+' vira espaço ao passar por URL — desfaz antes de decifrar.
            return (new GLPIKey())->decrypt(str_replace(' ', '+', $ciphertext));
        } catch (\Throwable $e) {
            return null;
        }
    }
}

ABM_EOF

cat > 'inc/action.class.php' <<'ABM_EOF'
<?php
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Ação tokenizada: registro que liga (usuário, item, itemtype) a um token
 * de uso único enviado por e-mail.
 *
 * S0: apenas o mapeamento de tabela (a tabela é criada no install).
 * S1: geração do token (bin2hex(random_bytes(32))) e validação
 *     (hash_equals, used_at single-use, expiração).
 */
class PluginApprovalbymailAction extends CommonDBTM
{
    public $dohistory = false;

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_approvalbymail_actions';
    }

    public static function getForeignKeyField()
    {
        return 'plugin_approvalbymail_actions_id';
    }

    static function canView()
    {
        return false;
    }

    static function canCreate()
    {
        // Criado internamente pelos hooks a partir do S1.
        return true;
    }

    static function canUpdate()
    {
        return false;
    }

    static function canDelete()
    {
        return false;
    }

    static function canPurge()
    {
        return false;
    }
}

ABM_EOF

cat > 'front/config.form.php' <<'ABM_EOF'
<?php
include('../../../inc/includes.php');

Session::checkLoginUser();

$plugin = new Plugin();
if (!$plugin->isActivated('approvalbymail')) {
    Html::displayNotFoundError();
    return;
}

// Só quem pode configurar o GLPI mexe nos flags.
Session::checkRight('config', UPDATE);

if (isset($_POST['update_config'])) {
    $config = new PluginApprovalbymailConfig();

    foreach ([PluginApprovalbymailConfig::TICKET_VALIDATION] as $id) {
        if ($config->getFromDB($id)) {
            $new = isset($_POST['is_active_' . $id]) ? (int) $_POST['is_active_' . $id] : 0;
            if ((int) $config->fields['is_active'] !== $new) {
                $config->update([
                    'id'        => $id,
                    'is_active' => $new,
                    'date_mod'  => $_SESSION['glpi_currenttime'],
                ]);
            }
        }
    }

    Session::addMessageAfterRedirect(__('Configuration updated', 'approvalbymail'), true, INFO);
}

Html::back();

ABM_EOF

cat > 'package.json' <<'ABM_EOF'
{
  "name": "approvalbymail",
  "version": "0.0.1-alpha",
  "private": true,
  "description": "GLPI plugin — approval by mail (Padrao SDB)",
  "license": "GPL-3.0-or-later",
  "scripts": {
    "build": "bash tools/build_assets.sh"
  },
  "devDependencies": {
    "cssnano": "^7.0.0",
    "postcss": "^8.4.0",
    "postcss-cli": "^11.0.0",
    "terser": "^5.31.0"
  }
}

ABM_EOF

cat > 'tools/build_assets.sh' <<'ABM_EOF'
#!/usr/bin/env bash
# Minifica CSS/JS: css/src/*.css -> css/*.min.css ; js/src/*.js -> js/*.min.js
set -euo pipefail
cd "$(dirname "$0")/.."

if [ -f css/src/styles.css ]; then
  npx postcss css/src/styles.css --use cssnano --no-map -o css/styles.min.css
  echo "  css/styles.min.css"
fi

if [ -f js/src/approvalbymail.js ]; then
  npx terser js/src/approvalbymail.js -c -m -o js/approvalbymail.min.js
  echo "  js/approvalbymail.min.js"
fi

ABM_EOF

cat > 'tools/extract_locales.sh' <<'ABM_EOF'
#!/usr/bin/env bash
# Extrai strings i18n para o .pot e atualiza os .po existentes.
set -euo pipefail
cd "$(dirname "$0")/.."
DOMAIN=approvalbymail
mkdir -p locales

find . -path ./node_modules -prune -o -name '*.php' -print \
  | xargs xgettext --from-code=UTF-8 --language=PHP \
      -k__:1 -k_n:1,2 -k_x:1c,2 -kx_:1c,2 \
      -o "locales/${DOMAIN}.pot"

for po in locales/*.po; do
  [ -e "$po" ] || continue
  msgmerge --update --backup=none "$po" "locales/${DOMAIN}.pot"
  echo "  atualizado: $po"
done

ABM_EOF

cat > 'css/src/styles.css' <<'ABM_EOF'
/* approval by mail — estilos da pagina publica de acao.
   Padrao SDB-14: unidades relativas, sem vw para fonte, sem !important em cascata. */
:root {
  --abm-max-width: 42rem;
}
.abm-container {
  max-width: var(--abm-max-width);
  margin: 2rem auto;
  padding: 0 1rem;
  font-size: 1rem;
  line-height: 1.5;
}

ABM_EOF

cat > 'js/src/approvalbymail.js' <<'ABM_EOF'
// approval by mail — JS minimo. Reusa os assets do core do GLPI (Padrao SDB-13).
(function () {
  'use strict';
  // S3: interacoes da pagina publica de acao.
}());

ABM_EOF

cat > 'locales/pt_BR.po' <<'ABM_EOF'
msgid ""
msgstr ""
"Project-Id-Version: approvalbymail 0.0.1-alpha\n"
"Report-Msgid-Bugs-To: \n"
"MIME-Version: 1.0\n"
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"Language: pt_BR\n"
"Plural-Forms: nplurals=2; plural=(n > 1);\n"

ABM_EOF

cat > 'locales/en_US.po' <<'ABM_EOF'
msgid ""
msgstr ""
"Project-Id-Version: approvalbymail 0.0.1-alpha\n"
"Report-Msgid-Bugs-To: \n"
"MIME-Version: 1.0\n"
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"Language: en_US\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\n"

ABM_EOF

cat > '.gitignore' <<'ABM_EOF'
/node_modules/
/staging/
*.log
.DS_Store

ABM_EOF

cat > 'README.md' <<'ABM_EOF'
# approval by mail

Plugin do GLPI para **aprovar/recusar chamados por e-mail** via link tokenizado,
sem necessidade de login. Fork modernizado, no **Padrao SDB**, do plugin abandonado
"SDB - Acao por e-mail" (ServiceDesk Brasil, GPLv3).

- **Alvo:** GLPI 10.0.x · PHP 8.x · MariaDB
- **Versao:** 0.0.1-alpha (MVP em construcao: aprovacao de TicketValidation)

## Estado (0.0.1-alpha)
S0 — fundacao: instala/desinstala limpo, aba de configuracao e feature flag.
Ainda **nao** envia e-mail (notificacao = S2; motor de validacao = S3).

## Desenvolvimento (servidor de homologacao)
```bash
npm install          # dependencias de build
make deploy          # build + substitui na pasta live do GLPI
make install         # instala via console (uma vez)
make activate        # ativa via console
make logs            # acompanha o log do GLPI
```
Caminhos do ambiente em `deploy/config.sh`.

## Licenca
GPLv3 — veja `LICENSE`.

ABM_EOF

cat > 'CHANGELOG.md' <<'ABM_EOF'
# Changelog

Formato: [Keep a Changelog](https://keepachangelog.com/) · Versionamento: SemVer.

## [0.0.1-alpha] - 2026-06-03
### Added
- Scaffold inicial do plugin (Padrao SDB).
- Instalacao/desinstalacao limpa: tabelas `config` e `actions` (com `used_at`).
- Feature flag para aprovacao de TicketValidation.
- Aba de configuracao em "Configurar > Geral".
- Cripto via GLPIKey (chave gerenciada pelo GLPI).
- Pipeline de build (minificacao CSS/JS) e i18n (pt_BR, en_US).

ABM_EOF

cat > 'LICENSE' <<'ABM_EOF'
approval by mail - GLPI plugin
Copyright (C) 2026  <seu nome / Verdanadesk>

Este programa e software livre: voce pode redistribui-lo e/ou modifica-lo
sob os termos da GNU General Public License, conforme publicada pela
Free Software Foundation, na versao 3 da Licenca, ou (a seu criterio)
qualquer versao posterior.

Este programa e distribuido na esperanca de que seja util, mas SEM
QUALQUER GARANTIA. Veja a GNU General Public License para mais detalhes.

Texto completo da licenca: https://www.gnu.org/licenses/gpl-3.0.txt

Obra derivada do plugin "SDB - Acao por e-mail" (ServiceDesk Brasil), GPLv3.

ABM_EOF

cat > 'deploy/config.sh' <<'ABM_EOF'
#!/usr/bin/env bash
# ============================================================
# Cenário de homologação — approval by mail
# Servidor: srvglpi-homol (10.11.39.117) · Debian 12 · PHP 8.4.15 · MariaDB 10.5.21 · GLPI 10.0.17
# ------------------------------------------------------------
# Ajuste os caminhos conforme a instalação do GLPI neste servidor.
# Descobrir a raiz do GLPI:
#   grep -ri DocumentRoot /etc/apache2/sites-enabled/
#   (ou)  find / -name 'version' -path '*glpi*' 2>/dev/null
# ============================================================

# Raiz do GLPI (confirmado no servidor)
GLPI_ROOT="${GLPI_ROOT:-/var/www/verdanadesk/glpi}"

# Chave do plugin (= nome do diretório). NÃO mudar sem renomear tudo.
PLUGIN_KEY="approvalbymail"

# Diretório-alvo do plugin (se usar GLPI_PLUGIN_DIR custom, ajuste aqui)
PLUGIN_DIR="${PLUGIN_DIR:-$GLPI_ROOT/plugins/$PLUGIN_KEY}"

# Usuário/grupo do Apache no Debian 12
WEB_USER="${WEB_USER:-www-data}"
WEB_GROUP="${WEB_GROUP:-www-data}"

# Usuário GLPI usado pelo console para instalar o plugin (super-admin)
GLPI_CLI_USER="${GLPI_CLI_USER:-glpi}"

# Raiz do repositório e diretório de staging (derivados — não mexer)
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STAGING_DIR="$REPO_ROOT/staging"

ABM_EOF

cat > 'deploy/build.sh' <<'ABM_EOF'
#!/usr/bin/env bash
# Monta o diretório staging/ com o plugin pronto para deploy (runtime-only).
set -euo pipefail
. "$(dirname "$0")/config.sh"

echo "==> [1/4] Limpando staging"
rm -rf "$STAGING_DIR"
mkdir -p "$STAGING_DIR"

echo "==> [2/4] Minificando assets (css/js)"
if [ -f "$REPO_ROOT/package.json" ]; then
  ( cd "$REPO_ROOT" && npm run build )
else
  echo "    (sem package.json — pulando minificação)"
fi

echo "==> [3/4] Compilando locales (.po -> .mo)"
shopt -s nullglob
for po in "$REPO_ROOT"/locales/*.po; do
  msgfmt "$po" -o "${po%.po}.mo"
  echo "    $(basename "$po") -> $(basename "${po%.po}.mo")"
done
shopt -u nullglob

echo "==> [4/4] Copiando runtime para staging"
rsync -a \
  --exclude '.git' \
  --exclude '.gitignore' \
  --exclude 'node_modules' \
  --exclude 'staging' \
  --exclude 'deploy' \
  --exclude 'tools' \
  --exclude 'tests' \
  --exclude 'package.json' \
  --exclude 'package-lock.json' \
  --exclude 'Makefile' \
  --exclude 'css/src' \
  --exclude 'js/src' \
  --exclude '*.po' \
  "$REPO_ROOT"/ "$STAGING_DIR"/

echo "==> Staging pronto: $STAGING_DIR"

ABM_EOF

cat > 'deploy/deploy.sh' <<'ABM_EOF'
#!/usr/bin/env bash
# Implanta o staging/ no diretório live do GLPI (atualiza e substitui).
set -euo pipefail
. "$(dirname "$0")/config.sh"

if [ ! -d "$STAGING_DIR" ]; then
  echo "ERRO: staging não existe. Rode 'make build' antes." >&2
  exit 1
fi

if [ ! -f "$GLPI_ROOT/bin/console" ]; then
  echo "AVISO: não achei $GLPI_ROOT/bin/console — confira GLPI_ROOT em deploy/config.sh." >&2
fi

echo "==> Implantando em: $PLUGIN_DIR"
sudo mkdir -p "$PLUGIN_DIR"

# --delete: o que saiu do staging é removido da pasta live (substituição real)
sudo rsync -a --delete "$STAGING_DIR"/ "$PLUGIN_DIR"/

echo "==> Ajustando dono ($WEB_USER:$WEB_GROUP) e permissões"
sudo chown -R "$WEB_USER:$WEB_GROUP" "$PLUGIN_DIR"
sudo find "$PLUGIN_DIR" -type d -exec chmod 755 {} \;
sudo find "$PLUGIN_DIR" -type f -exec chmod 644 {} \;

echo "==> Limpando cache do GLPI"
sudo -u "$WEB_USER" php "$GLPI_ROOT/bin/console" cache:clear || \
  echo "    (cache:clear falhou — verifique manualmente)"

echo "==> Deploy concluído em $PLUGIN_DIR"

ABM_EOF

cat > 'Makefile' <<'ABM_EOF'
.RECIPEPREFIX := >
GLPI_ROOT ?= /var/www/verdanadesk/glpi
PLUGIN_KEY = approvalbymail
GLPI_CLI_USER ?= glpi

.PHONY: build deploy release install activate clean logs help

help:
> @echo "Alvos: build | deploy | release | install | activate | clean | logs"

build:
> @bash deploy/build.sh

deploy: build
> @bash deploy/deploy.sh

release: deploy
> @echo ">> Lembrete: atualizar CHANGELOG e criar a git tag desta versao."

install:
> @sudo -u www-data php $(GLPI_ROOT)/bin/console plugin:install -u $(GLPI_CLI_USER) $(PLUGIN_KEY)

activate:
> @sudo -u www-data php $(GLPI_ROOT)/bin/console plugin:activate $(PLUGIN_KEY)

clean:
> @rm -rf staging

logs:
> @sudo tail -n 80 -f $(GLPI_ROOT)/files/_log/php-errors.log
ABM_EOF

chmod +x tools/build_assets.sh tools/extract_locales.sh deploy/build.sh deploy/deploy.sh deploy/config.sh

echo "=== Arvore criada ==="
find . -type f -not -path './node_modules/*' -not -name 'bootstrap_approvalbymail.sh' | sort
echo
echo "Proximos: php -l setup.php && php -l hook.php -> git init -> npm install -> make deploy"
