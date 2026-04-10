<?php
/**
 * @file
 * Réindexation Search API — index "Products".
 *
 * Ce script est un wrapper de documentation.
 * La commande Drush native est plus fiable (et obligatoire depuis la suppression
 * de IndexTaskManager::addItemsToIndex() dans search_api).
 *
 * Usage recommandé :
 *   ddev exec drush search-api:index --yes
 *   terminus drush atelierodesign.dev -- search-api:index --yes
 *
 * Ce script appelle la commande Drush équivalente programmatiquement.
 */

$index = \Drupal::entityTypeManager()->getStorage('search_api_index')->load('products');
if (!$index) {
  echo "ERREUR : index 'products' introuvable. Vérifier la config Search API.\n";
  return;
}

// Marquer tous les items pour réindexation
$index->reindex();

// Indexer via l'index entity (API stable search_api 1.x / 2.x)
$indexed = $index->indexItems(0); // 0 = tous les items
echo "Items indexés : {$indexed}\n";

$tracker = $index->getTrackerInstance();
echo "Total  : " . $tracker->getTotalItemsCount() . "\n";
echo "Indexés: " . $tracker->getIndexedItemsCount() . "\n";
