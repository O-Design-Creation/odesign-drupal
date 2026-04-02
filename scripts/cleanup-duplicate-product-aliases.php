<?php

/**
 * @file
 * Nettoyage prudent des doublons produits sur alias Shopify.
 *
 * Règle de conservation :
 *  1. garder le produit le plus référencé par des order items ;
 *  2. à égalité, garder le plus ancien ;
 *  3. à égalité parfaite, garder le plus petit product_id.
 *
 * Usage :
 *   ddev drush php-script scripts/cleanup-duplicate-product-aliases.php
 *   ddev drush php:eval 'putenv("DRY_RUN=0"); include "/var/www/html/scripts/cleanup-duplicate-product-aliases.php";'
 */

$dry_run = getenv('DRY_RUN');
$dry_run = ($dry_run === FALSE || $dry_run === '' || $dry_run === '1');

/**
 * Retourne les candidats liés à un alias.
 */
function load_duplicate_candidates(string $alias): array {
  $query = \Drupal::database()->select('commerce_product_field_data', 'cfd');
  $query->fields('cfd', ['product_id', 'title', 'created']);
  $query->condition('pa.alias', $alias);
  $query->leftJoin('path_alias', 'pa', "pa.path = CONCAT('/product/', cfd.product_id)");
  $query->leftJoin('commerce_product__variations', 'v', 'v.entity_id = cfd.product_id AND v.deleted = 0');
  $query->leftJoin('commerce_order_item', 'oi', 'oi.purchased_entity = v.variations_target_id');
  $query->addExpression('COUNT(DISTINCT oi.order_item_id)', 'order_items');
  $query->groupBy('cfd.product_id');
  $query->groupBy('cfd.title');
  $query->groupBy('cfd.created');
  $query->orderBy('cfd.product_id', 'ASC');

  return $query->execute()->fetchAllAssoc('product_id');
}

/**
 * Choisit le produit à conserver.
 */
function choose_product_to_keep(array $candidates): array {
  usort($candidates, static function ($a, $b) {
    $order_cmp = ((int) $b->order_items) <=> ((int) $a->order_items);
    if ($order_cmp !== 0) {
      return $order_cmp;
    }

    $created_cmp = ((int) $a->created) <=> ((int) $b->created);
    if ($created_cmp !== 0) {
      return $created_cmp;
    }

    return ((int) $a->product_id) <=> ((int) $b->product_id);
  });

  return [(array) $candidates[0], array_map(static fn($row) => (array) $row, array_slice($candidates, 1))];
}

$duplicate_aliases = \Drupal::database()->select('path_alias', 'pa')
  ->fields('pa', ['alias'])
  ->condition('pa.alias', '/products/%', 'LIKE')
  ->groupBy('pa.alias')
  ->having('COUNT(*) > 1')
  ->orderBy('pa.alias', 'ASC')
  ->execute()
  ->fetchCol();

if (!$duplicate_aliases) {
  echo "Aucun alias dupliqué à traiter.\n";
  return;
}

$storage = \Drupal::entityTypeManager()->getStorage('commerce_product');
$deleted = 0;

echo $dry_run
  ? "MODE DRY-RUN — aucune suppression ne sera effectuée.\n\n"
  : "MODE ACTIF — suppression des doublons non conservés.\n\n";

foreach ($duplicate_aliases as $alias) {
  $candidates = load_duplicate_candidates($alias);
  if (count($candidates) < 2) {
    continue;
  }

  [$keep, $delete_rows] = choose_product_to_keep(array_values($candidates));

  echo "Alias : {$alias}\n";
  echo "  Gardé : id={$keep['product_id']} | created=" . date('Y-m-d H:i:s', (int) $keep['created']) . " | order_items={$keep['order_items']} | {$keep['title']}\n";

  foreach ($delete_rows as $row) {
    echo "  Suppr. : id={$row['product_id']} | created=" . date('Y-m-d H:i:s', (int) $row['created']) . " | order_items={$row['order_items']} | {$row['title']}\n";

    if ($dry_run) {
      continue;
    }

    $product = $storage->load((int) $row['product_id']);
    if (!$product) {
      continue;
    }

    if ((int) $row['order_items'] > 0) {
      echo "    SKIP sécurité : produit encore référencé par des commandes.\n";
      continue;
    }

    $product->delete();
    $deleted++;
  }

  echo "\n";
}

echo $dry_run
  ? "Fin du dry-run.\n"
  : "Suppression terminée. Produits supprimés : {$deleted}\n";
