# 13 — Campagnes saisonnières

Les campagnes saisonnières vous permettent de créer des pages promotionnelles dédiées à des occasions récurrentes (Noël, Saint-Valentin, fête des mères, etc.) et de les programmer à l'avance.

---

## Créer une campagne

1. Rendez-vous dans **Contenu › Campagnes** dans la barre de navigation.
2. Cliquez sur **Ajouter un contenu › Campagne**.
3. Remplissez les champs :

| Champ | Description |
|---|---|
| **Titre** | Nom de la campagne (ex : *Noël 2026*, *Fête des mères 2026*) |
| **Description / texte principal** | Texte présenté sur la page campagne |
| **Image hero** | Image principale (format recommandé : 1200 × 630 px minimum, JPG ou WebP) |
| **Produits mis en avant** | Sélectionnez un ou plusieurs produits à mettre en valeur |
| **Texte du bouton CTA** | Ex : *Découvrir la collection*, *Commander maintenant* |
| **URL du bouton CTA** | Lien vers lequel redirige le bouton (peut être interne ou externe) |
| **Couleur d'accent** | Code hexadécimal (ex : `#BB7570`) — personnalise le titre et le CTA |

![TODO screenshot — formulaire de création d'une campagne]

---

## Programmer la publication et la dépublication

Le module **Scheduler** vous permet de définir des dates automatiques :

1. Dans le formulaire de la campagne, repérez la section **Programmation**.
2. Renseignez la **date de publication** : la campagne sera mise en ligne automatiquement à cette date/heure.
3. Renseignez la **date de dépublication** : la campagne sera retirée automatiquement (sans intervention de votre part).

> **Exemple** : Pour une campagne Noël 2026, publiez le 1er novembre 2026 à 09h00 et dépubliez le 2 janvier 2027 à 00h00.

![TODO screenshot — section Scheduler avec dates de publication]

---

## Afficher la campagne sur la homepage

Pour qu'une campagne apparaisse dans le widget **"Campagne en cours"** sur la page d'accueil :

1. Dans le formulaire, cochez la case **Mettre en avant** (champ *Promote to front page*).
2. Assurez-vous que la campagne est publiée (ou programmée).

> Le widget affiche uniquement la campagne mise en avant la plus récente. Si plusieurs campagnes sont cochées "Mettre en avant", seule la plus récente est affichée.

![TODO screenshot — case "Mettre en avant" dans le formulaire]

---

## Le listing /campagnes

La page `/campagnes` est générée automatiquement. Elle affiche toutes les campagnes publiées, triées de la plus récente à la plus ancienne, avec pagination.

Vous n'avez rien à configurer : dès qu'une campagne est publiée, elle apparaît dans le listing.

![TODO screenshot — listing /campagnes avec cartes campagne]

---

## URLs propres

Les URLs des campagnes sont générées automatiquement à partir du titre :

- Titre : *Noël 2026* → URL : `/campagnes/noel-2026`
- Titre : *Fête des mères 2026* → URL : `/campagnes/fete-des-meres-2026`

Vous pouvez personnaliser l'URL dans le champ **Alias d'URL** si nécessaire.

---

## Bonnes pratiques

- Préparez vos campagnes à l'avance et utilisez le **Scheduler** pour éviter toute intervention manuelle le jour J.
- Utilisez une image hero de qualité (1200 × 630 px minimum) pour un rendu optimal.
- Ne laissez pas plusieurs campagnes actives simultanément avec "Mettre en avant" coché : seule la plus récente sera affichée en homepage.
- Pensez à dépublier les campagnes passées pour ne pas encombrer le listing.
