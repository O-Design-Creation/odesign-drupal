# Gestion des commandes

> 📍 **Accès** : Tableau de bord → **Commandes**
> **URL directe** : `/admin/commerce/orders`

## Ce que vous pouvez faire ici

Vous pouvez consulter toutes les commandes reçues, suivre leur avancement dans le processus de fabrication, changer leur statut, ajouter des notes internes et accéder aux factures. C'est votre espace de travail quotidien pour traiter les commandes.

---

## Voir la liste des commandes

### Afficher toutes les commandes

1. Aller sur `/admin/commerce/orders`
2. La liste affiche toutes les commandes avec leur numéro, le nom du client, la date, le montant et le statut

### Filtrer les commandes

1. Sur la page `/admin/commerce/orders`, utiliser les filtres en haut de la liste :
   - **Statut** : pour voir uniquement les commandes "En fabrication", "Expédiées", etc.
   - **Date** : pour retrouver les commandes d'une période précise
   - **Client** : pour chercher les commandes d'un client par son nom ou son email
2. Cliquer sur **Appliquer** pour afficher les résultats filtrés

> 💡 **Astuce** : Le raccourci **Commandes à traiter** dans le tableau de bord affiche directement les commandes qui nécessitent votre attention (nouvelles commandes payées, non encore traitées).

---

## Consulter le détail d'une commande

1. Sur la liste des commandes, cliquer sur le numéro de commande ou sur **Voir**
2. La fiche de commande affiche :
   - Les articles commandés (nom, quantité, prix)
   - L'adresse de livraison et le mode de livraison choisi
   - Le montant payé et le mode de paiement
   - L'historique des changements de statut

---

## Changer le statut d'une commande

Les commandes suivent un parcours depuis la réception jusqu'à la livraison. Voici les statuts disponibles et leur signification :

| Statut | Signification |
|--------|--------------|
| **Brouillon** | Commande non finalisée par le client |
| **En attente de paiement** | Commande passée, paiement non encore confirmé |
| **En fabrication (atelier laser)** | Vous avez commencé à fabriquer la commande |
| **Contrôle qualité** | La pièce est fabriquée, vous vérifiez la qualité |
| **Emballée / prête à expédier** | La commande est prête, en attente d'envoi |
| **Expédiée** | Le colis est parti |
| **Livrée** | Le client a reçu sa commande |
| **Annulée** | La commande a été annulée |
| **Remboursée** | Un remboursement a été effectué |

### Passer une commande à l'étape suivante

1. Ouvrir la fiche de commande (cliquer sur le numéro de commande)
2. Dans la section **Statut**, cliquer sur le bouton correspondant à la prochaine étape (ex. : **En fabrication**, **Contrôle qualité**, **Expédiée**…)
3. Confirmer si un message de confirmation apparaît

> ⚠️ **Attention** : Le passage au statut **Annulée** ou **Remboursée** est difficile à annuler. Assurez-vous d'être certaine avant de changer vers ces statuts.

> 💡 **Astuce** : Mettez à jour le statut dès que vous commencez à travailler sur une commande. Cela permet de garder une vue d'ensemble claire et d'envoyer les bons emails automatiques à vos clients.

---

## Ajouter un commentaire sur une commande

Vous pouvez laisser une note interne sur une commande (visible uniquement par vous dans l'administration, pas par le client).

1. Ouvrir la fiche de commande
2. Descendre jusqu'à la section **Commentaires administrateur**
3. Saisir votre note dans le champ de texte (ex. : "Prénom à graver : Emma — police italique")
4. Cliquer sur **Ajouter un commentaire**

> 💡 **Astuce** : Utilisez les commentaires pour noter les personnalisations spécifiques, les échanges avec le client ou les informations de suivi colis.

---

## Voir la facture d'une commande

1. Ouvrir la fiche de commande
2. Cliquer sur l'onglet **Factures** ou sur le lien **Voir la facture**
3. La facture s'affiche ou se télécharge au format PDF

> 💡 **Astuce** : Les factures sont générées automatiquement à la confirmation du paiement. Vous pouvez les télécharger pour vos archives ou les transmettre à votre comptable.

---

## Débloquer une commande verrouillée

Dans certains cas rares, une commande peut être verrouillée (par exemple suite à un problème technique pendant le paiement).

1. Ouvrir la fiche de commande
2. Si un message indique que la commande est verrouillée, cliquer sur **Déverrouiller**
3. La commande redevient modifiable

> ⚠️ **Attention** : Ne déverrouillez une commande que si vous êtes certaine qu'elle n'est plus en cours de traitement par le système de paiement.

---

*Documentation Atelier O Design — Commandes — v1.0*
