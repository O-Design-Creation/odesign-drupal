<?php
/**
 * Ajoute les champs taxonomie aux view displays (teaser + full).
 */
$displayStorage = \Drupal::entityTypeManager()->getStorage('entity_view_display');

// --- Article : affichage full ---
$articleFull = $displayStorage->load('node.article.default');
if ($articleFull) {
  // Tags
  $articleFull->setComponent('field_tags', [
    'type'   => 'entity_reference_label',
    'weight' => 20,
    'region' => 'content',
    'label'  => 'inline',
    'settings' => ['link' => FALSE],
    'third_party_settings' => [],
  ]);
  // Catégorie
  $articleFull->setComponent('field_blog_category', [
    'type'   => 'entity_reference_label',
    'weight' => 15,
    'region' => 'content',
    'label'  => 'inline',
    'settings' => ['link' => FALSE],
    'third_party_settings' => [],
  ]);
  $articleFull->save();
  echo "✅ Article full display : field_tags + field_blog_category ajoutés\n";
}

// --- Article : affichage teaser ---
$articleTeaser = $displayStorage->load('node.article.teaser');
if ($articleTeaser) {
  $articleTeaser->setComponent('field_blog_category', [
    'type'   => 'entity_reference_label',
    'weight' => 5,
    'region' => 'content',
    'label'  => 'hidden',
    'settings' => ['link' => FALSE],
    'third_party_settings' => [],
  ]);
  $articleTeaser->save();
  echo "✅ Article teaser display : field_blog_category ajouté\n";
} else {
  echo "ℹ️  Pas de display 'teaser' pour article (display custom Twig utilisé)\n";
}

// --- Produit : affichage teaser — field_category ---
$productTeaser = $displayStorage->load('commerce_product.default.teaser');
if ($productTeaser) {
  $productTeaser->setComponent('field_category', [
    'type'   => 'entity_reference_label',
    'weight' => 30,
    'region' => 'content',
    'label'  => 'hidden',
    'settings' => ['link' => FALSE],
    'third_party_settings' => [],
  ]);
  $productTeaser->save();
  echo "✅ Produit teaser display : field_category ajouté\n";
}

echo "\n✅ View displays taxonomie configurés.\n";
