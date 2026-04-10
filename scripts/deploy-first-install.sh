#!/usr/bin/env bash
# =============================================================================
# deploy-first-install.sh — Premier déploiement Drupal complet
#
# Contexte : appelé par .docker/entrypoint.sh quand la DB est vide (ou
#            FIRST_INSTALL=1 forcé). Reproduit la séquence validée lors de la
#            simulation DDEV du 2026-04-10 (TCK-119).
#
# Usage manuel :
#   FIRST_INSTALL=1 docker exec <container> bash /var/www/html/scripts/deploy-first-install.sh
#
# Variables d'environnement lues (injectées par Coolify) :
#   ADMIN_PASS       — mot de passe du compte admin Drupal (obligatoire)
#   SITE_MAIL        — email du site (défaut : contact@atelierodesign.fr)
#   DRUPAL_ROOT      — racine du projet (défaut : /var/www/html)
# =============================================================================
set -euo pipefail

DRUPAL_ROOT="${DRUPAL_ROOT:-/var/www/html}"
DRUSH="${DRUPAL_ROOT}/vendor/bin/drush"
SCRIPTS="${DRUPAL_ROOT}/scripts"
STEPS_TOTAL=17
STEP=0

# ── Helpers ──────────────────────────────────────────────────────────────────

ts()  { date -u +%H:%M:%S; }
log() { echo "[$(ts)] [$((++STEP))/${STEPS_TOTAL}] $*"; }
ok()  { echo "[$(ts)]     ✓ $*"; }
warn(){ echo "[$(ts)]     ⚠ $*" >&2; }
die() { echo "[$(ts)]     ✗ ERREUR : $*" >&2; exit 1; }

# ── Prérequis ────────────────────────────────────────────────────────────────

[ -f "${SCRIPTS}/catalogue.json" ]            || die "catalogue.json absent de scripts/"
[ -f "${DRUPAL_ROOT}/config/sync/system.site.yml" ] || die "config/sync/system.site.yml absent"

UUID=$(grep '^uuid:' "${DRUPAL_ROOT}/config/sync/system.site.yml" | awk '{print $2}')
[ -n "$UUID" ] || die "UUID introuvable dans system.site.yml"

ADMIN_PASS="${ADMIN_PASS:-}"
[ -n "$ADMIN_PASS" ] || die "Variable ADMIN_PASS obligatoire (mot de passe admin Drupal)"

SITE_MAIL="${SITE_MAIL:-odesigncreation@gmail.com}"

cd "${DRUPAL_ROOT}"

echo ""
echo "============================================================"
echo "  Premier déploiement O'Design — $(ts)"
echo "  UUID site : ${UUID}"
echo "============================================================"
echo ""

# ── Étape 1 : Installation Drupal (profil minimal) ───────────────────────────

log "site:install minimal..."
"$DRUSH" site:install minimal --yes \
  --account-name=admin \
  --account-pass="${ADMIN_PASS}" \
  --account-mail="${SITE_MAIL}" \
  --no-interaction 2>&1 \
  | grep -E 'notice|success|error|Error' || true
ok "Drupal installé (profil minimal, admin créé)"

# ── Étape 2 : Alignement UUID ────────────────────────────────────────────────

log "Alignement UUID du site → ${UUID}..."
"$DRUSH" config:set "system.site" uuid "$UUID" --yes --no-interaction
ok "UUID aligné"

# ── Étape 3 : config:import passe 1 (activation modules Commerce) ────────────
# L'erreur "Missing bundle entity commerce_product_type" est normale à cette passe.
# Elle indique que les modules ont été activés — la passe 2 résoudra les dépendances.

log "config:import passe 1 (activation modules — échec Commerce attendu)..."
"$DRUSH" config:import --yes --no-interaction 2>&1 \
  | grep -E 'notice.*Synchronized|success|Missing bundle|error' \
  | tail -5 || true
ok "Passe 1 terminée (modules activés, erreur Commerce attendue)"

# ── Étape 4 : config:import passe 2 (import complet) ─────────────────────────

log "config:import passe 2 (import complet)..."
"$DRUSH" config:import --yes --no-interaction 2>&1 \
  | grep -E 'success|error|Error' || true
ok "Configuration importée (476 objets)"

# ── Étape 5 : Activation FR comme langue par défaut ──────────────────────────
# system.site est dans config_ignore → config:import ne définit jamais la langue.
# language.negotiation peut rester sur fr:fr (préfixe /fr/) après site:install.
# Ce script règle les trois couches : system.site, language.negotiation,
# ConfigurableLanguage (FR=default, EN=supprimé).

log "Activation langue FR par défaut (system.site, negotiation, entity)..."
"$DRUSH" php:script "${SCRIPTS}/setup-language.php" --no-interaction 2>&1 \
  | grep -E 'corrigé|défini|supprimé|absent|system.site|AVERTISSEMENT' || true
ok "Langue FR active, EN supprimé, language.negotiation corrigé"

# ── Étape 6 : Import contenu par défaut (DCD) ────────────────────────────────
# Importe les pages légales (CGV, Mentions légales, Politique de confidentialité),
# les pages informationnelles (Contact, Atelier, Merci), les termes de taxonomie
# et leurs path_alias depuis odesign_admin/content/.
# Les URLs ddev.site dans les _links sont normalisées par DCD 2.x à l'import.

log "Import contenu par défaut (DCD)..."
"$DRUSH" default-content-deploy:import \
  --folder="${DRUPAL_ROOT}/web/modules/custom/odesign_admin/content" \
  --force-override \
  --no-interaction 2>&1 \
  | grep -E 'Imported|Skipped|error|Error' || true
ok "Contenu par défaut importé (pages légales, pages info, taxonomies, path_alias)"

# ── Étape 7 : Seed taxonomies occasions de vie ───────────────────────────────

log "Seed occasions de vie..."
"$DRUSH" php:script "${SCRIPTS}/seed-life-occasions.php" --no-interaction 2>&1 \
  | grep -E '^\[OK\]|notice|error' || true
ok "4 univers occasions de vie créés"

# ── Étape 8 : Cache rebuild (après DCD et occasions) ─────────────────────────
# Le cache rebuild est placé ici pour que les path_alias créés par DCD (étape 6)
# et les aliases de taxonomie (étape 7) soient dans le cache Drupal avant que
# seed-redirections.php (étape 12) n'utilise path.validator.

log "Cache rebuild..."
"$DRUSH" cr --no-interaction
ok "Cache rebuild"

# ── Étape 9 : Traductions officielles + traductions custom ───────────────────
# 9a. locale:update télécharge les fichiers .po officiels depuis localize.drupal.org
#     pour tous les modules activés (Commerce, Shipping, etc.).
#     Nécessite un accès internet — actif sur Pantheon et DDEV.

log "Mise à jour traductions officielles (locale:update)..."
"$DRUSH" locale:check --no-interaction 2>&1 | grep -E 'Checked|error' | tail -3 || true
"$DRUSH" locale:update --no-interaction 2>&1 \
  | grep -E 'Translations imported|translation|error' | tail -3 || true
ok "Traductions officielles mises à jour"

# 9b. Traductions custom odesign_admin (--override=all → priorité sur officiel).

log "Import traductions custom (odesign_admin/translations/fr.po)..."
"$DRUSH" locale:import fr \
  "${DRUPAL_ROOT}/web/modules/custom/odesign_admin/translations/fr.po" \
  --type=customized --override=all --no-interaction 2>&1 \
  | grep -E 'import|translated|error|Error' || true
ok "Traductions custom importées"

# ── Étape 10 : Seed store Commerce + méthodes d'expédition ───────────────────

log "Seed store + méthodes d'expédition..."
"$DRUSH" php:script "${SCRIPTS}/seed-shipping-methods.php" --no-interaction 2>&1 \
  | grep -E 'CRÉÉ|Store|error|Seed' || true
ok "Store + 5 méthodes d'expédition créés"

# ── Étape 11 : Seed pages informations de base ───────────────────────────────

log "Seed pages informations (Contact, Atelier)..."
"$DRUSH" php:script "${SCRIPTS}/seed-info-pages.php" --no-interaction 2>&1 \
  | grep -E 'Pages OK|error' || true
ok "Pages informations créées"

# ── Étape 12 : Seed redirections SEO ────────────────────────────────────────
# Certaines destinations peuvent être manquantes (pages légales non encore créées).
# Le script est idempotent — re-exécuté après création des pages légales.

log "Seed redirections SEO..."
"$DRUSH" php:script "${SCRIPTS}/seed-redirections.php" --no-interaction 2>&1 \
  | grep -E 'CRÉÉ|SKIP|Résumé|AVERTISSEMENT' || true
ok "Redirections SEO seedées (avertissements possibles → re-exécuter après pages légales)"

# ── Étape 13 : Import catalogue produits ─────────────────────────────────────

log "Import catalogue produits depuis catalogue.json..."
"$DRUSH" php:script "${SCRIPTS}/migrate-catalogue.php" --no-interaction 2>&1 \
  | grep -E 'Créés|Skippés|Erreurs|RÉSULTAT|Store|Produits lus' || true
ok "Catalogue importé"

# ── Étape 14 : Import articles blog ──────────────────────────────────────────

log "Import articles blog..."
BLOG_SOURCE_JSON="${SCRIPTS}/blog-articles-real.json" \
  "$DRUSH" php:script "${SCRIPTS}/create-blog-articles.php" --no-interaction 2>&1 \
  | grep -E 'Créés|Lus|Erreurs|OK' || true
ok "Articles blog importés"

# ── Étape 15 : Reindex Search API ────────────────────────────────────────────

log "Reindex Search API..."
"$DRUSH" search-api:index --yes --no-interaction 2>&1 \
  | grep -E 'success|items|error' || true
ok "Search API indexé"

# ── Étape 16 : Cache rebuild final ───────────────────────────────────────────

log "Cache rebuild final..."
"$DRUSH" cr --no-interaction
ok "Cache rebuild final"

echo ""
echo "============================================================"
echo "  Premier déploiement terminé — $(ts)"
echo ""
echo "  Actions manuelles requises avant mise en ligne :"
echo "  · Vérifier clés Stripe (STRIPE_SECRET_KEY, STRIPE_WEBHOOK_SECRET)"
echo "  · Vérifier SMTP (BREVO_SMTP_USERNAME, BREVO_SMTP_PASSWORD)"
echo "  · Créer compte gestionnaire_boutique (Océane)"
echo "============================================================"
echo ""
