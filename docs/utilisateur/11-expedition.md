# 11 — Expédition et transporteurs

> Guide gestionnaire boutique · Atelier O Design

---

## Les 4 transporteurs disponibles

Au moment du checkout, la cliente choisit parmi les options suivantes :

| Transporteur | Tarif | Réseau |
|---|---|---|
| **Colissimo** — Livraison à domicile | 5,90 € | La Poste, 3–5 jours ouvrés |
| **DPD** — Livraison à domicile | 5,90 € | DPD, 2–4 jours ouvrés |
| **Chronopost** — Livraison express | 7,90 € | Chronopost, J+1 avant 13h |
| **Mondial Relay** — Point relais | 3,90 € | Réseau Mondial Relay |
| **Mondial Relay gratuit** *(≥ 50€)* | 0,00 € | Réseau Mondial Relay |

> **Note :** Le Mondial Relay gratuit n'apparaît dans la liste du checkout que si le montant du panier est supérieur ou égal à 50 €. C'est la méthode qui remplace la promotion automatique — le client la sélectionne lui-même.

---

## Voir quel transporteur a été sélectionné

1. Allez dans **Commandes** via la navigation boutique
2. Cliquez sur une commande pour ouvrir son détail
3. Dans le bloc **Transporteur & suivi** (colonne de gauche), le transporteur sélectionné par le client s'affiche en gras

![TODO screenshot — bloc transporteur & suivi sur la page de détail commande]()

---

## Renseigner le numéro de suivi

1. Ouvrez la commande concernée
2. Dans le bloc **Transporteur & suivi**, saisissez le numéro dans le champ prévu
3. Cliquez sur **Enregistrer**

Le numéro est immédiatement enregistré. Si le transporteur est reconnu (Colissimo, DPD, Chronopost ou Mondial Relay), un **lien cliquable vers le site de suivi** apparaît automatiquement.

![TODO screenshot — champ saisie numéro de suivi + lien généré]()

### Liens de suivi générés automatiquement

| Transporteur | URL de suivi |
|---|---|
| Colissimo | `https://www.laposte.fr/outils/suivre-vos-envois?code={numéro}` |
| DPD | `https://www.dpd.fr/trace/{numéro}` |
| Chronopost | `https://www.chronopost.fr/tracking-no-cms/suivi-page?listeNumerosLt={numéro}` |
| Mondial Relay | `https://www.mondialrelay.fr/suivi-de-colis/?numColis={numéro}` |

> Le lien s'ouvre dans un nouvel onglet. Il suffit de cliquer sur le numéro de suivi affiché dans la fiche commande.

---

## Livraison Mondial Relay gratuite dès 50 €

- La méthode **"Mondial Relay gratuit (commande ≥ 50€)"** est proposée automatiquement au checkout uniquement si le panier atteint 50 € ou plus.
- Le client qui la sélectionne ne paie aucun frais de port.
- Les autres transporteurs (Colissimo, DPD, Chronopost) conservent leur tarif même si le panier dépasse 50 €.
- Il n'y a pas de promotion "automatique" qui s'appliquerait à tous les transporteurs — la gratuité est liée au **choix explicite de Mondial Relay**.

---

## En cas de problème

Si les méthodes d'expédition n'apparaissent pas au checkout, contactez votre prestataire technique pour relancer le script de configuration :

```bash
ddev drush php-script scripts/seed-shipping-methods.php
```

Ce script est idempotent : il peut être relancé sans créer de doublons.
