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

# Guarda de segurança: o rsync --delete só pode mirar .../plugins/<chave>.
# Evita que um GLPI_ROOT/PLUGIN_DIR mal configurado apague algo fora do plugin.
case "$PLUGIN_DIR" in
  */plugins/"$PLUGIN_KEY") : ;;
  *)
    echo "ERRO: PLUGIN_DIR fora de .../plugins/$PLUGIN_KEY — abortando para não apagar nada indevido." >&2
    echo "      PLUGIN_DIR atual: $PLUGIN_DIR" >&2
    exit 1
    ;;
esac

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
