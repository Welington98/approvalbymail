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

