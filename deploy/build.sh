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

