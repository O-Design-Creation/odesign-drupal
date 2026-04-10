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
#   SITE_URL              (https://preprod.atelierodesign.fr — pour base_url Drupal)
#   SITE_MAIL             (défaut : contact@atelierodesign.fr)
#   STRIPE_SECRET_KEY     (sk_test_... ou sk_live_...)
#   STRIPE_WEBHOOK_SECRET (whsec_...)
#   BREVO_SMTP_USERNAME
#   BREVO_SMTP_PASSWORD
#   IP_WHITELIST_ENABLED  (1 = activer la restriction IP nginx)
#   IP_WHITELIST_RANGES   (CSV CIDR ex: 88.183.56.216/32,78.241.156.16/32)
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

// ── URL du site (permet à Drupal de connaître son URL absolue) ───────────────
// Utilisé pour les emails transactionnels, les liens canoniques, etc.
if ('${SITE_URL:-}') {
  \$settings['base_url'] = rtrim('${SITE_URL:-}', '/');
}

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

# ── 2a. Générer le fichier de whitelist IP nginx (optionnel) ─────────────────
# Fichier inclus par nginx.conf. Sans activation, le fichier reste neutre.
IP_WHITELIST_FILE="/etc/nginx/conf.d/ip-whitelist.conf"
{
  echo "# auto-generated by entrypoint.sh"
  if [ "${IP_WHITELIST_ENABLED:-0}" = "1" ]; then
    if [ -n "${IP_WHITELIST_RANGES:-}" ]; then
      IFS=',' read -r -a whitelist_ranges <<< "${IP_WHITELIST_RANGES}"
      for range in "${whitelist_ranges[@]}"; do
        range_trimmed="$(echo "$range" | xargs)"
        if [ -n "$range_trimmed" ]; then
          echo "allow ${range_trimmed};"
        fi
      done
      echo "deny all;"
      log "Whitelist IP nginx activée (IP_WHITELIST_RANGES)."
    else
      log "IP_WHITELIST_ENABLED=1 mais IP_WHITELIST_RANGES est vide: whitelist non appliquée."
    fi
  else
    log "Whitelist IP nginx désactivée (IP_WHITELIST_ENABLED!=1)."
  fi
} > "${IP_WHITELIST_FILE}"
ok "Fichier ${IP_WHITELIST_FILE} généré."

# ── 2b. Corriger les permissions de sites/default/files/ ─────────────────────
# Les sous-dossiers copiés dans l'image sont owned root. On force www-data à
# chaque démarrage pour que PHP puisse écrire CSS agrégés, image styles, etc.
log "Correction des permissions sites/default/files/ ..."
chown -R www-data:www-data "${WEB_ROOT}/sites/default/files" 2>/dev/null || true
find "${WEB_ROOT}/sites/default/files" -type d -exec chmod 775 {} \; 2>/dev/null || true
find "${WEB_ROOT}/sites/default/files" -type f -exec chmod 664 {} \; 2>/dev/null || true
ok "Permissions files/ corrigées."

# ── 2c. Seeder le volume si vide ─────────────────────────────────────────────
# Si sites/default/files/ est monté comme volume vide (premier déploiement),
# copier les assets initiaux (produits, blog) depuis /var/www/html/seeds/.
SEEDS_DIR="${DRUPAL_ROOT}/seeds"
if [ -d "${SEEDS_DIR}" ]; then
  # shopt nullglob : évite l'erreur si seeds/ est vide (glob non résolu)
  shopt -s nullglob
  for seed_dir in "${SEEDS_DIR}"/*/; do
    dir_name=$(basename "${seed_dir}")
    target="${WEB_ROOT}/sites/default/files/${dir_name}"
    if [ ! -d "${target}" ]; then
      log "Seeding files/${dir_name} depuis image..."
      cp -r "${seed_dir}" "${target}" || { log "WARNING: seeding ${dir_name} échoué (ignoré)"; continue; }
      chown -R www-data:www-data "${target}" || true
      ok "Seeding files/${dir_name} terminé."
    fi
  done
  shopt -u nullglob
fi

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

# ── 4c. Correction finale des permissions ────────────────────────────────────
# deploy-first-install.sh et drush CLI tournent en root → peuvent créer des
# fichiers root:root dans sites/default/files/. On re-chown systématiquement
# après toute opération de déploiement pour que PHP-FPM (www-data) puisse écrire.
log "Correction finale des permissions sites/default/files/ ..."
chown -R www-data:www-data "${WEB_ROOT}/sites/default/files" 2>/dev/null || true
ok "Permissions files/ corrigées (post-déploiement)."

# ── 5. Smoke test post-déploiement ────────────────────────────────────────────

if [ -f "${DRUPAL_ROOT}/.docker/smoke-test.sh" ]; then
  log "Smoke test..."
  bash "${DRUPAL_ROOT}/.docker/smoke-test.sh" || true
fi

# ── 6. Démarrer les services ─────────────────────────────────────────────────

log "Démarrage des services (supervisord)..."
exec "$@"
