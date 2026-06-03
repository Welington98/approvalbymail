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

