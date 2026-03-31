<?php
// Check existing Search API indexes and servers
$indexStorage = \Drupal::entityTypeManager()->getStorage('search_api_index');
$serverStorage = \Drupal::entityTypeManager()->getStorage('search_api_server');

$servers = $serverStorage->loadMultiple();
echo "=== Serveurs (" . count($servers) . ") ===\n";
foreach ($servers as $id => $server) {
  echo "  $id : " . $server->label() . " (backend: " . $server->getBackendId() . ", status: " . ($server->status() ? 'enabled' : 'disabled') . ")\n";
}

$indexes = $indexStorage->loadMultiple();
echo "\n=== Index (" . count($indexes) . ") ===\n";
foreach ($indexes as $id => $index) {
  echo "  $id : " . $index->label() . "\n";
  echo "    Datasources: " . implode(', ', array_keys($index->getDatasources())) . "\n";
  echo "    Trackers: " . count($index->getTrackerInstance()->getIndexedItems()) . " indexés\n";
  $fields = $index->getFields();
  echo "    Champs: " . implode(', ', array_keys($fields)) . "\n";
}
