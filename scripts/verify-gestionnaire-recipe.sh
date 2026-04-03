#!/usr/bin/env bash
set -euo pipefail

TARGET="${1:-local}"

if [[ "${TARGET}" == "local" ]]; then
  BASE_URL="https://odesign-drupal.ddev.site"
else
  BASE_URL="https://dev-atelierodesign.pantheonsite.io"
fi

get_uli() {
  if [[ "${TARGET}" == "local" ]]; then
    ddev drush uli --name=odesign
  else
    terminus drush "${TARGET}" -- user:login odesign | tail -n 1
  fi
}

echo "==> Vérification recette gestionnaire (${TARGET})"
echo "base_url=${BASE_URL}"
echo "uli_mode=fresh-per-check"

echo "==> 1/3 workflow runtime"
bash scripts/verify-order-workflow.sh "${TARGET}"

echo "==> 2/3 dashboard quick links (Playwright)"
ULI_QUICK_LINKS="$(get_uli)"
PLAYWRIGHT_BASE_URL="${BASE_URL}" \
  node playwright/scripts/check-dashboard-quick-links-uli.js "${ULI_QUICK_LINKS}"

echo "==> 3/3 toolbar commandes (Playwright)"
ULI_TOOLBAR="$(get_uli)"
PLAYWRIGHT_BASE_URL="${BASE_URL}" \
  node playwright/scripts/check-toolbar-orders-filter-uli.js "${ULI_TOOLBAR}"

echo "OK: recette gestionnaire outillée conforme (${TARGET})"
