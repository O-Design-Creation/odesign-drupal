# Journal des nouveautés — O'Design Création

Ce journal retrace les améliorations et corrections apportées à votre espace de gestion.
Il est rédigé en langage simple, sans jargon technique.

---

## v1.4rc1 — 1er juin 2026

### Technique — stockage interne (correctif)

- **Espace de stockage privé** : le rapport système Drupal signalait "Pas entièrement protégé" pour le dossier de fichiers privés. Le fichier de protection `.htaccess` est maintenant généré par Drupal lui-même au démarrage du serveur (marqueur SA-2013-003 exact), ce qui fait disparaître cet avertissement dans le rapport système dès le premier lancement.

---

## v1.2rc1 — 1er juin 2026

### Sécurité

- **Mise à jour de sécurité** : 8 alertes de sécurité corrigées sur 4 composants (Drupal core, Symfony, Twig, Guzzle). Drupal passe en version **11.3.11**, Twig en **3.27.1**, Symfony en **7.4.13**. Les correctifs couvrent des risques de contournement du moteur de templates (sandbox Twig), une faille SSRF sur les réseaux IPv6, et un problème d'encodage d'URL.
- Aucun changement visible pour la gestion de la boutique ou les commandes.

---

## v1.1rc1 — 22 mai 2026

### Sécurité

- **Mise à jour de sécurité Drupal** : le cœur de la boutique passe en version **11.3.10** avec les dépendances Symfony et Twig associées, pour corriger les alertes de sécurité remontées par l'audit Composer.
- **Surveillance sécurité préparée** : un workflow GitHub dédié à l'audit automatique des dépendances Composer est ajouté pour faciliter la détection rapide des prochaines alertes.

---

## v1.0rc40 — 29 avril 2026

### Personnalisation produits

- **Étiquettes bois cadeaux d'invités** : le formulaire de personnalisation indique désormais clairement "Quantité souhaitée" avec un exemple de saisie ("Ex : 10"). Le champ accepte maintenant du texte libre comme sur le site Shopify original.
- **Marques bacs enfant en bois** : le champ "Catégories" affiche un exemple de saisie ("Ex : voitures, poupées, livres") pour guider la commande.

---

## v1.0rc39 — 29 avril 2026

### Technique — stockage interne

- **Espace de stockage privé** : le dossier réservé aux factures PDF et aux fichiers internes est maintenant correctement accessible par le serveur. Les messages d'avertissement sur l'espace de stockage privé dans le rapport système disparaissent au prochain redémarrage.

---

## v1.0rc38 — 29 avril 2026

### Panier — correctif miniatures produits (2e passe)
- **Photos des articles** : les images s'affichent maintenant correctement dans le panier sur tous les environnements. Le correctif précédent corrigeait la bibliothèque de médias, mais la structure de la vue Commerce n'était pas correctement prise en compte — c'est résolu.

---

## v1.0rc37 — 29 avril 2026

### Panier — correctif miniatures produits
- **Photos des articles** : les images des produits s'affichent maintenant correctement dans le panier. Un problème technique empêchait leur chargement depuis la bibliothèque de médias — c'est corrigé.

---

## v1.0rc36 — 29 avril 2026

### Paiement
- **Mode de paiement réel activé** : la boutique accepte désormais de vrais paiements par carte bancaire. La phase de test Stripe est terminée — les achats effectués sur le site sont maintenant réels et débités.

---

## v1.0rc35 — 29 avril 2026

### Robustesse — ajout au panier (produits personnalisables)
- **Personnalisations distinctes conservées** : si vous ajoutez deux fois le même produit personnalisé (ex. deux plaques de chaise avec des prénoms différents), chaque article conserve désormais sa propre personnalisation dans le panier. Avant ce correctif, le second ajout écrasait le premier.
- **Champ "liste de choix" obligatoire** : quand un champ de type liste déroulante est marqué obligatoire, la soumission sans sélection est maintenant correctement bloquée (validation Drupal active).

---

## v1.0rc34 — 27 avril 2026

### Panier — affichage des miniatures produits
- **Photo de chaque article** : la page panier affiche désormais la photo du produit à côté de chaque ligne, pour identifier facilement les articles avant de valider.
- **Mise en page** : les articles s'affichent en cartes lisibles (photo + nom + prix unitaire + quantité + total + suppression), adapté mobile et tablette.

---

## v1.0rc33 — 27 avril 2026

### Personnalisation produits — correctif panier
- **Cases à cocher (ex. Pancartes jeu des mariés)** : l'ajout au panier ne provoque plus d'erreur fatale sur les produits dont la personnalisation utilise des cases à cocher. Le problème venait d'une contrainte de longueur de texte appliquée par erreur sur ce type de champ — c'est corrigé.

---

## v1.0rc32 — 27 avril 2026

### Page campagne — affichage mobile
- **Grille produits** : la page campagne (ex. Fête des mères) affiche désormais **2 produits par ligne** sur mobile, comme les pages univers (Mariage, Naissance & bébé…).
- **Bouton "Voir le produit"** : sur mobile en grille 2 colonnes, le bouton affiche une icône œil à la place du texte pour éviter tout débordement.

---

## v1.0rc31 — 21 avril 2026

### Sécurité
- **Mise à jour de sécurité** : le cœur de la boutique est mis à jour vers la version 11.3.8 — correctif de sécurité officiel appliqué.

---

## v1.0rc30 — 21 avril 2026

### Fiches produits
- **Tarif de livraison** : le tarif affiché sur les fiches produits est corrigé — il indique désormais **3,99 €** (au lieu de 4,90 €).

---

## v1.0rc29 — 21 avril 2026

### Correctifs techniques
- **Factures PDF** : l'espace de stockage des factures est maintenant correctement configuré — le message d'erreur "Commerce Invoice requires the private file system path" ne s'affiche plus.
- **Robots.txt** : le message d'avertissement sur le fichier robots.txt est résolu — c'est désormais le module qui gère ce fichier (configuration pré-lancement : indexation limitée).

---

## v1.0rc28 — 20 avril 2026

### Facturation
- **Factures PDF** : l'espace de stockage des factures est maintenant correctement configuré — le message d'erreur "Commerce Invoice requires the private file system path" ne s'affiche plus.

---

## v1.0rc27 — 20 avril 2026

### Infrastructure
- **HTTPS correct en back-office** : les URLs générées dans l'interface de gestion (notamment l'URL webhook Stripe) affichent désormais `https://` au lieu de `http://`, conformément au certificat SSL en place.

---

## v1.0rc26 — 20 avril 2026

### Infrastructure
- **Configuration sécurisée** : les clés Stripe et les identifiants email (Brevo) sont désormais protégés et survivent aux mises à jour du serveur sans intervention manuelle.

---

## v1.0rc25 — 20 avril 2026

### Personnalisation produits — back-office
- **Formulaire modèles de personnalisation corrigé** : la sauvegarde d'un modèle de personnalisation échouait systématiquement avec des messages d'erreur ("Nom du modèle est requis", "valeur non autorisée"). Ce blocage est résolu — les modèles se créent, s'éditent et se sauvegardent normalement.

---

## v1.0rc24 — 20 avril 2026

### Lisibilité
- **Newsletter** : le lien vers la politique de confidentialité est corrigé sur le balisage réellement servi en préproduction.

---

## v1.0rc23 — 20 avril 2026

### Lisibilité
- **Newsletter** : le lien vers la politique de confidentialité dans le formulaire d'inscription est plus contrasté.

---

## v1.0rc22 — 20 avril 2026

### Page campagne Fête des mères
- **Affichage mobile** : le bouton de la campagne est mieux contraint pour éviter tout débordement horizontal sur téléphone.
- **Bandeau cookies** : le lien vers la politique de confidentialité est plus lisible et respecte le contraste attendu.

---

## v1.0rc21 — 20 avril 2026

### Page campagne Fête des mères
- **Accessibilité** : le titre principal reste disponible pour les lecteurs d'écran tout en évitant un doublon visuel sur la page.
- **Affichage mobile** : le bouton "Découvrir la collection" peut maintenant revenir à la ligne si le texte est trop long sur téléphone.
- **Liens de campagne** : les boutons de campagne utilisent désormais une adresse lisible au lieu d'une adresse technique `internal:/...`.

### Panier
- **Produits personnalisés** : l'ajout au panier fonctionne à nouveau après saisie des champs de personnalisation sur le cadre jardin fleuri.

### Lisibilité
- **Contraste** : les textes du footer et du bandeau cookies sont renforcés pour rester lisibles sur le fond terracotta.

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
