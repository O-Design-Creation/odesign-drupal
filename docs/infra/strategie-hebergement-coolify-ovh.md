# Stratégie d'hébergement et d'automatisation (OVH + Coolify)

## Contexte

- Hébergement actuel d'intégration: Pantheon.
- Contrainte de démarrage: un seul serveur `OVH VPS-2`.
- Objectif: héberger `preprod` et `prod` avec un niveau de fiabilité acceptable, en restant frugal.

## Décision d'architecture (phase démarrage)

Architecture retenue avec 1 seul VPS:

1. Coolify sur le VPS-2.
2. Deux stacks applicatives isolées:
   - `drupal-preprod`
   - `drupal-prod`
3. Deux bases MariaDB distinctes + utilisateurs SQL distincts.
4. Deux jeux de volumes distincts (fichiers publics et privés).
5. Deux jeux de variables d'environnement distincts (SMTP, Stripe, secrets Drupal).

Limite connue:

- Avec un seul VPS, `preprod` et `prod` partagent le même point de panne (SPOF).
- La mitigation prioritaire est la stratégie de sauvegarde/restauration hors VPS.

## Scénarios d'automatisation envisagés

### Scénario A - Minimal shell + cron

- Dump DB + archive fichiers via scripts shell.
- Envoi vers stockage objet via `rclone`.
- Rotation basique.

Avantages:

- Très simple à mettre en place.

Inconvénients:

- Moins robuste (vérification/restauration/chiffrement moins structurés).

### Scénario B - Restic complet (recommandé)

- Sauvegarde chiffrée côté client avec `restic`.
- Cible: OVH Object Storage (S3 compatible).
- Rétention: `14 daily`, `8 weekly`, `12 monthly`.
- Vérification hebdomadaire (`restic check`).
- Test de restauration mensuel.

Avantages:

- Très bon ratio coût/fiabilité/simplicité.
- Déduplication native et restauration claire.

Inconvénients:

- Demande une discipline d'exploitation (checks et restore-tests).

### Scénario C - Snapshot VPS + Restic

- Snapshot machine + sauvegarde applicative `restic`.

Avantages:

- Récupération machine plus rapide.

Inconvénients:

- Coût et complexité supérieurs au démarrage.

### Scénario D - Full GitOps orchestration

- Backups orchestrés et audités via CI/CD.

Avantages:

- Traçabilité forte.

Inconvénients:

- Trop lourd pour un démarrage sur un seul VPS.

## Choix retenu

- Court terme: **Scénario B (Restic complet)**.
- Moyen terme: évolution vers **Scénario C** quand la charge business augmente.

## Convention de release et déploiement

- Tag `X.YrcN` (ex: `2.4rc1`) -> déploiement automatique `preprod`.
- Tag `X.Y.Z` (ex: `2.4.0`) -> déploiement `prod` manuel/validé.

Règle importante:

- La production doit promouvoir la **même image** validée en preprod (pas de rebuild).

## Pipeline recommandé

1. Build image Docker versionnée (GHCR).
2. Push image taggée (`X.YrcN` ou `X.Y.Z`).
3. Déploiement auto preprod sur tags `rc`.
4. Déploiement prod manuel sur tags stables.
5. Post-déploiement Drupal:
   - `drush updb -y`
   - `drush cim -y`
   - `drush cr`

## Sauvegardes frugales (recommandation)

Stockage cible:

- OVH Object Storage S3, classe Infrequent Access pour la rétention standard.
- Évolution possible vers lifecycle Cold Archive pour le long terme.

Politique:

- Fréquence: quotidien.
- Rétention: `14 daily`, `8 weekly`, `12 monthly`.
- Vérification: `restic check` hebdomadaire.
- Test de restauration: mensuel.

### Coût estimatif (ordre de grandeur)

Hypothèses:

- Coût IA OVH: ~`0,004 EUR / GiB / mois` (hors TVA).
- Stockage moyen `S` (GiB): coût ~`S * 0,004 EUR`.

Exemples:

- `100 GiB` -> `~0,40 EUR / mois`.
- `250 GiB` -> `~1,00 EUR / mois`.
- `500 GiB` -> `~2,00 EUR / mois`.
- `1 TiB` -> `~4,10 EUR / mois`.

Note:

- Les restaurations IA ajoutent un coût ponctuel par GiB récupéré.

## Préparer un serveur de secours plus tard

Objectif: permettre une bascule future sans refonte.

À faire dès maintenant:

1. Éviter les IP codées en dur dans la config applicative.
2. Utiliser des noms DNS + variables d'environnement.
3. Garder un TTL DNS court (60-300s) sur les entrées critiques.
4. Garantir que les sauvegardes sont hors VPS et restaurables.

Évolutions futures possibles:

1. Bascule DNS manuelle vers IP de secours.
2. Failover DNS automatisé avec healthcheck.
3. Réplication DB + sync fichiers + runbook de bascule.

## Plan exécutable J0 -> J7

### J0 - Préparation

1. Valider conventions tags (`rc` et stable).
2. Créer matrice des secrets preprod/prod.
3. Définir critères de validation preprod avant promotion prod.

### J1 - Durcissement serveur

1. SSH par clé uniquement.
2. Désactiver root SSH.
3. Activer firewall et fail2ban.
4. Activer mises à jour sécurité automatiques.

### J2 - Base Coolify

1. Vérifier DNS et TLS pour:
   - panel Coolify
   - preprod
   - prod
2. Activer healthchecks applicatifs.

### J3 - Déploiement preprod

1. Créer stack `drupal-preprod` + DB dédiée.
2. Monter volumes dédiés.
3. Injecter secrets dédiés.
4. Ajouter post-déploiement Drush.

### J4 - Déploiement prod

1. Créer stack `drupal-prod` + DB dédiée.
2. Séparer strictement volumes et secrets.
3. Garder déploiement prod manuel.

### J5 - CI/CD tags

1. Implémenter workflow `X.YrcN` -> preprod auto.
2. Implémenter workflow `X.Y.Z` -> prod manuel.
3. Garantir promotion d'image immuable.

### J6 - Sauvegardes et observabilité

1. Mettre en place backups quotidiens restic (DB + fichiers).
2. Configurer rétention et prune.
3. Configurer alerting (uptime, disque, RAM/CPU, TLS).

### J7 - PRA

1. Tester restauration complète preprod.
2. Documenter rollback et incident.
3. Valider un exercice de restauration bout-en-bout.

## Commandes Drupal post-déploiement

```bash
drush status
drush updb -y
drush cim -y
drush cr
drush cron
```

## Références

- OVH Public Cloud pricing: <https://www.ovhcloud.com/en/public-cloud/prices/>
- OVH Object Storage classes: <https://help.ovhcloud.com/csm/en-public-cloud-storage-s3-choosing-right-storage-class?id=kb_article_view&sysparm_article=KB0047289>
- OVH Cold Archive restore: <https://help.ovhcloud.com/csm/en-public-cloud-storage-object-storage-restoring-objects?id=kb_article_view&sysparm_article=KB0073630>
