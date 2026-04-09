# 11 — Expédition et transporteurs

> Guide gestionnaire boutique · Atelier O Design

---

## État des fonctionnalités par transporteur

| Transporteur | Sélection au checkout | Widget point relais | Génération d'étiquette |
|---|---|---|---|
| **Colissimo** | ✅ Disponible | — | ⏳ Phase future (API Okapi) |
| **DPD** | ✅ Disponible | — | ⏳ Phase future |
| **Chronopost** | ✅ Disponible | — | ⏳ Phase future |
| **Mondial Relay** | ✅ Disponible | ✅ Widget actif (BDTEST → prod) | ⏳ Phase future |

> **Phases :** La sélection du transporteur et la saisie du numéro de suivi sont disponibles dès maintenant. La génération automatique d'étiquettes d'expédition nécessite une intégration API par transporteur — prévu en V2.

---

## Les transporteurs disponibles au checkout

| Transporteur | Tarif | Réseau |
|---|---|---|
| **Colissimo** — Livraison à domicile | 5,90 € | La Poste, 3–5 jours ouvrés |
| **DPD** — Livraison à domicile | 5,90 € | DPD, 2–4 jours ouvrés |
| **Chronopost** — Livraison express | 7,90 € | Chronopost, J+1 avant 13h |
| **Mondial Relay** — Point relais | 3,90 € | Réseau Mondial Relay |
| **Mondial Relay gratuit** *(≥ 50€)* | 0,00 € | Réseau Mondial Relay |

> **Note :** Le Mondial Relay gratuit n'apparaît dans la liste du checkout que si le montant du panier est supérieur ou égal à 50 €.

---

## Voir quel transporteur a été sélectionné

1. Allez dans **Commandes** via la navigation boutique
2. Cliquez sur une commande pour ouvrir son détail
3. Dans le bloc **Transporteur & suivi** (colonne de gauche), le transporteur sélectionné par le client s'affiche en gras

> **Mondial Relay :** l'adresse du point relais choisi par le client est visible dans le bloc **Adresse de livraison** de la même fiche commande.

![Bloc Transporteur & suivi sur la fiche commande](screenshots/transporteurs-detail-commande.png)

---

## Renseigner le numéro de suivi

1. Ouvrez la commande concernée
2. Dans le bloc **Transporteur & suivi**, saisissez le numéro dans le champ prévu
3. Cliquez sur **Enregistrer**

Le numéro est immédiatement enregistré. Si le transporteur est reconnu (Colissimo, DPD, Chronopost ou Mondial Relay), un **lien cliquable vers le site de suivi** apparaît automatiquement.

![Saisie du numéro de suivi et lien généré automatiquement](screenshots/transporteurs-saisie-suivi.png)

### Liens de suivi générés automatiquement

| Transporteur | URL de suivi |
|---|---|
| Colissimo | `https://www.laposte.fr/outils/suivre-vos-envois?code={numéro}` |
| DPD | `https://www.dpd.fr/trace/{numéro}` |
| Chronopost | `https://www.chronopost.fr/tracking-no-cms/suivi-page?listeNumerosLt={numéro}` |
| Mondial Relay | `https://www.mondialrelay.fr/suivi-de-colis/?numColis={numéro}` |

> Le lien s'ouvre dans un nouvel onglet. Il suffit de cliquer sur le numéro de suivi affiché dans la fiche commande.

---

## Configurer le widget point relais Mondial Relay

> **Backoffice** : Configuration → Transporteurs & tarifs → "Mondial Relay — Point relais" → Modifier

Le widget de sélection de point relais est actif avec un **Brand ID de test** (`BDTEST`). Pour la mise en production, il faut le remplacer par votre Brand ID réel.

### Obtenir votre Brand ID de production

1. Connectez-vous à votre espace pro Mondial Relay : [connect.mondialrelay.com](https://connect.mondialrelay.com/)
2. Accédez à vos paramètres API (section **Api settings** ou équivalent selon votre type de compte)
3. Générez ou récupérez vos identifiants API
4. Notez votre **Brand ID** (code alphanumérique fourni par Mondial Relay, ex : `12345`)

### Saisir le Brand ID dans le backoffice

1. Dans la navigation boutique, ouvrez **Configuration** → **Transporteurs & tarifs**
2. Cliquez sur **Modifier** à côté de "Mondial Relay — Point relais"
3. Dans le champ **Marque**, remplacez `BDTEST` par votre Brand ID réel
4. Répétez pour "Mondial Relay gratuit (commande ≥ 50€)"
5. Cliquez sur **Enregistrer**

![Formulaire Mondial Relay — champ Marque avec BDTEST](screenshots/transporteurs-mondial-relay-brand.png)

> ⚠️ Tant que le champ **Marque** contient `BDTEST`, le widget est en mode sandbox : les points relais affichés sont fictifs. Ne pas laisser `BDTEST` en production.

---

## Configurer les tarifs d'un transporteur

> **Backoffice** : Configuration → Transporteurs & tarifs

Tous les transporteurs sont modifiables depuis le backoffice sans intervention technique.

![Liste des modes d'expédition](screenshots/transporteurs-liste.png)

1. Cliquez sur **Modifier** à côté du transporteur
2. Modifiez le montant dans le champ **Montant du tarif**
3. Cliquez sur **Enregistrer**

![Formulaire de modification — exemple Colissimo](screenshots/transporteurs-colissimo-edit.png)

---

## Livraison Mondial Relay gratuite dès 50 €

- La méthode **"Mondial Relay gratuit (commande ≥ 50€)"** est proposée automatiquement au checkout uniquement si le panier atteint 50 € ou plus.
- Le client qui la sélectionne ne paie aucun frais de port.
- Les autres transporteurs (Colissimo, DPD, Chronopost) conservent leur tarif même si le panier dépasse 50 €.
- Il n'y a pas de promotion "automatique" qui s'appliquerait à tous les transporteurs — la gratuité est liée au **choix explicite de Mondial Relay**.

---

## Imprimer les étiquettes d'expédition (workflow interim)

> La génération d'étiquettes depuis le backoffice est prévue en **V2** (intégration API par transporteur).
> En attendant, voici le workflow à suivre pour chaque expédition.

### Principe général

1. Préparer la commande (emballer, peser, mesurer)
2. Générer l'étiquette **directement sur le portail du transporteur**
3. **Saisir le numéro de suivi** obtenu dans la fiche commande Drupal (bloc Transporteur & suivi → champ → Enregistrer)
4. Imprimer et coller l'étiquette sur le colis

### Sans contrat pro — dépôt ponctuel en bureau

Si vous n'avez pas encore de contrat professionnel avec le transporteur, vous pouvez créer des envois au tarif public :

| Transporteur | Service | Lien |
|---|---|---|
| **Colissimo** | Colissimo.fr — envoi en ligne | [colissimo.fr](https://www.laposte.fr/colissimo) |
| **DPD** | DPD Predict — particulier | [dpd.fr](https://www.dpd.fr/particulier) |
| **Chronopost** | Chronopost.fr | [chronopost.fr](https://www.chronopost.fr) |
| **Mondial Relay** | Mondial Relay — envoi en ligne | [mondialrelay.fr](https://www.mondialrelay.fr/envoi-de-colis/) |

> ⚠️ Le tarif public est plus élevé que les tarifs négociés. Cette solution convient en phase de lancement ou pour un volume faible.

### Avec un contrat pro — portail transporteur

Si vous avez un contrat professionnel, chaque transporteur met à disposition un portail ou un logiciel d'impression d'étiquettes :

| Transporteur | Portail pro | Notes |
|---|---|---|
| **Colissimo** | [Colissimo Business / Affranchigo](https://www.laposte.fr/colissimo/professionnels) | Impression depuis le navigateur ou Coliship (logiciel) |
| **DPD** | [DPD Shipper / MyDPD Business](https://www.dpd.fr/professionnel) | Portail en ligne, pas d'installation requise |
| **Chronopost** | [Chronopost Business](https://www.chronopost.fr/professionnels) | Compte pro requis |
| **Mondial Relay** | [Mondial Relay Connect](https://connect.mondialrelay.com/) | Le même portail que pour le Brand ID |

### Workflow type (exemple Colissimo)

1. Récupérer dans la fiche commande Drupal : **nom client**, **adresse de livraison**, **poids estimé** du colis
2. Se connecter au portail Colissimo (public ou pro)
3. Saisir les informations de l'expédition → générer l'étiquette PDF
4. **Copier le numéro de suivi** (ex : `6X123456789FR`) dans la fiche commande Drupal → cliquer **Enregistrer**
5. Imprimer l'étiquette et la coller sur le colis

> Le numéro de suivi saisi dans Drupal génère automatiquement un **lien cliquable** vers le site de suivi du transporteur, consultable en backoffice.

---



Si les méthodes d'expédition n'apparaissent pas au checkout, contactez votre prestataire technique pour relancer le script de configuration :

```bash
ddev drush php-script scripts/seed-shipping-methods.php
```

Ce script est idempotent : il peut être relancé sans créer de doublons.

> **Note :** Cette commande s'exécute en environnement de développement local (DDEV). Sur Pantheon, le prestataire utilisera l'équivalent via Terminus.
