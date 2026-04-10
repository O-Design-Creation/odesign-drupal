#!/bin/bash
# ──────────────────────────────────────────────────────────────────────────────
# docker-cleanup.sh — Nettoyage hebdomadaire des artefacts Docker (TCK-134)
#
# Déploiement : /etc/cron.weekly/docker-cleanup (exécutable, root)
# Cible       : VPS OVH ubuntu@51.38.177.195
# Fréquence   : hebdomadaire (cron.weekly)
#
# Précautions :
#   - Ne prune JAMAIS les volumes actifs (attachés à un container running)
#   - Les volumes nommés odesign-*-files sont toujours attachés → protégés
#   - Seuil alerte disque : 70 % → mail à contact@atelierodesign.fr
# ──────────────────────────────────────────────────────────────────────────────

set -euo pipefail

LOG="/var/log/docker-cleanup.log"
ALERT_THRESHOLD=70
ALERT_EMAIL="contact@atelierodesign.fr"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*" | tee -a "$LOG"; }

log "=== Début nettoyage Docker ==="

# ── 1. Images obsolètes (> 7 jours, non utilisées) ───────────────────────────
log "Prune images > 7 jours..."
docker image prune -f --filter "until=168h" >> "$LOG" 2>&1

# ── 2. Containers stoppés ─────────────────────────────────────────────────────
log "Prune containers stoppés..."
docker container prune -f >> "$LOG" 2>&1

# ── 3. Volumes orphelins (non attachés) ───────────────────────────────────────
# Les volumes odesign-*-files sont TOUJOURS attachés → ne seront pas prunés
log "Prune volumes orphelins..."
docker volume prune -f >> "$LOG" 2>&1

# ── 4. Cache builder > 7 jours ───────────────────────────────────────────────
log "Prune build cache > 7 jours..."
docker builder prune -f --filter "until=168h" >> "$LOG" 2>&1

# ── 5. Rapport post-nettoyage ─────────────────────────────────────────────────
log "État Docker après nettoyage :"
docker system df 2>&1 | tee -a "$LOG"

# ── 6. Alerte disque ──────────────────────────────────────────────────────────
USAGE=$(df / | awk 'NR==2 {print $5}' | tr -d '%')
log "Utilisation disque : ${USAGE}%"
if [ "$USAGE" -gt "$ALERT_THRESHOLD" ]; then
  MSG="ALERTE : disque VPS à ${USAGE}% (seuil : ${ALERT_THRESHOLD}%). Action manuelle requise."
  log "$MSG"
  if command -v mail &>/dev/null; then
    echo "$MSG" | mail -s "[odesign] Espace disque critique — ${USAGE}%" "$ALERT_EMAIL"
  else
    log "mail non disponible — alerte non envoyée par email"
  fi
fi

log "=== Fin nettoyage Docker ==="
