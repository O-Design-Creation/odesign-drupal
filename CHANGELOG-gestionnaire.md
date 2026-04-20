# Journal des nouveautés — O'Design Création

Ce journal retrace les améliorations et corrections apportées à votre espace de gestion.
Il est rédigé en langage simple, sans jargon technique.

---

## v1.0rc20 — 20 avril 2026

### Page campagne Fête des mères (correctifs CSS)
- **Chargement CSS** : les styles de la page campagne sont désormais correctement chargés (librairie non attachée au template full).
- **Grille de produits** : les produits mis en avant s'affichent maintenant en grille (3 colonnes desktop, 2 sur tablette, 1 sur mobile).

---

## v1.0rc19 — 20 avril 2026

### Page campagne Fête des mères
- **Widget campagne sur l'accueil** : la campagne Fête des mères est désormais visible sur la page d'accueil (desktop et mobile).
- **Bouton "Découvrir la collection"** : le lien du bouton est corrigé — il pointait vers une page d'administration au lieu de la page campagne.
- **Contraste du bouton** : la couleur du bouton est ajustée pour satisfaire les critères WCAG 2.1 AA (ratio ≥ 4.5:1).
- **Affichage mobile** : le bouton ne déborde plus de l'écran sur téléphone.

---

## v1.0rc18 — 15 avril 2026

### Documentation gestionnaire
- **Modèles de personnalisation** : le guide de personnalisation précise maintenant la différence entre les modèles réutilisables et les modèles dédiés à un produit.
- **Prévention des erreurs** : le guide rappelle qu'un modèle dédié, par exemple pour l'Arbre de vie ou le Calendrier gravé, ne doit pas être associé à d'autres produits.

## v1.0rc17 — 15 avril 2026

### Personnalisation produits
- **Textes d'aide et exemples de saisie** : plusieurs fiches produits retrouvent les textes d'aide et les exemples visibles sur l'ancien site.
- **Noms des options** : les libellés des champs de personnalisation sont réalignés avec Shopify pour les produits concernés.
- **Formulaires dédiés par produit** : certains produits utilisent désormais leur propre modèle de personnalisation pour éviter qu'un exemple ou un texte d'aide soit partagé avec une fiche qui n'en a pas besoin.
- **Calendrier gravé** : le champ principal est renommé **Prénom(s)**.

## v1.0rc16 — 14 avril 2026

### Personnalisation produits
- **Numéro de table** : le formulaire affiche maintenant un champ clair pour le **nom ou numéro de la table** avec son exemple de saisie.
- **Motif de feuille** : le choix **Oui / Non** est de nouveau visible et obligatoire sur la fiche produit.

## v1.0rc15 — 14 avril 2026

### Personnalisation produits
- **Cadre photo en bois personnalisé** : les exemples de saisie reviennent sur les champs **Texte 1** et **Texte 2**.
- **Motifs de fleurs** : le choix **Oui / Non** est de nouveau affiché clairement et demandé sur la fiche produit.

## v1.0rc14 — 14 avril 2026

### Personnalisation produits
- **Plaque de chaise mariage** : le formulaire affiche maintenant les bons champs **Texte 1**, **Texte 2** et **Symbole** avec les choix **Coeur / Feuille**.
- **Description produit corrigée** : la fiche ne contient plus le texte mal encodé observé en recette.

## v1.0rc13 — 14 avril 2026

### Préparation recette
- **Déploiement préprod plus fiable** : les correctifs techniques et données de déploiement sont désormais rejoués automatiquement au redémarrage après une mise à jour.

## v1.0rc12 — 14 avril 2026

### Personnalisation produits
- **Carte de naissance ovale** : le champ de personnalisation **Prénom** est rétabli sur la fiche produit.
- **Correction plus fiable** : le correctif est rejouable sans retirer à nouveau cette personnalisation lors d'une maintenance ultérieure.

## v1.0rc11 — 14 avril 2026

### Catalogue
- **Catalogue complet plus fiable** : la pagination de la page `/produits` est stabilisée pour éviter que certaines créations apparaissent en double d'une page à l'autre.
- **Produits mieux répartis** : les créations retrouvent une place cohérente dans les différentes pages du catalogue complet.

### Préparation recette
- **Correction prête pour la preprod** : ce correctif est préparé pour être rejoué sur l'environnement de préproduction avant validation finale.

## v1.0rc10 — 13 avril 2026

### Recherche & catalogue
- **Recherche plus pratique** : des suggestions reviennent dans la barre de recherche pendant la saisie, pour éviter de devoir lancer la recherche à l'aveugle.
- **Page résultats plus claire** : la page `/recherche` affiche un résumé du terme recherché et le nombre de résultats trouvés.
- **Filtres plus lisibles** : les colonnes de filtres vides n'encombrent plus la page quand aucun filtre utile n'est disponible.

### Personnalisation produits
- **Puzzle prénom** : la personnalisation dédiée au puzzle est rétablie avec le prénom et le choix du motif.
- **Support cartes de jeux** : le formulaire de personnalisation est réaffecté sur la fiche produit.
- **Supports bracelet de naissance** : les versions rondes et nuage retrouvent un formulaire complet (prénom, date, heure, poids, taille).
- **Arbre de vie mieux ciblé** : l'interface spéciale "Arbre de vie" n'est plus censée s'afficher sur d'autres produits qui n'en ont pas besoin.

### Blog
- **Images d'articles plus nettes** : les cartes du blog utilisent un format d'image plus large pour limiter l'effet de flou sur ordinateur.

## v1.0rc7 — 10 avril 2026

### Sécurité d'accès
- **Accès limité avant ouverture publique** : les environnements **preprod** et **prod (prévisualisation)** sont désormais accessibles uniquement depuis les adresses IP autorisées par l'équipe.
- **Protection renforcée** : toute tentative d'accès depuis une autre adresse est automatiquement bloquée.

## v1.0rc6 — 10 avril 2026

### Corrections
- **Images produits** : les images des produits s'affichent à nouveau correctement après un redémarrage du serveur. Elles étaient perdues à chaque mise à jour.
- **Images dérivées** : les vignettes et formats recadrés (utilisés sur les pages produit) se génèrent à nouveau sans erreur.

### Infrastructure
- Les fichiers uploadés (photos produits, images blog) sont maintenant conservés de manière permanente, même lors d'une mise à jour de la boutique.

---

## v1.0rc5 — 9 avril 2026

### Améliorations
- **Espace de travail** : un bandeau coloré indique désormais en haut de chaque page si vous êtes sur l'environnement de **test** (orange) ou de **production** (vert). Cela évite toute confusion entre les deux espaces.
- **Version visible** : le numéro de version déployée est affiché dans le bandeau, pour faciliter les échanges avec l'équipe technique en cas de question.

---

## v1.0rc4 — 8 avril 2026

### Corrections
- **Accès externe** : la boutique en ligne (www.prod.atelierodesign.fr) est désormais accessible depuis l'extérieur sans erreur 503.
- **Réinstallation intempestive** : la boutique ne se réinitialisait plus toute seule à chaque redémarrage du serveur.

---

## v1.0rc1 — 7 avril 2026

### Mise en production initiale
- Première version de la boutique Drupal Commerce déployée sur le serveur de production.
- Catalogue produits (arbres de vie, cartes naissance, personnalisation) importé.
- Paiement Stripe configuré en mode test.
- Envoi d'emails transactionnels via Brevo opérationnel.
