<?php
/**
 * @file
 * Crée les vues catalogue (/produits) et blog (/blog).
 *
 * Usage : ddev drush php-script scripts/setup-views.php
 *         terminus drush atelierodesign.dev -- php-script scripts/setup-views.php
 */

use Drupal\views\Entity\View;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\Entity\Entity\EntityViewDisplay;

$view_storage = \Drupal::entityTypeManager()->getStorage('view');

// ---------------------------------------------------------------------------
// 1. View modes : teaser pour commerce_product et article
// ---------------------------------------------------------------------------

// View mode teaser pour commerce_product.
$vm_id = 'commerce_product.teaser';
if (!\Drupal::entityTypeManager()->getStorage('entity_view_mode')->load($vm_id)) {
  $vm = EntityViewMode::create([
    'id'               => $vm_id,
    'targetEntityType' => 'commerce_product',
    'label'            => 'Teaser (carte produit)',
  ]);
  $vm->save();
  echo "✓ View mode commerce_product.teaser créé\n";

  // Display associé.
  $display = EntityViewDisplay::create([
    'targetEntityType' => 'commerce_product',
    'bundle'           => 'default',
    'mode'             => 'teaser',
    'status'           => TRUE,
  ]);
  $display->setComponent('title', [
    'type'     => 'string',
    'weight'   => 0,
    'label'    => 'hidden',
    'settings' => ['link_to_entity' => TRUE],
  ]);
  $display->setComponent('field_product_images', [
    'type'     => 'entity_reference_entity_view',
    'weight'   => -1,
    'label'    => 'hidden',
    'settings' => ['view_mode' => 'default', 'link' => TRUE],
  ]);
  $display->removeComponent('body');
  $display->save();
  echo "  ✓ Display teaser configuré\n";
}
else {
  echo "SKIP : view mode teaser existe déjà\n";
}

// View mode teaser pour node.
$vm_node_id = 'node.teaser';
if (!\Drupal::entityTypeManager()->getStorage('entity_view_mode')->load($vm_node_id)) {
  $vm_node = EntityViewMode::create([
    'id'               => $vm_node_id,
    'targetEntityType' => 'node',
    'label'            => 'Teaser',
  ]);
  $vm_node->save();
  echo "✓ View mode node.teaser créé\n";

  // Display pour article teaser.
  $display_article = EntityViewDisplay::create([
    'targetEntityType' => 'node',
    'bundle'           => 'article',
    'mode'             => 'teaser',
    'status'           => TRUE,
  ]);
  $display_article->setComponent('title', [
    'type'     => 'string',
    'weight'   => 0,
    'label'    => 'hidden',
    'settings' => ['link_to_entity' => TRUE],
  ]);
  $display_article->setComponent('body', [
    'type'     => 'text_summary_or_trimmed',
    'weight'   => 1,
    'label'    => 'hidden',
    'settings' => ['trim_length' => 200],
  ]);
  $display_article->save();
  echo "  ✓ Display article teaser configuré\n";
}
else {
  echo "SKIP : view mode node.teaser existe déjà\n";
}

// ---------------------------------------------------------------------------
// 2. Vue catalogue (/produits)
// ---------------------------------------------------------------------------

if ($view_storage->load('catalogue')) {
  echo "SKIP : vue 'catalogue' existe déjà\n";
}
else {
  $view = View::create([
    'id'     => 'catalogue',
    'label'  => 'Catalogue produits',
    'module' => 'views',
    'base_table' => 'commerce_product_field_data',
    'display' => [
      'default' => [
        'id'             => 'default',
        'display_title'  => 'Principal',
        'display_plugin' => 'default',
        'position'       => 0,
        'display_options' => [
          'title'    => 'Boutique',
          'use_ajax' => FALSE,
          'pager'    => [
            'type'    => 'full',
            'options' => [
              'items_per_page' => 24,
              'offset'         => 0,
              'id'             => 0,
              'total_pages'    => NULL,
              'tags'           => ['first' => '', 'previous' => '‹', 'next' => '›', 'last' => ''],
              'expose'         => ['items_per_page' => FALSE, 'offset' => FALSE],
              'quantity'       => 9,
            ],
          ],
          'row'      => [
            'type'    => 'entity:commerce_product',
            'options' => ['relationship' => 'none', 'view_mode' => 'teaser'],
          ],
          'filters'  => [
            'status' => [
              'id'         => 'status',
              'table'      => 'commerce_product_field_data',
              'field'      => 'status',
              'value'      => '1',
              'group'      => 1,
              'plugin_id'  => 'boolean',
              'expose'     => ['operator_id' => '', 'label' => '', 'identifier' => '', 'required' => FALSE, 'remember' => FALSE, 'multiple' => FALSE, 'remember_roles' => ['authenticated' => 'authenticated'], 'use_operator' => FALSE, 'operator' => '', 'operator_limit_selection' => FALSE, 'operator_list' => [], 'description' => ''],
              'is_grouped' => FALSE,
            ],
          ],
          'sorts'    => [
            'created' => [
              'id'        => 'created',
              'table'     => 'commerce_product_field_data',
              'field'     => 'created',
              'order'     => 'DESC',
              'plugin_id' => 'date',
            ],
          ],
          'access'   => ['type' => 'none', 'options' => []],
          'cache'    => ['type' => 'tag', 'options' => []],
          'query'    => ['type' => 'views_query', 'options' => ['distinct' => FALSE, 'replica' => FALSE, 'query_comment' => '', 'query_tags' => []]],
          'display_extenders' => [],
        ],
      ],
      'page_catalogue' => [
        'id'             => 'page_catalogue',
        'display_title'  => 'Page catalogue',
        'display_plugin' => 'page',
        'position'       => 1,
        'display_options' => [
          'path'   => 'produits',
          'menu'   => [
            'type'        => 'normal',
            'title'       => 'Boutique',
            'description' => '',
            'expanded'    => FALSE,
            'parent'      => '',
            'weight'      => 0,
            'context'     => 0,
            'menu_name'   => 'main',
          ],
        ],
      ],
    ],
  ]);
  $view->save();
  echo "✓ Vue 'catalogue' créée (path: /produits)\n";
}

// ---------------------------------------------------------------------------
// 3. Vue blog (/blog)
// ---------------------------------------------------------------------------

if ($view_storage->load('blog')) {
  echo "SKIP : vue 'blog' existe déjà\n";
}
else {
  $view_blog = View::create([
    'id'         => 'blog',
    'label'      => 'Blog',
    'module'     => 'views',
    'base_table' => 'node_field_data',
    'display'    => [
      'default' => [
        'id'             => 'default',
        'display_title'  => 'Principal',
        'display_plugin' => 'default',
        'position'       => 0,
        'display_options' => [
          'title'    => 'Journal',
          'use_ajax' => FALSE,
          'pager'    => [
            'type'    => 'full',
            'options' => [
              'items_per_page' => 10,
              'offset'         => 0,
              'id'             => 0,
              'total_pages'    => NULL,
              'tags'           => ['first' => '', 'previous' => '‹', 'next' => '›', 'last' => ''],
              'expose'         => ['items_per_page' => FALSE, 'offset' => FALSE],
              'quantity'       => 9,
            ],
          ],
          'row'     => [
            'type'    => 'entity:node',
            'options' => ['relationship' => 'none', 'view_mode' => 'teaser'],
          ],
          'filters' => [
            'status' => [
              'id'        => 'status',
              'table'     => 'node_field_data',
              'field'     => 'status',
              'value'     => '1',
              'group'     => 1,
              'plugin_id' => 'boolean',
              'expose'    => ['operator_id' => '', 'label' => '', 'identifier' => '', 'required' => FALSE, 'remember' => FALSE, 'multiple' => FALSE, 'remember_roles' => ['authenticated' => 'authenticated'], 'use_operator' => FALSE, 'operator' => '', 'operator_limit_selection' => FALSE, 'operator_list' => [], 'description' => ''],
              'is_grouped' => FALSE,
            ],
            'type' => [
              'id'        => 'type',
              'table'     => 'node_field_data',
              'field'     => 'type',
              'value'     => ['article' => 'article'],
              'group'     => 1,
              'plugin_id' => 'bundle',
              'expose'    => ['operator_id' => '', 'label' => '', 'identifier' => '', 'required' => FALSE, 'remember' => FALSE, 'multiple' => FALSE, 'remember_roles' => ['authenticated' => 'authenticated'], 'use_operator' => FALSE, 'operator' => '', 'operator_limit_selection' => FALSE, 'operator_list' => [], 'description' => ''],
              'is_grouped' => FALSE,
            ],
          ],
          'sorts'   => [
            'created' => [
              'id'        => 'created',
              'table'     => 'node_field_data',
              'field'     => 'created',
              'order'     => 'DESC',
              'plugin_id' => 'date',
            ],
          ],
          'access'  => ['type' => 'none', 'options' => []],
          'cache'   => ['type' => 'tag', 'options' => []],
          'query'   => ['type' => 'views_query', 'options' => ['distinct' => FALSE, 'replica' => FALSE, 'query_comment' => '', 'query_tags' => []]],
          'display_extenders' => [],
        ],
      ],
      'page_blog' => [
        'id'             => 'page_blog',
        'display_title'  => 'Page blog',
        'display_plugin' => 'page',
        'position'       => 1,
        'display_options' => [
          'path'   => 'blog',
          'menu'   => [
            'type'        => 'normal',
            'title'       => 'Journal',
            'description' => '',
            'expanded'    => FALSE,
            'parent'      => '',
            'weight'      => 5,
            'context'     => 0,
            'menu_name'   => 'main',
          ],
        ],
      ],
    ],
  ]);
  $view_blog->save();
  echo "✓ Vue 'blog' créée (path: /blog)\n";
}

// Vider les caches.
drupal_flush_all_caches();
echo "✓ Caches vidés\n";
echo "\nSetup vues terminé.\n";
