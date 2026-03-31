<?php
// Update global metatag defaults with proper OG keys
$storage = \Drupal::entityTypeManager()->getStorage('metatag_defaults');

// Global defaults
$global = $storage->load('global');
if ($global) {
  $tags = $global->get('tags');
  $tags['og_title']       = '[current-page:title] | [site:name]';
  $tags['og_description'] = 'Atelier O Design — cadeaux personnalisés en bois gravés au laser, faits main en France.';
  $tags['og_site_name']   = '[site:name]';
  $tags['og_type']        = 'website';
  $tags['og_url']         = '[current-page:url]';
  $global->set('tags', $tags);
  $global->save();
  echo "Global OG tags mis à jour\n";
}

// Node defaults
$node = $storage->load('node');
if ($node) {
  $tags = $node->get('tags');
  $tags['og_title']       = '[node:title]';
  $tags['og_description'] = '[node:summary]';
  $tags['og_url']         = '[node:url]';
  $tags['og_type']        = 'article';
  $node->set('tags', $tags);
  $node->save();
  echo "Node OG tags mis à jour\n";
}

// Product defaults (commerce_product)
$productDefaults = $storage->load('commerce_product');
if (!$productDefaults) {
  $productDefaults = $storage->create([
    'id' => 'commerce_product',
    'label' => 'Produit',
    'tags' => [
      'title'          => '[commerce_product:title] | [site:name]',
      'description'    => '[commerce_product:body:summary]',
      'canonical_url'  => '[commerce_product:url]',
      'og_title'       => '[commerce_product:title]',
      'og_description' => '[commerce_product:body:summary]',
      'og_url'         => '[commerce_product:url]',
      'og_type'        => 'product',
    ],
  ]);
} else {
  $tags = $productDefaults->get('tags');
  $tags['og_title']       = '[commerce_product:title]';
  $tags['og_description'] = '[commerce_product:body:summary]';
  $tags['og_url']         = '[commerce_product:url]';
  $tags['og_type']        = 'product';
  $productDefaults->set('tags', $tags);
}
$productDefaults->save();
echo "Product OG tags mis à jour\n";

echo "Cache invalidé\n";
\Drupal::service('cache.render')->invalidateAll();
