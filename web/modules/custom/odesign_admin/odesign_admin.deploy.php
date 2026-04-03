<?php

/**
 * @file
 * ODesign Admin — hooks de déploiement.
 *
 * Ces hooks sont exécutés par `drush deploy` (Drupal Deploy hook convention).
 * Sur Pantheon : `terminus drush atelierodesign.<env> -- deploy`
 *
 * Workflow de versionnement du contenu éditorial :
 *
 * EXPORT (après modification d'un guide ou d'une page dans l'admin) :
 *   drush dcde node --bundle=guide --folder=web/modules/custom/odesign_admin/content
 *   drush dcde node --bundle=page  --folder=web/modules/custom/odesign_admin/content
 *   git add web/modules/custom/odesign_admin/content/
 *   git commit -m "content(guide): mise à jour guide marketing"
 *   git push
 *
 * IMPORT (sur un environnement cible — appliqué automatiquement via hook_deploy_N) :
 *   drush dcdi --folder=web/modules/custom/odesign_admin/content
 *
 * Comportement d'import (force_override = FALSE) :
 *   - Nœud absent du site cible → créé
 *   - Nœud présent avec UUID identique ET fichier plus récent → mis à jour
 *   - Nœud présent avec UUID identique ET fichier moins récent → ignoré (modifications locales préservées)
 *   - Pour forcer l'écrasement : drush dcdi --force-override
 *
 * Bundles versionnés : node/page, node/guide
 * Format : HAL+JSON (default_content_deploy 2.x)
 * Commande d'export : drush dcde (default-content-deploy:export)
 * Commande d'import : drush dcdi (default-content-deploy:import)
 */

use Drupal\default_content_deploy\DeployManager;

/**
 * Implémente hook_deploy_N().
 *
 * Importe les contenus éditoriaux versionnés (page + guide) lors du déploiement.
 * S'exécute via `drush deploy` après updatedb et config:import.
 *
 * Les contenus existants dont l'UUID correspond sont mis à jour si la version
 * git est plus récente. Les contenus inconnus sont ignorés (--force-override
 * n'est pas activé pour ne pas écraser les modifications manuelles sur prod).
 */
function odesign_admin_deploy_001(): void {
  $content_dir = \Drupal::service('extension.list.module')
    ->getPath('odesign_admin') . '/content';

  if (!is_dir($content_dir)) {
    // Aucun contenu à importer — répertoire absent.
    return;
  }

  /** @var \Drupal\default_content_deploy\DeployManager $deploy_manager */
  $deploy_manager = \Drupal::service('default_content_deploy.manager');
  $deploy_manager->importContent($content_dir, FALSE);
}
