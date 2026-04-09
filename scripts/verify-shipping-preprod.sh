#!/usr/bin/env bash
set -euo pipefail

TARGET="${1:-local}"

run_drush() {
  if [[ "${TARGET}" == "local" ]]; then
    ddev drush "$@"
  else
    terminus drush "${TARGET}" -- "$@"
  fi
}

echo "==> Vérification shipping (${TARGET})"

METHODS_OUTPUT="$(run_drush php:eval '$methods = \Drupal::entityTypeManager()->getStorage("commerce_shipping_method")->loadMultiple(); foreach ($methods as $m) { if ($m->isEnabled()) { echo $m->id() . "|" . $m->label() . PHP_EOL; } }')"
echo "${METHODS_OUTPUT}"

ACTIVE_COUNT="$(grep -c '|' <<< "${METHODS_OUTPUT}" || true)"
if [[ "${ACTIVE_COUNT}" -lt 1 ]]; then
  echo "ERREUR: aucune méthode d'expédition active." >&2
  exit 1
fi

if ! grep -Fq "Mondial Relay" <<< "${METHODS_OUTPUT}"; then
  echo "ERREUR: méthode Mondial Relay absente." >&2
  exit 1
fi

FLOW_OUTPUT="$(run_drush php:eval '$flow = \Drupal::entityTypeManager()->getStorage("commerce_checkout_flow")->load("default"); $panes = $flow->get("configuration")["panes"] ?? []; if (isset($panes["shipping_information"])) { echo "shipping_pane=1\n"; } else { echo "shipping_pane=0\n"; }')"
echo "${FLOW_OUTPUT}"
if ! grep -Fq "shipping_pane=1" <<< "${FLOW_OUTPUT}"; then
  echo "ERREUR: le checkout flow n'expose pas shipping_information." >&2
  exit 1
fi

TRAITS_OUTPUT="$(run_drush php:eval '$type = \Drupal::entityTypeManager()->getStorage("commerce_product_variation_type")->load("default"); echo implode(",", $type->getTraits()) . PHP_EOL;')"
echo "variation_traits=${TRAITS_OUTPUT}"
if ! grep -Fq "purchasable_entity_shippable" <<< "${TRAITS_OUTPUT}"; then
  echo "ERREUR: trait purchasable_entity_shippable absent du type de variation default." >&2
  exit 1
fi

echo "OK: shipping configuration cohérente (${TARGET})."
