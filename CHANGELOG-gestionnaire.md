# Journal des nouveautés — O'Design Création

Ce journal retrace les améliorations et corrections apportées à votre espace de gestion.
Il est rédigé en langage simple, sans jargon technique.

---

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
