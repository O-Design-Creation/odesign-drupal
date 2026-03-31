<?php
$pattern = \Drupal\pathauto\Entity\PathautoPattern::create([
  'id' => 'article',
  'label' => 'Article',
  'type' => 'canonical_entities:node',
  'pattern' => 'blog/[node:title]',
  'weight' => 0,
]);
$pattern->addSelectionCondition([
  'id' => 'entity_bundle:node',
  'bundles' => ['article' => 'article'],
  'negate' => FALSE,
  'context_mapping' => ['node' => 'node'],
]);
$pattern->save();

// Bulk-generate aliases for existing articles
$aliasStorageHelper = \Drupal::service('pathauto.alias_storage_helper');
$pathautoGenerator = \Drupal::service('pathauto.generator');
$nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['type' => 'article']);
foreach ($nodes as $node) {
  $pathautoGenerator->createEntityAlias($node, 'bulkupdate');
  echo "Alias généré pour nid " . $node->id() . "\n";
}
