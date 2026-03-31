<?php
/**
 * enrich-products-from-csv.php — v2
 * Enrichit les produits depuis products_enrichment.json (issu du CSV Shopify).
 */

$enrichmentFile = __DIR__ . '/products_enrichment.json';
$enrichments = json_decode(file_get_contents($enrichmentFile), TRUE);
$byHandle = [];
foreach ($enrichments as $e) {
  $byHandle[$e['handle']] = $e;
}

// Mapping Type Shopify → catégorie Drupal
$typeMap = [
  'Pancarte mariage'     => 'Mariage',
  'Article de mariage'   => 'Mariage',
  'Carte de naissance'   => 'Naissance',
  'Souvenir naissance'   => 'Naissance',
  'Cadeau en bois'       => 'Décoration',
  'Souvenir en bois'     => 'Décoration',
  'Apprentissage'        => 'Décoration',
  'Pense bête en bois'   => 'Décoration',
  'Support carte de jeu' => 'Décoration',
  'Puzzle en bois'       => 'Décoration',
  'arbre de vie'         => 'Décoration',
];

$termStorage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$terms = [];
foreach ($termStorage->loadByProperties(['vid' => 'product_category']) as $term) {
  $terms[$term->label()] = $term->id();
}

// Charger tous les alias /product/* → handle
$aliasManager = \Drupal::service('path_alias.manager');
$productStorage = \Drupal::entityTypeManager()->getStorage('commerce_product');
$products = $productStorage->loadMultiple();

$stats = ['enriched' => 0, 'body_set' => 0, 'cat_set' => 0, 'no_match' => 0];
$noMatchList = [];

foreach ($products as $product) {
  $pid = $product->id();
  // Récupérer l'alias depuis /product/{pid}
  $alias = $aliasManager->getAliasByPath('/product/' . $pid);
  $handle = $alias ? ltrim(basename($alias), '/') : NULL;

  // Fallback : slugifier le titre
  if (!$handle || $handle === '/product/' . $pid) {
    $handle = preg_replace('/[^a-z0-9]+/', '-', strtolower($product->getTitle()));
    $handle = trim($handle, '-');
  }

  $data = $byHandle[$handle] ?? NULL;
  if (!$data) {
    // Essai avec le handle normalisé (retirant -bois, accents, etc.)
    foreach ($byHandle as $csvHandle => $csvData) {
      similar_text($handle, $csvHandle, $pct);
      if ($pct > 85) { $data = $csvData; break; }
    }
  }

  if (!$data) {
    $stats['no_match']++;
    $noMatchList[] = "[{$pid}] " . substr($product->getTitle(), 0, 40) . " (handle: $handle)";
    continue;
  }

  $changed = FALSE;

  // 1. Body vide → SEO Description
  $body = trim(strip_tags($product->get('body')->value ?? ''));
  if (empty($body) && !empty($data['seo_desc'])) {
    $product->set('body', ['value' => $data['seo_desc'], 'format' => 'basic_html']);
    $stats['body_set']++;
    $changed = TRUE;
  }

  // 2. Catégorie depuis Type Shopify (priorité sur heuristique précédente)
  if (!empty($data['type']) && isset($typeMap[$data['type']])) {
    $catName = $typeMap[$data['type']];
    if (isset($terms[$catName])) {
      $currentCat = $product->get('field_category')->target_id;
      // Toujours appliquer — le type CSV est plus fiable
      $product->set('field_category', ['target_id' => $terms[$catName]]);
      $stats['cat_set']++;
      $changed = TRUE;
    }
  }

  if ($changed) {
    $product->save();
    $stats['enriched']++;
    $catLabel = !empty($data['type']) ? ($typeMap[$data['type']] ?? '—') : '—';
    $descPreview = !empty($data['seo_desc']) ? substr($data['seo_desc'], 0, 45) . '…' : '(existant)';
    echo sprintf("  ✅ [%3d] %-45s | %-12s | %s\n",
      $pid, substr($product->getTitle(), 0, 45), $catLabel, $descPreview);
  }
}

echo "\n=== Résumé ===\n";
echo "  Produits enrichis        : {$stats['enriched']}\n";
echo "  Body (desc) rempli       : {$stats['body_set']}\n";
echo "  Catégorie mise à jour    : {$stats['cat_set']}\n";
echo "  Sans correspondance CSV  : {$stats['no_match']}\n";
if ($noMatchList) {
  echo "\n  Produits sans match :\n";
  foreach (array_slice($noMatchList, 0, 10) as $item) echo "    $item\n";
  if (count($noMatchList) > 10) echo "    ... +" . (count($noMatchList)-10) . " autres\n";
}
