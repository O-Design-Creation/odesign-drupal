<?php
/**
 * update-search-index.php
 * Ajoute field_category à l'index Search API et réindexe tous les produits.
 */
$indexStorage = \Drupal::entityTypeManager()->getStorage('search_api_index');
$index = $indexStorage->load('products');

if (!$index) { echo "❌ Index 'products' introuvable\n"; exit(1); }

$fields = $index->getFields();
echo "Champs actuels : " . implode(', ', array_keys($fields)) . "\n";

// Ajouter field_category si absent
if (!isset($fields['field_category'])) {
  $fieldsHelper = \Drupal::getContainer()->get('search_api.fields_helper');
  $field = $fieldsHelper->createField($index, 'field_category');
  $field->setType('integer');
  $field->setLabel('Catégorie');
  $field->setPropertyPath('field_category');
  $field->setDatasourceId('entity:commerce_product');
  $index->addField($field);
  echo "✅ field_category ajouté\n";
} else {
  echo "ℹ️  field_category déjà présent\n";
}

// Vérifier/ajouter body
if (!isset($fields['body'])) {
  $fieldsHelper = \Drupal::getContainer()->get('search_api.fields_helper');
  $f = $fieldsHelper->createField($index, 'body');
  $f->setType('text');
  $f->setLabel('Description');
  $f->setPropertyPath('body');
  $f->setDatasourceId('entity:commerce_product');
  $index->addField($f);
  echo "✅ body ajouté\n";
}

$index->save();
echo "✅ Index sauvegardé\n";

// Lancer l'indexation complète
$index->reindex();
\Drupal::service('search_api.index.task_manager')->addItemsToIndex($index);
echo "✅ Réindexation lancée\n";

// Forcer l'indexation via cron
\Drupal::service('cron')->run();
echo "✅ Cron exécuté\n";

// Vérifier l'état
$index = $indexStorage->load('products'); // Rechargement
echo "\nÉtat final de l'index :\n";
$tracker = $index->getTrackerInstance();
echo "  Total items  : " . $tracker->getTotalItemsCount() . "\n";
echo "  Indexés      : " . $tracker->getIndexedItemsCount() . "\n";
echo "  En attente   : " . ($tracker->getTotalItemsCount() - $tracker->getIndexedItemsCount()) . "\n";
