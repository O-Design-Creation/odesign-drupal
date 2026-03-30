<?php
/**
 * Script drush scr : crée field_product_images sur commerce_product.
 * Usage: ddev drush scr scripts/create-product-images-field.php
 */

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

// Storage : entity_reference vers media, cardinality illimitée.
if (!FieldStorageConfig::loadByName('commerce_product', 'field_product_images')) {
  $storage = FieldStorageConfig::create([
    'field_name' => 'field_product_images',
    'entity_type' => 'commerce_product',
    'type' => 'entity_reference',
    'cardinality' => -1,
    'settings' => ['target_type' => 'media'],
  ]);
  $storage->save();
  echo "field_product_images storage created\n";
}
else {
  echo "field_product_images storage already exists\n";
}

// Instance sur le bundle 'default' (produit standard).
if (!FieldConfig::loadByName('commerce_product', 'default', 'field_product_images')) {
  $field = FieldConfig::create([
    'field_name' => 'field_product_images',
    'entity_type' => 'commerce_product',
    'bundle' => 'default',
    'label' => 'Images produit',
    'required' => FALSE,
    'settings' => [
      'handler' => 'default:media',
      'handler_settings' => [
        'target_bundles' => ['product_image' => 'product_image'],
      ],
    ],
  ]);
  $field->save();
  echo "field_product_images field on product/default created\n";
}
else {
  echo "field_product_images field already exists\n";
}

echo "Done.\n";
