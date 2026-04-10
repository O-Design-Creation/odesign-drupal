#!/usr/bin/env bash
# =============================================================================
# smoke-test.sh — Vérifications post-déploiement O'Design
#
# Appelé automatiquement par entrypoint.sh après chaque déploiement.
# Les échecs sont loggués en WARN mais ne bloquent pas le démarrage du conteneur.
#
# Usage manuel :
#   docker exec <container> bash /var/www/html/.docker/smoke-test.sh
# =============================================================================

DRUPAL_ROOT="${DRUPAL_ROOT:-/var/www/html}"
DRUSH="${DRUPAL_ROOT}/vendor/bin/drush"
PASS=0
WARN=0

ts() { date -u +%H:%M:%S; }
ok()   { echo "[smoke][$(ts)] ✓ $1"; PASS=$((PASS+1)); }
warn() { echo "[smoke][$(ts)] ⚠ $1" >&2; WARN=$((WARN+1)); }

cd "$DRUPAL_ROOT"

# ── Drupal bootstrappable ─────────────────────────────────────────────────────

VERSION=$("$DRUSH" status --field=drupal-version --no-interaction 2>/dev/null || echo "")
if echo "$VERSION" | grep -qE '^[0-9]+\.[0-9]+'; then
  ok "Drupal bootstrappable (v${VERSION})"
else
  warn "Drupal non bootstrappable (version: '${VERSION}')"
fi

# ── Produits importés ─────────────────────────────────────────────────────────

PRODUCT_COUNT=$("$DRUSH" php:eval \
  "echo \Drupal::entityQuery('commerce_product')->accessCheck(FALSE)->count()->execute();" \
  --no-interaction 2>/dev/null || echo "0")
if [ "${PRODUCT_COUNT:-0}" -ge 80 ] 2>/dev/null; then
  ok "Produits importés : ${PRODUCT_COUNT}"
else
  warn "Produits insuffisants : ${PRODUCT_COUNT:-0} (attendu ≥ 80)"
fi

# ── Store Commerce configuré ──────────────────────────────────────────────────

STORE=$("$DRUSH" php:eval \
  "echo \Drupal\commerce_store\Entity\Store::load(1) ? 'ok' : 'absent';" \
  --no-interaction 2>/dev/null || echo "absent")
if [ "$STORE" = "ok" ]; then
  ok "Store Commerce (id=1) présent"
else
  warn "Store Commerce absent — seed-shipping-methods.php à re-exécuter"
fi

# ── Configuration synchronisée ────────────────────────────────────────────────

DIFF_COUNT=$("$DRUSH" config:status --state=Different --no-interaction 2>/dev/null \
  | grep -c 'Different' 2>/dev/null || echo "0")
if [ "${DIFF_COUNT:-0}" -eq 0 ] 2>/dev/null; then
  ok "Configuration synchronisée (0 diff)"
else
  warn "Configuration désynchronisée : ${DIFF_COUNT} objet(s) en diff"
fi

# ── Search API indexé ─────────────────────────────────────────────────────────

INDEXED=$("$DRUSH" php:eval \
  "if (\$idx = \Drupal::entityTypeManager()->getStorage('search_api_index')->load('products')) { echo \$idx->getTrackerInstance()->getIndexedItemsCount(); } else { echo 'N/A'; }" \
  --no-interaction 2>/dev/null || echo "N/A")
if [ "$INDEXED" != "N/A" ] && [ "${INDEXED:-0}" -ge 80 ] 2>/dev/null; then
  ok "Search API indexé : ${INDEXED} produits"
else
  warn "Search API : ${INDEXED:-N/A} produits indexés (attendu ≥ 80)"
fi

# ── Stripe configuré ──────────────────────────────────────────────────────────

STRIPE_KEY=$("$DRUSH" php:eval \
  "echo \Drupal::config('commerce_payment.commerce_payment_gateway.stripe_sandbox')->get('configuration.secret_key');" \
  --no-interaction 2>/dev/null || echo "")
if echo "$STRIPE_KEY" | grep -qE '^sk_(test|live)_'; then
  ok "Stripe configuré (clé ${STRIPE_KEY:0:12}...)"
else
  warn "Stripe : clé manquante ou placeholder — vérifier STRIPE_SECRET_KEY"
fi

# ── SMTP configuré ────────────────────────────────────────────────────────────

SMTP_USER=$("$DRUSH" php:eval \
  "echo \Drupal::config('smtp.settings')->get('smtp_username');" \
  --no-interaction 2>/dev/null || echo "")
if [ -n "$SMTP_USER" ]; then
  ok "SMTP configuré (user: ${SMTP_USER})"
else
  warn "SMTP : smtp_username vide — vérifier BREVO_SMTP_USERNAME"
fi

# ── Résumé ───────────────────────────────────────────────────────────────────

echo ""
echo "[smoke][$(ts)] Résultat : ${PASS} OK / ${WARN} WARN"
if [ "$WARN" -gt 0 ]; then
  echo "[smoke][$(ts)] Des avertissements nécessitent une attention manuelle."
fi
echo ""
