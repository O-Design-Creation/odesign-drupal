#!/usr/bin/env bash
set -euo pipefail

SITE="${1:-atelierodesign.dev}"
BRANCH="${2:-master}"
MODE="${DEPLOY_MODE:-auto}" # auto|full|fast
DRY_RUN="${DEPLOY_DRY_RUN:-0}"
NO_PUSH="${DEPLOY_NO_PUSH:-0}"
DOC_AUDIT="${DEPLOY_DOC_AUDIT:-auto}" # auto|1|0
REQUIRE_CLEAN="${DEPLOY_REQUIRE_CLEAN:-0}" # 1|0

usage() {
  cat <<'EOF'
Usage:
  deploy-pantheon-batch.sh [site] [branch]

Env options:
  DEPLOY_MODE=auto|full|fast   (default: auto)
  DEPLOY_DRY_RUN=1             Simule sans exécuter push/drush
  DEPLOY_NO_PUSH=1             N'exécute pas le push git
  DEPLOY_DOC_AUDIT=auto|1|0    Audit captures doc avant push (default: auto)
  DEPLOY_REQUIRE_CLEAN=1|0     Bloque le déploiement si git status n'est pas propre
EOF
}

if [[ "${SITE}" == "--help" || "${SITE}" == "-h" ]]; then
  usage
  exit 0
fi

run_cmd() {
  local cmd="$1"
  if [[ "${DRY_RUN}" == "1" ]]; then
    echo "[dry-run] ${cmd}"
    return 0
  fi
  eval "${cmd}"
}

echo "==> Préparation"
echo "    site=${SITE}"
echo "    branch=${BRANCH}"
echo "    mode=${MODE}"
echo "    dry_run=${DRY_RUN}"
echo "    no_push=${NO_PUSH}"
echo "    doc_audit=${DOC_AUDIT}"
echo "    require_clean=${REQUIRE_CLEAN}"

if [[ "${REQUIRE_CLEAN}" == "1" ]]; then
  if [[ -n "$(git status --porcelain)" ]]; then
    echo "Erreur: working tree non propre. Commit/stash requis (DEPLOY_REQUIRE_CLEAN=1)." >&2
    git status --short
    exit 1
  fi
  echo "==> Pré-check git: working tree propre"
fi

git fetch pantheon "${BRANCH}" >/dev/null 2>&1 || true

BASE_REF="pantheon/${BRANCH}"
CHANGED_FILES="$(git diff --name-only "${BASE_REF}...HEAD" || true)"

needs_updb=0
needs_cim=0

if [[ "${MODE}" == "full" ]]; then
  needs_updb=1
  needs_cim=1
elif [[ "${MODE}" == "fast" ]]; then
  needs_updb=0
  needs_cim=0
else
  # auto: détecte si DB/config doivent être rejouées.
  if grep -Eq '(^|/)\.install$|(^|/)composer\.(json|lock)$|^web/modules/contrib/' <<< "${CHANGED_FILES}"; then
    needs_updb=1
  fi
  if grep -Eq '^config/sync/' <<< "${CHANGED_FILES}"; then
    needs_cim=1
  fi
fi

echo "==> Analyse diff ${BASE_REF}...HEAD"
if [[ -n "${CHANGED_FILES}" ]]; then
  echo "${CHANGED_FILES}"
else
  echo "    (aucun changement détecté)"
fi
echo "    plan: updb=${needs_updb} cim=${needs_cim}"

needs_doc_audit=0
if [[ "${DOC_AUDIT}" == "1" ]]; then
  needs_doc_audit=1
elif [[ "${DOC_AUDIT}" == "auto" ]]; then
  if grep -Eq '^docs/utilisateur/|^playwright/scripts/(screenshot-docs-utilisateur|screenshot-reviews-docs|check-doc-page-images|check-doc-images-all)\.js$|^scripts/(verify-doc-screenshots|audit-doc-images)\.sh$|^web/modules/custom/odesign_admin/src/Controller/DocumentationController\.php$|^web/modules/custom/odesign_admin/odesign_admin\.routing\.yml$' <<< "${CHANGED_FILES}"; then
    needs_doc_audit=1
  fi
fi

if [[ "${needs_doc_audit}" == "1" ]]; then
  echo "==> Pré-check: audit captures documentation"
  if [[ "${DRY_RUN}" == "1" ]]; then
    echo "[dry-run] bash scripts/audit-doc-images.sh"
  else
    if ! command -v ddev >/dev/null 2>&1; then
      echo "Erreur: ddev est requis pour DEPLOY_DOC_AUDIT=${DOC_AUDIT}." >&2
      exit 1
    fi
    if ! command -v node >/dev/null 2>&1; then
      echo "Erreur: node est requis pour DEPLOY_DOC_AUDIT=${DOC_AUDIT}." >&2
      exit 1
    fi
    bash scripts/audit-doc-images.sh
  fi
else
  echo "==> Pré-check doc: ignoré"
fi

if [[ "${NO_PUSH}" != "1" ]]; then
  echo "==> Push code vers pantheon/${BRANCH}"
  run_cmd "git push pantheon \"HEAD:${BRANCH}\""

  echo "==> Attente fin de workflow Pantheon (${SITE})"
  if [[ "${DRY_RUN}" != "1" ]]; then
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
  else
    echo "[dry-run] skip wait workflow"
  fi
else
  echo "==> NO_PUSH=1: push et attente workflow ignorés"
fi

if [[ "${needs_updb}" == "1" ]]; then
  echo "==> Exécution update DB"
  run_cmd "terminus drush \"${SITE}\" -- updb -y"
else
  echo "==> Fast path: updb ignoré"
fi

if [[ "${needs_cim}" == "1" ]]; then
  echo "==> Import configuration"
  run_cmd "terminus drush \"${SITE}\" -- cim -y"
else
  echo "==> Fast path: cim ignoré"
fi

echo "==> Purge cache"
run_cmd "terminus drush \"${SITE}\" -- cr"

echo "==> OK: déploiement batch terminé"
