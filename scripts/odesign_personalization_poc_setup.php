<?php

use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

$fields = [
  'field_poc_text' => [
    'type' => 'string',
    'label' => 'Texte de personnalisation',
    'settings' => ['max_length' => 255],
  ],
  'field_poc_finish' => [
    'type' => 'list_string',
    'label' => 'Finition',
    'settings' => [
      'allowed_values' => [
        'standard' => 'Standard',
        'premium' => 'Premium (+7 EUR)',
      ],
    ],
  ],
  'field_poc_need_upload' => [
    'type' => 'boolean',
    'label' => 'Ajouter un fichier client',
    'settings' => ['on_label' => 'Oui', 'off_label' => 'Non'],
  ],
  'field_poc_upload' => [
    'type' => 'file',
    'label' => 'Fichier de personnalisation',
    'settings' => ['uri_scheme' => 'public', 'file_extensions' => 'png jpg jpeg pdf'],
  ],
  'field_poc_tree_names' => [
    'type' => 'string_long',
    'label' => 'Prénoms de l’arbre',
  ],
  'field_poc_tree_layout' => [
    'type' => 'string_long',
    'label' => 'Disposition des prénoms',
  ],
  'field_poc_tree_wall_mount' => [
    'type' => 'boolean',
    'label' => 'Ajouter une attache murale',
    'settings' => ['on_label' => 'Oui', 'off_label' => 'Non'],
  ],
];

foreach ($fields as $field_name => $definition) {
  $storage = FieldStorageConfig::loadByName('commerce_order_item', $field_name);
  if (!$storage) {
    $storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'commerce_order_item',
      'type' => $definition['type'],
      'settings' => $definition['settings'] ?? [],
      'cardinality' => 1,
      'translatable' => FALSE,
    ]);
    $storage->save();
  }

  $field = FieldConfig::loadByName('commerce_order_item', 'default', $field_name);
  if (!$field) {
    $field = FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'commerce_order_item',
      'bundle' => 'default',
      'label' => $definition['label'],
      'required' => FALSE,
      'settings' => $definition['settings'] ?? [],
    ]);
    $field->save();
  }
}

$form_display = EntityFormDisplay::load('commerce_order_item.default.add_to_cart') ?: EntityFormDisplay::create([
  'targetEntityType' => 'commerce_order_item',
  'bundle' => 'default',
  'mode' => 'add_to_cart',
  'status' => TRUE,
]);
$form_display->setComponent('field_poc_text', [
  'type' => 'string_textfield',
  'weight' => 10,
  'settings' => ['size' => 60, 'placeholder' => 'Votre texte'],
]);
$form_display->setComponent('field_poc_finish', [
  'type' => 'options_select',
  'weight' => 11,
]);
$form_display->setComponent('field_poc_need_upload', [
  'type' => 'boolean_checkbox',
  'weight' => 12,
  'settings' => ['display_label' => TRUE],
]);
$form_display->setComponent('field_poc_upload', [
  'type' => 'file_generic',
  'weight' => 13,
  'settings' => ['progress_indicator' => 'throbber'],
]);
$form_display->setComponent('field_poc_tree_names', [
  'type' => 'string_textarea',
  'weight' => 30,
  'settings' => ['rows' => 4, 'placeholder' => 'ex : Marie, Paul, Jeanne'],
]);
$form_display->setComponent('field_poc_tree_wall_mount', [
  'type' => 'boolean_checkbox',
  'weight' => 31,
  'settings' => ['display_label' => TRUE],
]);
$form_display->setComponent('field_poc_tree_layout', [
  'type' => 'string_textarea',
  'weight' => 32,
  'settings' => ['rows' => 3],
]);
$form_display->save();

$admin_form_display = EntityFormDisplay::load('commerce_order_item.default.default') ?: EntityFormDisplay::create([
  'targetEntityType' => 'commerce_order_item',
  'bundle' => 'default',
  'mode' => 'default',
  'status' => TRUE,
]);
$admin_form_display->setComponent('field_poc_text', [
  'type' => 'string_textfield',
  'weight' => 10,
  'settings' => ['size' => 60, 'placeholder' => 'Votre texte'],
]);
$admin_form_display->setComponent('field_poc_finish', [
  'type' => 'options_select',
  'weight' => 11,
]);
$admin_form_display->setComponent('field_poc_need_upload', [
  'type' => 'boolean_checkbox',
  'weight' => 12,
  'settings' => ['display_label' => TRUE],
]);
$admin_form_display->setComponent('field_poc_upload', [
  'type' => 'file_generic',
  'weight' => 13,
  'settings' => ['progress_indicator' => 'throbber'],
]);
$admin_form_display->setComponent('field_poc_tree_names', [
  'type' => 'string_textarea',
  'weight' => 30,
  'settings' => ['rows' => 4],
]);
$admin_form_display->setComponent('field_poc_tree_wall_mount', [
  'type' => 'boolean_checkbox',
  'weight' => 31,
  'settings' => ['display_label' => TRUE],
]);
$admin_form_display->setComponent('field_poc_tree_layout', [
  'type' => 'string_textarea',
  'weight' => 32,
  'settings' => ['rows' => 4],
]);
$admin_form_display->save();

$view_display = EntityViewDisplay::load('commerce_order_item.default.default') ?: EntityViewDisplay::create([
  'targetEntityType' => 'commerce_order_item',
  'bundle' => 'default',
  'mode' => 'default',
  'status' => TRUE,
]);
$view_display->setComponent('field_poc_text', [
  'type' => 'string',
  'label' => 'above',
  'weight' => 20,
]);
$view_display->setComponent('field_poc_finish', [
  'type' => 'list_default',
  'label' => 'above',
  'weight' => 21,
]);
$view_display->setComponent('field_poc_need_upload', [
  'type' => 'boolean',
  'label' => 'above',
  'weight' => 22,
  'settings' => ['format' => 'default'],
]);
$view_display->setComponent('field_poc_upload', [
  'type' => 'file_default',
  'label' => 'above',
  'weight' => 23,
]);
$view_display->setComponent('field_poc_tree_names', [
  'type' => 'basic_string',
  'label' => 'above',
  'weight' => 30,
]);
$view_display->setComponent('field_poc_tree_wall_mount', [
  'type' => 'boolean',
  'label' => 'above',
  'weight' => 31,
  'settings' => ['format' => 'default'],
]);
$view_display->setComponent('field_poc_tree_layout', [
  'type' => 'basic_string',
  'label' => 'above',
  'weight' => 32,
]);
$view_display->save();

$store_storage = \Drupal::entityTypeManager()->getStorage('commerce_store');
$stores = $store_storage->loadMultiple();
$store = reset($stores);
if (!$store) {
  throw new \RuntimeException('No commerce store available for the PoC product.');
}

$variation_storage = \Drupal::entityTypeManager()->getStorage('commerce_product_variation');
$existing = $variation_storage->loadByProperties(['sku' => 'POC-PERSO-001']);
$variation = $existing ? reset($existing) : NULL;
if (!$variation) {
  $variation = ProductVariation::create([
    'type' => 'default',
    'sku' => 'POC-PERSO-001',
    'title' => 'Produit test personnalisation',
    'price' => ['number' => '25.00', 'currency_code' => 'EUR'],
    'status' => 1,
  ]);
  $variation->save();
}

$product_storage = \Drupal::entityTypeManager()->getStorage('commerce_product');
$existing_product = $product_storage->loadByProperties(['title' => 'Produit test personnalisation']);
$product = $existing_product ? reset($existing_product) : NULL;
if (!$product) {
  $product = Product::create([
    'type' => 'default',
    'title' => 'Produit test personnalisation',
    'stores' => [$store],
    'variations' => [$variation],
    'status' => 1,
  ]);
  $product->save();
}

if (!$variation->getProductId() || $variation->getProductId() != $product->id()) {
  $variation->set('product_id', $product->id());
  $variation->save();
}

$tree_variation_existing = $variation_storage->loadByProperties(['sku' => 'POC-TREE-001']);
$tree_variation = $tree_variation_existing ? reset($tree_variation_existing) : NULL;
if (!$tree_variation) {
  $tree_variation = ProductVariation::create([
    'type' => 'default',
    'sku' => 'POC-TREE-001',
    'title' => 'Arbre de vie PoC',
    'price' => ['number' => '39.90', 'currency_code' => 'EUR'],
    'status' => 1,
  ]);
  $tree_variation->save();
}

$existing_tree_product = $product_storage->loadByProperties(['title' => 'Arbre de vie PoC']);
$tree_product = $existing_tree_product ? reset($existing_tree_product) : NULL;
if (!$tree_product) {
  $tree_product = Product::create([
    'type' => 'default',
    'title' => 'Arbre de vie PoC',
    'stores' => [$store],
    'variations' => [$tree_variation],
    'status' => 1,
    'body' => [
      'value' => '<p>PoC exploratoire : placement guidé des prénoms sur un arbre de vie.</p>',
      'format' => 'basic_html',
    ],
  ]);
  $tree_product->save();
}

if (!$tree_variation->getProductId() || $tree_variation->getProductId() != $tree_product->id()) {
  $tree_variation->set('product_id', $tree_product->id());
  $tree_variation->save();
}

print "PoC personalization setup complete.\n";
print "Product ID: {$product->id()}\n";
print "Variation ID: {$variation->id()}\n";
print "Tree Product ID: {$tree_product->id()}\n";
print "Tree Variation ID: {$tree_variation->id()}\n";
