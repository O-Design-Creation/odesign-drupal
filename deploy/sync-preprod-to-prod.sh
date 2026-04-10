#!/usr/bin/env bash
# =============================================================================
# sync-preprod-to-prod.sh — Clone de préprod vers prod (pré-bascule DNS)
#
# Exécuté sur le VPS host (pas dans un conteneur).
# Prérequis : Docker, accès root ou appartenance au groupe docker.
#
# Usage :
#   bash /opt/odesign/deploy/sync-preprod-to-prod.sh
#
# Variables d'environnement optionnelles (auto-détectées sinon) :
#   PREPROD_CONTAINER  — nom ou ID du conteneur préprod
#   PROD_CONTAINER     — nom ou ID du conteneur prod
#   LOG_DIR            — répertoire des logs (défaut : /var/log/odesign)
#   DRY_RUN            — 1 = analyse sans modifier prod
#
# Ce script :
#   1. Dump la DB depuis le conteneur préprod
#   2. Importe la DB dans le conteneur prod
#   3. Synchronise les fichiers média (public://*)
#   4. Redémarre le conteneur prod (régénère settings.local.php avec les secrets prod)
#   5. Attend le démarrage, rebuild le cache
#   6. Lance le smoke test et produit un rapport
# =============================================================================
set -euo pipefail

# ── Configuration ─────────────────────────────────────────────────────────────

LOG_DIR="${LOG_DIR:-/var/log/odesign}"
DRY_RUN="${DRY_RUN:-0}"
TIMESTAMP=$(date +%Y%m%d-%H%M%S)
LOG_FILE="${LOG_DIR}/sync-${TIMESTAMP}.log"
DUMP_FILE="/tmp/odesign-sync-dump-${TIMESTAMP}.sql"
MEDIA_TMP="/tmp/odesign-sync-media-${TIMESTAMP}"

# ── Helpers ───────────────────────────────────────────────────────────────────

ts()   { date '+%H:%M:%S'; }
log()  { echo "[sync][$(ts)] $*" | tee -a "$LOG_FILE"; }
ok()   { echo "[sync][$(ts)] ✓ $*" | tee -a "$LOG_FILE"; }
warn() { echo "[sync][$(ts)] ⚠ $*" | tee -a "$LOG_FILE" >&2; }
die()  { echo "[sync][$(ts)] ✗ ERREUR : $*" | tee -a "$LOG_FILE" >&2; exit 1; }

drush_preprod() { docker exec "$PREPROD_CONTAINER" bash -c "cd /var/www/html && vendor/bin/drush $* --no-interaction"; }
drush_prod()    { docker exec "$PROD_CONTAINER"    bash -c "cd /var/www/html && vendor/bin/drush $* --no-interaction"; }

# ── Init logs ─────────────────────────────────────────────────────────────────

mkdir -p "$LOG_DIR"
echo "" | tee -a "$LOG_FILE" >/dev/null
log "======================================================="
log "  Sync préprod → prod — ${TIMESTAMP}"
[ "$DRY_RUN" = "1" ] && log "  MODE DRY-RUN — aucune modification en prod"
log "======================================================="

# ── Détection des conteneurs ──────────────────────────────────────────────────

if [ -z "${PREPROD_CONTAINER:-}" ]; then
  PREPROD_CONTAINER=$(docker ps --filter "name=drupal-preprod" --format "{{.Names}}" | head -1)
fi
if [ -z "${PROD_CONTAINER:-}" ]; then
  PROD_CONTAINER=$(docker ps --filter "name=drupal-prod" --format "{{.Names}}" | grep -v preprod | head -1)
fi

[ -n "$PREPROD_CONTAINER" ] || die "Conteneur préprod introuvable (cherché : drupal-preprod). Définir PREPROD_CONTAINER."
[ -n "$PROD_CONTAINER"    ] || die "Conteneur prod introuvable (cherché : drupal-prod). Définir PROD_CONTAINER."

docker inspect "$PREPROD_CONTAINER" >/dev/null 2>&1 || die "Conteneur '$PREPROD_CONTAINER' non trouvé ou arrêté."
docker inspect "$PROD_CONTAINER"    >/dev/null 2>&1 || die "Conteneur '$PROD_CONTAINER' non trouvé ou arrêté."

PREPROD_STATUS=$(docker inspect --format='{{.State.Status}}' "$PREPROD_CONTAINER")
PROD_STATUS=$(docker inspect    --format='{{.State.Status}}' "$PROD_CONTAINER")

[ "$PREPROD_STATUS" = "running" ] || die "Conteneur préprod '$PREPROD_CONTAINER' n'est pas en cours d'exécution (état : $PREPROD_STATUS)."
[ "$PROD_STATUS"    = "running" ] || die "Conteneur prod '$PROD_CONTAINER' n'est pas en cours d'exécution (état : $PROD_STATUS)."

log "  Préprod : $PREPROD_CONTAINER ($PREPROD_STATUS)"
log "  Prod    : $PROD_CONTAINER ($PROD_STATUS)"
log "  Log     : $LOG_FILE"
echo ""

# ── Vérification Drupal préprod bootstrappable ────────────────────────────────

log "[0/6] Vérification préprod..."
PREPROD_VERSION=$(drush_preprod "status --field=drupal-version" 2>/dev/null || echo "")
echo "$PREPROD_VERSION" | grep -qE '^[0-9]+' || die "Drupal préprod non bootstrappable. Vérifier l'état du conteneur."
ok "Préprod bootstrappable (Drupal $PREPROD_VERSION)"

PROD_VERSION=$(drush_prod "status --field=drupal-version" 2>/dev/null || echo "")
if ! echo "$PROD_VERSION" | grep -qE '^[0-9]+'; then
  warn "Drupal prod non bootstrappable — le conteneur prod sera initialisé par le restart."
fi

if [ "$DRY_RUN" = "1" ]; then
  log "DRY-RUN : fin de la simulation. Aucune action exécutée."
  exit 0
fi

# ── Étape 1 : Dump DB préprod ─────────────────────────────────────────────────

log "[1/6] Dump DB préprod..."
docker exec "$PREPROD_CONTAINER" bash -c \
  "cd /var/www/html && vendor/bin/drush sql:dump --result-file=/tmp/sync-dump.sql --no-interaction" \
  >> "$LOG_FILE" 2>&1
docker cp "${PREPROD_CONTAINER}:/tmp/sync-dump.sql" "$DUMP_FILE"
docker exec "$PREPROD_CONTAINER" rm -f /tmp/sync-dump.sql
DUMP_SIZE=$(du -sh "$DUMP_FILE" | cut -f1)
ok "Dump créé : $DUMP_FILE ($DUMP_SIZE)"

# ── Étape 2 : Import DB prod ──────────────────────────────────────────────────

log "[2/6] Import DB prod (drop + import)..."
docker cp "$DUMP_FILE" "${PROD_CONTAINER}:/tmp/sync-dump.sql"
docker exec "$PROD_CONTAINER" bash -c \
  "cd /var/www/html && vendor/bin/drush sql:drop --yes --no-interaction" \
  >> "$LOG_FILE" 2>&1
docker exec "$PROD_CONTAINER" bash -c \
  "cd /var/www/html && vendor/bin/drush sql:query --file=/tmp/sync-dump.sql --no-interaction" \
  >> "$LOG_FILE" 2>&1
docker exec "$PROD_CONTAINER" rm -f /tmp/sync-dump.sql
rm -f "$DUMP_FILE"
ok "DB importée en prod"

# ── Étape 3 : Sync fichiers média ─────────────────────────────────────────────

log "[3/6] Sync fichiers média préprod → prod..."
mkdir -p "$MEDIA_TMP"
docker cp "${PREPROD_CONTAINER}:/var/www/html/web/sites/default/files/." "$MEDIA_TMP/" \
  >> "$LOG_FILE" 2>&1
MEDIA_COUNT=$(find "$MEDIA_TMP" -type f | wc -l)
docker cp "${MEDIA_TMP}/." "${PROD_CONTAINER}:/var/www/html/web/sites/default/files/" \
  >> "$LOG_FILE" 2>&1
docker exec "$PROD_CONTAINER" \
  chown -R www-data:www-data /var/www/html/web/sites/default/files/ \
  >> "$LOG_FILE" 2>&1
rm -rf "$MEDIA_TMP"
ok "Fichiers média synchronisés : ${MEDIA_COUNT} fichiers"

# ── Étape 4 : Restart conteneur prod ─────────────────────────────────────────
# Le restart régénère settings.local.php depuis les env vars Coolify prod :
# - Stripe clés live
# - SMTP prod
# - Domaine prod (trusted_host)
# Le chemin UPDATE de l'entrypoint (updb + cim + cr) sera exécuté automatiquement.

log "[4/6] Restart conteneur prod (application des secrets prod)..."
docker restart "$PROD_CONTAINER" >> "$LOG_FILE" 2>&1

log "Attente du démarrage Drupal prod..."
MAX_WAIT=90
WAITED=0
until docker exec "$PROD_CONTAINER" bash -c \
  "cd /var/www/html && vendor/bin/drush status --field=drupal-version --no-interaction 2>/dev/null | grep -qE '[0-9]+'" \
  2>/dev/null || [ "$WAITED" -ge "$MAX_WAIT" ]; do
  sleep 5
  WAITED=$((WAITED + 5))
  echo -n "." | tee -a "$LOG_FILE"
done
echo "" | tee -a "$LOG_FILE"

if [ "$WAITED" -ge "$MAX_WAIT" ]; then
  warn "Timeout ($MAX_WAIT s) — Drupal prod peut ne pas être complètement démarré."
else
  ok "Conteneur prod redémarré et Drupal bootstrappable (${WAITED}s)"
fi

# ── Étape 5 : Cache rebuild prod ─────────────────────────────────────────────
# Le restart de l'entrypoint exécute déjà drush cr. On force un second rebuild
# propre pour s'assurer que le cache reflète bien l'état post-import.

log "[5/6] Cache rebuild prod..."
drush_prod "cr" >> "$LOG_FILE" 2>&1
ok "Cache rebuild prod"

# ── Étape 6 : Smoke test prod ─────────────────────────────────────────────────

log "[6/6] Smoke test prod..."
echo "" | tee -a "$LOG_FILE"
docker exec "$PROD_CONTAINER" bash /var/www/html/.docker/smoke-test.sh \
  2>&1 | tee -a "$LOG_FILE"

# ── Rapport final ─────────────────────────────────────────────────────────────

echo "" | tee -a "$LOG_FILE"
log "======================================================="
log "  Sync terminé — $(ts)"
log ""
log "  Actions manuelles requises avant bascule DNS :"
log "  · Vérifier https://<domaine-prod> visuellement"
log "  · Effectuer un paiement test avec carte Stripe live"
log "  · Vérifier réception email (commande test)"
log "  · Confirmer smoke test 7/7 dans les logs"
log ""
log "  Log complet : $LOG_FILE"
log "======================================================="
