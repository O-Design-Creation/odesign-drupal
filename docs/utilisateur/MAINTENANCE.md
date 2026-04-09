# Maintenance technique — Documentation gestionnaire

Ce fichier est destiné au prestataire technique.
Il décrit la procédure de contrôle des captures utilisées dans la documentation backoffice.

## Pourquoi

Les chapitres `/admin/boutique/documentation/*` affichent les images référencées dans les fichiers Markdown de `docs/utilisateur`.
Une anomalie de capture (fichier manquant, image cassée, capture illisible) dégrade directement la doc gestionnaire.

## Pré-requis

- Projet démarré en local (`ddev start`)
- Dépendances Node disponibles
- Un utilisateur `uid=2` avec rôle `gestionnaire_boutique`

## Commande unique recommandée

```bash
npm run audit:docs-images
```

Cette commande enchaîne:

1. Vérification des fichiers référencés dans les Markdown  
   script: `scripts/verify-doc-screenshots.sh`
2. Vérification de chargement réel dans Drupal, chapitre par chapitre  
   script: `playwright/scripts/check-doc-images-all.js`

## Regénérer des captures (chapitre avis clients)

```bash
ULI=$(ddev drush uli --uid=2)
node playwright/scripts/screenshot-reviews-docs.js "$ULI"
```

Ensuite, valider immédiatement:

```bash
ULI=$(ddev drush uli --uid=2)
node playwright/scripts/check-doc-page-images.js "$ULI" "/admin/boutique/documentation/16-avis-clients"
```

## Règle qualité

Toute nouvelle capture doit être:

- prise avec une session `gestionnaire_boutique`
- visualisée manuellement avant validation
- contrôlée par script (`check-doc-page-images.js` ou `check-doc-images-all.js`)
