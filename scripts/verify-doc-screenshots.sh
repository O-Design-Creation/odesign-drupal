#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
DOC_DIR="$ROOT_DIR/docs/utilisateur"
SRC_DIR="$DOC_DIR/screenshots"

missing_src=0

echo "Vérification des captures référencées dans docs/utilisateur/*.md"

mapfile -t refs < <(sed -n 's/.*](screenshots\/\([^)]*\)).*/\1/p' "$DOC_DIR"/*.md | sort -u)

for ref in "${refs[@]}"; do
  if [[ ! -f "$SRC_DIR/$ref" ]]; then
    echo "MISSING source: docs/utilisateur/screenshots/$ref"
    missing_src=$((missing_src + 1))
  fi
done

echo "Références détectées: ${#refs[@]}"
echo "Manquants source: $missing_src"

if [[ "$missing_src" -gt 0 ]]; then
  exit 2
fi

echo "OK: toutes les captures référencées existent dans docs/utilisateur/screenshots."
