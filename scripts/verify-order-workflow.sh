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

echo "==> Vérification workflow commandes (${TARGET})"

STATES_OUTPUT="$(run_drush php:eval '$def = \Drupal::service("plugin.manager.workflow")->getDefinition("odesign_order"); foreach ($def["states"] as $id => $state) { echo $id . "|" . $state["label"] . PHP_EOL; }')"
echo "${STATES_OUTPUT}"

for expected in \
  "processing|En fabrication (atelier laser)" \
  "quality_check|Contrôle qualité" \
  "packaged|Emballée / prête à expédier"; do
  if ! grep -Fq "${expected}" <<< "${STATES_OUTPUT}"; then
    echo "ERREUR: état manquant ou label incorrect: ${expected}" >&2
    exit 1
  fi
done

TOOLBAR_URL="$(run_drush php:eval '$container = \Drupal::getContainer(); $service_id = $container->has("odesign_orders.lazy_builders") ? "odesign_orders.lazy_builders" : "odesign_admin.lazy_builders"; $lb = \Drupal::service($service_id); $build = $lb->renderOrdersInbox(); echo $build["#url"]->toString();')"
echo "toolbar_url=${TOOLBAR_URL}"

if grep -Fq "/admin/boutique/commandes-a-traiter" <<< "${TOOLBAR_URL}"; then
  echo "toolbar cible la route dédiée commandes à traiter."
else
  for expected_state in pending_payment processing quality_check packaged; do
    if ! grep -Fq "${expected_state}" <<< "${TOOLBAR_URL}"; then
      echo "ERREUR: état absent du filtre toolbar: ${expected_state}" >&2
      exit 1
    fi
  done
fi

echo "OK: workflow + filtre toolbar conformes."
