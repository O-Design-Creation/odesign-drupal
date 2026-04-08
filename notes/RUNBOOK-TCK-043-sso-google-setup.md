# RUNBOOK — TCK-043 — Configuration SSO Google OAuth

> Ce runbook est destiné à la **cliente** (ou au développeur qui déploie l'environnement).
> Il décrit les étapes nécessaires pour créer l'application OAuth Google Cloud et saisir
> les credentials dans Drupal afin d'activer la connexion sociale Google.

---

## Prérequis

- Accès à [Google Cloud Console](https://console.cloud.google.com/) avec un compte Google
- URL publique HTTPS du site Drupal (ex. : `https://atelierodesign.fr` en prod, ou `https://dev-atelierodesign.pantheonsite.io` en sandbox)
- Accès administrateur Drupal sur le site cible

> ⚠️ Le SSO Google **ne fonctionne pas** sur `http://` ni sur `localhost` sans configuration spécifique.
> Sur DDEV (`https://odesign-drupal.ddev.site`), le SSL auto-signé est accepté.

---

## Étape 1 — Créer un projet dans Google Cloud Console

1. Aller sur [https://console.cloud.google.com/](https://console.cloud.google.com/)
2. Cliquer sur le sélecteur de projet (en haut à gauche) → **"Nouveau projet"**
3. Nommer le projet : `Atelier O Design — Boutique` (ou similaire)
4. Cliquer sur **"Créer"**

---

## Étape 2 — Configurer l'écran de consentement OAuth

1. Dans le menu de gauche : **"API et Services"** → **"Écran d'autorisation OAuth"**
2. Choisir **"Externe"** (pour les comptes Google de vos clients)
3. Remplir les champs obligatoires :
   - **Nom de l'application** : `Atelier O Design`
   - **E-mail d'assistance utilisateur** : votre email Google
   - **Logo** : optionnel (recommandé — améliore la confiance)
   - **Domaines autorisés** : `atelierodesign.fr` (et `ddev.site` pour les tests)
   - **Coordonnées du développeur** : votre email
4. Cliquer sur **"Enregistrer et continuer"**
5. Page "Champs d'application" : cliquer sur **"Enregistrer et continuer"** (pas besoin d'ajouter de scopes)
6. Page "Utilisateurs test" : en mode **brouillon**, ajouter votre propre email Google pour les tests
7. Cliquer sur **"Enregistrer et continuer"** puis **"Retour au tableau de bord"**

---

## Étape 3 — Créer les identifiants OAuth 2.0

1. Dans **"API et Services"** → **"Identifiants"**
2. Cliquer sur **"+ Créer des identifiants"** → **"ID client OAuth"**
3. Type d'application : **"Application Web"**
4. Nom : `Drupal Boutique — SSO`
5. **URI de redirection autorisés** (critical) — ajouter :
   - `https://atelierodesign.fr/user/social-auth/callback/google` (production)
   - `https://dev-atelierodesign.pantheonsite.io/user/social-auth/callback/google` (sandbox Pantheon)
   - `https://odesign-drupal.ddev.site/user/social-auth/callback/google` (DDEV local)
6. Cliquer sur **"Créer"**
7. Une fenêtre affiche le **Client ID** et le **Client Secret** → **COPIER LES DEUX MAINTENANT**

> ⚠️ Le Client Secret ne sera plus affiché intégralement après fermeture de la fenêtre.
> Stocker ces valeurs dans un gestionnaire de mots de passe sécurisé (Bitwarden, 1Password...).

---

## Étape 4 — Saisir les credentials dans Drupal

1. Se connecter à l'administration Drupal
2. Aller sur : **Configuration → Social API → Social Auth → Google**
   → URL directe : `/admin/config/social-api/social-auth/google`
3. Remplir :
   - **Client ID** : valeur copiée à l'étape 3
   - **Client Secret** : valeur copiée à l'étape 3
   - **Scopes** : laisser vide (les scopes par défaut sont suffisants)
   - **Endpoints** : laisser vide
4. Cliquer sur **"Enregistrer la configuration"**

> ✅ Après enregistrement, le bouton **"Continuer avec Google"** apparaît automatiquement
> sur les pages `/user/login` et `/user/register`.

---

## Étape 5 — Vérifier le flux de connexion

1. Ouvrir une fenêtre de navigation privée
2. Aller sur `https://odesign-drupal.ddev.site/user/login` (ou l'URL prod)
3. Vérifier que le bouton **"Continuer avec Google"** est visible au-dessus du formulaire
4. Cliquer dessus → redirection vers Google → choisir son compte Google
5. Après consentement → retour sur le site, compte Drupal créé ou connexion effectuée
6. Vérifier dans **Administration → Personnes** que le nouveau compte :
   - a le rôle `Utilisateur authentifié` uniquement
   - **n'a PAS** le rôle `gestionnaire_boutique`

---

## Étape 6 — Passer l'app en production (Google)

L'app Google est en mode "Test" par défaut (seuls les emails test autorisés peuvent se connecter).
Pour activer pour tous vos clients :

1. Dans Google Cloud Console → **"Écran d'autorisation OAuth"**
2. Cliquer sur **"Publier l'application"**
3. Si demande de vérification Google : suivre le processus (peut prendre 2–5 jours)

---

## Sécurité

- **Ne jamais** stocker le Client ID / Client Secret dans le dépôt Git
- En cas de fuite de secrets : régénérer immédiatement dans Google Cloud Console → Identifiants
- Le rôle `gestionnaire_boutique` ne peut pas être obtenu via SSO (protection en place)

---

## Support

Problème : le bouton Google n'apparaît pas
→ Vérifier que Client ID est bien renseigné dans `/admin/config/social-api/social-auth/google`

Problème : erreur "redirect_uri_mismatch"
→ Vérifier que l'URI de redirection dans Google Cloud Console correspond exactement à l'URL du site

Problème : "Access blocked: This app's request is invalid"
→ L'écran de consentement OAuth n'est pas configuré → reprendre Étape 2
