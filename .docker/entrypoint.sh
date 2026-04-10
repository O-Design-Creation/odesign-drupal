#!/bin/bash
# =============================================================================
# entrypoint.sh — Point d'entrée du conteneur O'Design
#
# Deux chemins selon l'état de la base de données :
#
#   PREMIER DÉPLOIEMENT (DB vide ou FIRST_INSTALL=1)
#     → scripts/deploy-first-install.sh
#        site:install + UUID + config:import (×2) + seeds + catalogue + blog
#
#   MISE À JOUR COURANTE (DB existante)
#     → drush updatedb + drush config:import + drush cache:rebuild
#
# Variables d'environnement obligatoires (Coolify) :
#   DB_HOST, DB_NAME, DB_USER, DB_PASS
#   DRUPAL_HASH_SALT
#   DRUPAL_TRUSTED_HOST   (ex: www.atelierodesign.fr — hôte principal)
#                         Plusieurs hôtes : séparés par | (ex: www.atelierodesign.fr|atelierodesign.fr)
#   ADMIN_PASS            (premier déploiement uniquement)
#
# Variables optionnelles :
#   DB_PORT               (défaut : 3306)
#   SITE_MAIL             (défaut : contact@atelierodesign.fr)
#   STRIPE_SECRET_KEY     (sk_test_... ou sk_live_...)
#   STRIPE_WEBHOOK_SECRET (whsec_...)
#   BREVO_SMTP_USERNAME
#   BREVO_SMTP_PASSWORD
#   FIRST_INSTALL         (1 = forcer le chemin premier déploiement)
# =============================================================================
set -e

DRUPAL_ROOT=/var/www/html
WEB_ROOT=${DRUPAL_ROOT}/web
DRUSH="${DRUPAL_ROOT}/vendor/bin/drush"

ts()  { date -u +%H:%M:%S; }
log() { echo "[entrypoint][$(ts)] $*"; }
ok()  { echo "[entrypoint][$(ts)] ✓ $*"; }
err() { echo "[entrypoint][$(ts)] ✗ $*" >&2; exit 1; }

# ── 1. Attendre que la base de données soit disponible ────────────────────────

log "Attente de la base de données (${DB_HOST}/${DB_NAME})..."
MAX_RETRIES=30
RETRIES=0
until php -r "new PDO('mysql:host=${DB_HOST};dbname=${DB_NAME}', '${DB_USER}', '${DB_PASS}');" 2>/dev/null; do
  RETRIES=$((RETRIES+1))
  [ $RETRIES -ge $MAX_RETRIES ] && err "Base de données injoignable après ${MAX_RETRIES} tentatives."
  sleep 3
done
ok "Base de données disponible."

# ── 2. Écrire settings.local.php ─────────────────────────────────────────────
# Les variables PHP ($databases, $settings, $config) sont échappées avec \$
# pour ne pas être interprétées par bash dans le heredoc.

mkdir -p "${WEB_ROOT}/sites/default"
cat > "${WEB_ROOT}/sites/default/settings.local.php" << SETTINGS_EOF
<?php
/**
 * settings.local.php — Auto-généré par entrypoint.sh
 * Ne pas modifier manuellement : le fichier est regénéré à chaque démarrage du conteneur.
 */

// ── Base de données ──────────────────────────────────────────────────────────
\$databases['default']['default'] = [
  'driver'    => 'mysql',
  'host'      => '${DB_HOST}',
  'port'      => '${DB_PORT:-3306}',
  'database'  => '${DB_NAME}',
  'username'  => '${DB_USER}',
  'password'  => '${DB_PASS}',
  'prefix'    => '',
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
];

// ── Sécurité ─────────────────────────────────────────────────────────────────
\$settings['hash_salt'] = '${DRUPAL_HASH_SALT}';
\$settings['trusted_host_patterns'] = array_map(
  fn(\$h) => '^' . preg_quote(trim(\$h)) . '$',
  explode('|', '${DRUPAL_TRUSTED_HOST}')
);

// ── Email du site ─────────────────────────────────────────────────────────────
\$config['system.site']['mail'] = '${SITE_MAIL:-contact@atelierodesign.fr}';

// ── Stripe — injecté depuis variables d'environnement Coolify ────────────────
// Écrase la valeur PLACEHOLDER de config/sync sans modifier la config exportée.
if (\$stripe_secret = getenv('STRIPE_SECRET_KEY')) {
  \$config['commerce_payment.commerce_payment_gateway.stripe_sandbox']['configuration']['secret_key']         = \$stripe_secret;
  \$config['commerce_payment.commerce_payment_gateway.stripe_sandbox']['configuration']['publishable_key']    = getenv('STRIPE_PUBLISHABLE_KEY') ?: '';
  \$config['commerce_payment.commerce_payment_gateway.stripe_sandbox']['configuration']['webhook_signing_secret'] = getenv('STRIPE_WEBHOOK_SECRET') ?: '';
}

// ── SMTP Brevo — injecté depuis variables d'environnement Coolify ────────────
if (\$smtp_user = getenv('BREVO_SMTP_USERNAME')) {
  \$config['smtp.settings']['smtp_username'] = \$smtp_user;
  \$config['smtp.settings']['smtp_password'] = getenv('BREVO_SMTP_PASSWORD') ?: '';
  \$config['smtp.settings']['smtp_on']       = TRUE;
}
SETTINGS_EOF

ok "settings.local.php écrit."

# ── 3. Détecter premier déploiement vs mise à jour ────────────────────────────

cd "${DRUPAL_ROOT}"

DRUPAL_INSTALLED=false
if [ "${FIRST_INSTALL:-0}" != "1" ]; then
  if "$DRUSH" status --field=drupal-version --no-interaction 2>/dev/null | grep -qE '[0-9]+\.[0-9]+'; then
    DRUPAL_INSTALLED=true
  fi
fi

# ── 4a. PREMIER DÉPLOIEMENT ───────────────────────────────────────────────────

if [ "$DRUPAL_INSTALLED" = "false" ]; then
  if [ "${FIRST_INSTALL:-0}" = "1" ]; then
    log "FIRST_INSTALL=1 — réinstallation forcée."
  else
    log "Base vide détectée — premier déploiement."
  fi

  [ -n "${ADMIN_PASS:-}" ] || err "Variable ADMIN_PASS obligatoire au premier déploiement."

  bash "${DRUPAL_ROOT}/scripts/deploy-first-install.sh" \
    || err "deploy-first-install.sh a échoué. Consulter les logs ci-dessus."

# ── 4b. MISE À JOUR COURANTE ──────────────────────────────────────────────────

else
  log "Drupal déjà installé — chemin mise à jour."

  log "drush updatedb..."
  "$DRUSH" updatedb -y --no-interaction \
    || err "drush updatedb a échoué."
  ok "updatedb"

  log "drush config:import..."
  "$DRUSH" config:import -y --no-interaction \
    || err "drush config:import a échoué."
  ok "config:import"

  log "drush cache:rebuild..."
  "$DRUSH" cache:rebuild --no-interaction
  ok "cache:rebuild"
fi

# ── 5. Smoke test post-déploiement ────────────────────────────────────────────

if [ -f "${DRUPAL_ROOT}/.docker/smoke-test.sh" ]; then
  log "Smoke test..."
  bash "${DRUPAL_ROOT}/.docker/smoke-test.sh" || true
fi

# ── 6. Démarrer les services ─────────────────────────────────────────────────

log "Démarrage des services (supervisord)..."
exec "$@"
