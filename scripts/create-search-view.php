<?php
/**
 * Crée la vue Search API pour le catalogue avec filtre de recherche exposé.
 * On crée une NOUVELLE vue /recherche séparée pour ne pas casser le catalogue existant.
 */

// Créer la facette "Catégorie"
if (!\Drupal::entityTypeManager()->getStorage('facets_facet')->load('product_category_facet')) {
  $facet = \Drupal\facets\Entity\Facet::create([
    'id'           => 'product_category_facet',
    'name'         => 'Catégorie',
    'url_alias'    => 'categorie',
    'field_identifier' => 'field_category',
    'facet_source_id'  => 'search_api:views_page__catalogue__page_catalogue',
    'widget'       => ['type' => 'links', 'config' => ['show_numbers' => TRUE, 'soft_limit' => 0]],
    'processor_configs' => [
      'url_processor_handler' => [
        'processor_id' => 'url_processor_handler',
        'weights' => ['pre_query' => -10, 'build' => -10],
        'settings' => [],
      ],
      'display_value_widget_order' => [
        'processor_id' => 'display_value_widget_order',
        'weights' => ['sort' => 50],
        'settings' => ['sort' => 'ASC'],
      ],
    ],
    'only_visible_when_facet_source_is_visible' => TRUE,
    'use_hierarchy' => FALSE,
    'expand_hierarchy' => FALSE,
    'enable_parent_when_child_active' => FALSE,
    'min_count' => 1,
    'missing' => FALSE,
    'missing_label' => '',
    'status' => TRUE,
  ]);
  $facet->save();
  echo "✅ Facette 'product_category_facet' créée\n";
} else {
  echo "ℹ️  Facette déjà existante\n";
}
