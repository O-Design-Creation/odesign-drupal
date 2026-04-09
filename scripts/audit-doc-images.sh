#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

echo "Audit docs: vérification des captures référencées..."
./scripts/verify-doc-screenshots.sh

echo "Audit docs: vérification du chargement des images sur toutes les pages documentation..."
ULI="$(ddev drush uli --uid=2)"
node playwright/scripts/check-doc-images-all.js "$ULI"

echo "OK: audit documentation terminé."
