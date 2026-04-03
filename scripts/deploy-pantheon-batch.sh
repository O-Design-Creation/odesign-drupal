#!/usr/bin/env bash
set -euo pipefail

SITE="${1:-atelierodesign.dev}"
BRANCH="${2:-master}"

echo "==> Push code vers pantheon/${BRANCH}"
git push pantheon "HEAD:${BRANCH}"

echo "==> Attente fin de workflow Pantheon (${SITE})"
while true; do
  STATUS="$(terminus workflow:info:status "${SITE}" 2>/dev/null | awk '/Status/{print $2}')"
  echo "    status=${STATUS}"
  if [[ "${STATUS}" == "succeeded" ]]; then
    break
  fi
  if [[ "${STATUS}" == "failed" ]]; then
    echo "Workflow Pantheon en échec." >&2
    exit 1
  fi
  sleep 10
done

echo "==> Exécution update DB"
terminus drush "${SITE}" -- updb -y

echo "==> Purge cache"
terminus drush "${SITE}" -- cr

echo "==> OK: déploiement batch terminé"

