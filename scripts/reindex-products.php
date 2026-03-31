<?php
$index = \Drupal::entityTypeManager()->getStorage('search_api_index')->load('products');
$index->reindex();
\Drupal::service('search_api.index_task_manager')->addItemsToIndex($index);
// Batch indexing
$index->indexItems(0); // 0 = all items
\Drupal::service('cron')->run();
$tracker = $index->getTrackerInstance();
echo "Total  : " . $tracker->getTotalItemsCount() . "\n";
echo "Indexés: " . $tracker->getIndexedItemsCount() . "\n";
