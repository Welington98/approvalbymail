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

