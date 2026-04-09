<?php
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

// 1. Créer vocabulaire "Catégories produits"
if (!Vocabulary::load('product_category')) {
  Vocabulary::create([
    'vid' => 'product_category',
    'name' => 'Catégories produits',
    'description' => 'Catégories de produits O'Design Création',
  ])->save();
  echo "✅ Vocabulaire 'product_category' créé\n";
} else {
  echo "ℹ️  Vocabulaire déjà existant\n";
}

// 2. Créer quelques termes de démo
$categories = ['Naissance', 'Mariage', 'Décoration', 'Noël', 'Anniversaire'];
foreach ($categories as $cat) {
  $existing = \Drupal::entityTypeManager()->getStorage('taxonomy_term')
    ->loadByProperties(['name' => $cat, 'vid' => 'product_category']);
  if (empty($existing)) {
    Term::create(['name' => $cat, 'vid' => 'product_category'])->save();
    echo "  ✅ Terme : $cat\n";
  }
}

// 3. Créer FieldStorage si inexistant
if (!FieldStorageConfig::loadByName('commerce_product', 'field_category')) {
  FieldStorageConfig::create([
    'field_name'  => 'field_category',
    'entity_type' => 'commerce_product',
    'type'        => 'entity_reference',
    'cardinality' => 1,
    'settings'    => ['target_type' => 'taxonomy_term'],
  ])->save();
  echo "✅ FieldStorage 'field_category' créé\n";
} else {
  echo "ℹ️  FieldStorage déjà existant\n";
}

// 4. Attacher au bundle "default"
if (!FieldConfig::loadByName('commerce_product', 'default', 'field_category')) {
  FieldConfig::create([
    'field_name'    => 'field_category',
    'entity_type'   => 'commerce_product',
    'bundle'        => 'default',
    'label'         => 'Catégorie',
    'required'      => FALSE,
    'settings'      => [
      'handler'          => 'default:taxonomy_term',
      'handler_settings' => ['target_bundles' => ['product_category' => 'product_category']],
    ],
  ])->save();
  echo "✅ FieldConfig 'field_category' attaché au bundle 'default'\n";
} else {
  echo "ℹ️  FieldConfig déjà attaché\n";
}

echo "\n✅ Catégories prêtes.\n";
