<?php

/**
 * @file
 * Audit non destructif des doublons produits sur alias Shopify.
 *
 * Produit un rapport lisible avant toute opération de nettoyage.
 *
 * Usage :
 *   ddev drush php-script scripts/audit-duplicate-product-aliases.php
 *   terminus drush atelierodesign.dev -- php-script scripts/audit-duplicate-product-aliases.php
 */

$query = \Drupal::database()->select('path_alias', 'pa');
$query->fields('pa', ['alias']);
$query->condition('pa.alias', '/products/%', 'LIKE');
$query->groupBy('pa.alias');
$query->having('COUNT(*) > 1');
$query->addExpression('COUNT(*)', 'nb');
$query->orderBy('pa.alias', 'ASC');
$duplicates = $query->execute()->fetchAllAssoc('alias');

if (!$duplicates) {
  echo "Aucun alias produit dupliqué.\n";
  return;
}

echo "=== DOUBLONS ALIAS PRODUITS ===\n";
echo "Nombre d'aliases dupliqués : " . count($duplicates) . "\n\n";

foreach ($duplicates as $alias => $row) {
  echo "Alias : {$alias} ({$row->nb} occurrences)\n";

  $detail_query = \Drupal::database()->select('path_alias', 'pa');
  $detail_query->fields('pa', ['path']);
  $detail_query->condition('pa.alias', $alias);
  $paths = $detail_query->execute()->fetchCol();

  $product_ids = [];
  foreach ($paths as $path) {
    if (preg_match('#^/product/(\d+)$#', $path, $matches)) {
      $product_ids[] = (int) $matches[1];
    }
  }

  sort($product_ids);

  if (!$product_ids) {
    echo "  - Aucun product_id résolu depuis les paths.\n\n";
    continue;
  }

  $products = \Drupal::entityTypeManager()
    ->getStorage('commerce_product')
    ->loadMultiple($product_ids);

  $oldest = NULL;
  $newest = NULL;

  foreach ($product_ids as $product_id) {
    if (!isset($products[$product_id])) {
      continue;
    }

    $product = $products[$product_id];
    $created = (int) $product->getCreatedTime();

    if ($oldest === NULL || $created < $oldest['created']) {
      $oldest = ['id' => $product_id, 'created' => $created];
    }
    if ($newest === NULL || $created > $newest['created']) {
      $newest = ['id' => $product_id, 'created' => $created];
    }

    echo '  - id=' . $product_id
      . ' | created=' . date('Y-m-d H:i:s', $created)
      . ' | title=' . $product->label()
      . "\n";
  }

  if ($oldest && $newest) {
    echo '  Suggestion audit seulement : garder '
      . $oldest['id']
      . ' (plus ancien) ou '
      . $newest['id']
      . " (plus récent), après vérification humaine.\n";
  }

  echo "\n";
}

echo "Fin du rapport. Aucune suppression effectuée.\n";
