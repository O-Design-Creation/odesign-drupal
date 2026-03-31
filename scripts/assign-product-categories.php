<?php
// Assigner les catégories aux produits existants + ajouter field_category à l'index
$productStorage = \Drupal::entityTypeManager()->getStorage('commerce_product');
$termStorage    = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

$products = $productStorage->loadMultiple();

// Récupérer les termes
$terms = [];
foreach ($termStorage->loadByProperties(['vid' => 'product_category']) as $term) {
  $terms[$term->label()] = $term->id();
}
echo "Termes disponibles : " . implode(', ', array_keys($terms)) . "\n\n";

// Heuristique de catégorisation sur le titre
$rules = [
  'naissance' => 'Naissance',
  'bébé'      => 'Naissance',
  'mariage'   => 'Mariage',
  'noël'      => 'Noël',
  'noel'      => 'Noël',
  'déco'      => 'Décoration',
  'décor'     => 'Décoration',
  'miroir'    => 'Décoration',
  'cadre'     => 'Décoration',
];

foreach ($products as $product) {
  $title = strtolower($product->getTitle());
  $category = 'Décoration'; // défaut
  foreach ($rules as $keyword => $cat) {
    if (str_contains($title, $keyword)) { $category = $cat; break; }
  }
  if (isset($terms[$category])) {
    $product->set('field_category', ['target_id' => $terms[$category]]);
    $product->save();
    echo "  [" . $product->id() . "] " . $product->getTitle() . " → $category\n";
  }
}

// Ajouter field_category à l'index Search API
$indexStorage = \Drupal::entityTypeManager()->getStorage('search_api_index');
$index = $indexStorage->load('products');
if ($index) {
  $fields = $index->getFields();
  if (!isset($fields['field_category'])) {
    $fieldManager = \Drupal::getContainer()->get('search_api.fields_helper');
    $field = $fieldManager->createField($index, 'field_category');
    $field->setType('integer');
    $field->setLabel('Catégorie');
    $field->setPropertyPath('field_category');
    $field->setDatasourceId('entity:commerce_product');
    $index->addField($field);
    $index->save();
    echo "\n✅ field_category ajouté à l'index Search API\n";
  } else {
    echo "\nℹ️  field_category déjà dans l'index\n";
  }
  // Réindexer
  $index->reindex();
  echo "✅ Réindexation planifiée\n";
}
