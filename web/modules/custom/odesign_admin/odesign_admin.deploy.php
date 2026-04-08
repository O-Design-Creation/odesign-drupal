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
 * EXPORT (après modification d'un contenu de référence dans l'admin) :
 *   drush dcde node --bundle=guide --folder=/var/www/html/web/modules/custom/odesign_admin/content
 *   drush dcde node --bundle=page  --folder=/var/www/html/web/modules/custom/odesign_admin/content
 *   drush dcde taxonomy_term --bundle=life_occasion --folder=/var/www/html/web/modules/custom/odesign_admin/content
 *   git add web/modules/custom/odesign_admin/content/
 *   git commit -m "content(page): mise à jour contenu de référence"
 *   git push
 *
 * IMPORT (sur un environnement cible — appliqué automatiquement via hook_deploy_N) :
 *   drush dcdi --folder=web/modules/custom/odesign_admin/content
 *
 * Comportement d'import (force_override = FALSE) :
 *   - Entité absente du site cible → créée
 *   - Entité présente avec UUID identique ET fichier plus récent → mise à jour
 *   - Entité présente avec UUID identique ET fichier moins récent → ignorée (modifications locales préservées)
 *   - Pour forcer l'écrasement : drush dcdi --force-override
 *
 * Contenus versionnés (2026-04-08) :
 *   node/page : /merci, /contact, /atelier, /cgv, /mentions-legales, /politique-confidentialite
 *   taxonomy_term/life_occasion : Mariage, Naissance & bébé, Cadeaux pers./famille, Pancarte événementielles
 * Format : HAL+JSON (default_content_deploy 2.x)
 * Commande d'export : drush dcde (default-content-deploy:export) — utiliser chemin absolu /var/www/html/...
 * Commande d'import : drush dcdi (default-content-deploy:import)
 */

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

  /** @var \Drupal\default_content_deploy\ImporterInterface $importer */
  $importer = \Drupal::service('default_content_deploy.importer');
  $importer->setFolder($content_dir);
  $importer->setForceOverride(FALSE);
  $importer->import();
}

/**
 * Implémente hook_deploy_N().
 *
 * Corrige les metatag des pages légales encore en DB avec des valeurs DDEV :
 * - Supprime les canonicals pointant vers odesign-drupal.ddev.site
 * - Supprime les og:site_name hardcodés (héritage global via [site:name])
 * - Corrige les titres meta (ODesign Drupal → Atelier O Design)
 * - Normalise la description de la politique de confidentialité
 *
 * Déclencheur : déploiement fix/seo-noindex-canonicals (2026-04-07).
 */
function odesign_admin_deploy_002(): void {
  $aliases = [
    '/cgv'                      => [
      'title'       => 'Conditions Générales de Vente | Atelier O Design',
      'description' => NULL,
    ],
    '/mentions-legales'         => [
      'title'       => 'Mentions Légales | Atelier O Design',
      'description' => NULL,
    ],
    '/politique-confidentialite' => [
      'title'       => 'Politique de Confidentialité | Atelier O Design',
      'description' => "Politique de confidentialité d'Atelier O Design : données collectées, durées de conservation, droits d'accès et de suppression, conformément au RGPD.",
    ],
  ];

  $alias_manager  = \Drupal::service('path_alias.manager');
  $entity_manager = \Drupal::entityTypeManager()->getStorage('node');

  foreach ($aliases as $alias => $overrides) {
    $system_path = $alias_manager->getPathByAlias($alias);
    if (!preg_match('#^/node/(\d+)$#', $system_path, $m)) {
      continue;
    }
    /** @var \Drupal\node\NodeInterface $node */
    $node = $entity_manager->load((int) $m[1]);
    if (!$node || !$node->hasField('metatag')) {
      continue;
    }

    $metatag_field = $node->get('metatag');
    $tags = $metatag_field->value ?? [];
    if (!is_array($tags)) {
      $tags = [];
    }

    // Supprimer canonical et og:site_name ; corriger title et description.
    $cleaned = [];
    foreach ($tags as $key => $value) {
      if ($key === 'canonical_url') {
        continue;
      }
      if ($key === 'og_site_name') {
        continue;
      }
      if ($key === 'title') {
        $value = $overrides['title'];
      }
      if ($key === 'og_title') {
        $value = str_replace('ODesign Drupal', 'Atelier O Design', (string) $value);
      }
      if ($key === 'description' && $overrides['description'] !== NULL) {
        $value = $overrides['description'];
      }
      if ($key === 'og_description' && $overrides['description'] !== NULL) {
        $value = $overrides['description'];
      }
      $cleaned[$key] = $value;
    }

    $metatag_field->value = $cleaned;
    $node->save();
  }
}

/**
 * deploy_003 : Crée la page de confirmation /merci si elle n'existe pas encore.
 */
function odesign_admin_deploy_003(): void {
  $alias_manager = \Drupal::service('path_alias.manager');
  $path = $alias_manager->getPathByAlias('/merci');

  // Si l'alias /merci existe déjà, ne rien faire.
  if ($path !== '/merci') {
    return;
  }

  $node = \Drupal\node\Entity\Node::create([
    'type'     => 'page',
    'title'    => 'Merci pour votre message',
    'body'     => [
      'value'  => '<p>Votre message a bien été reçu. Atelier O Design vous répondra dans les plus brefs délais.</p>'
               . '<p><a href="/produits">Découvrir le catalogue</a> ou <a href="/">retourner à l\'accueil</a>.</p>',
      'format' => 'basic_html',
    ],
    'status'   => 1,
    'langcode' => 'fr',
    'path'     => [['alias' => '/merci']],
  ]);
  $node->save();

  \Drupal::logger('odesign_admin')->info('deploy_003 : page /merci créée (nid @nid).', ['@nid' => $node->id()]);
}
