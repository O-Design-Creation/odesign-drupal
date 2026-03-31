<?php

/**
 * Adds field_image to article content type for blog card thumbnails.
 * Run: ddev drush php:script ../scripts/add-article-image-field.php
 */

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Entity\Entity\EntityViewDisplay;

// 1. Create field storage if not exists.
if (!FieldStorageConfig::loadByName('node', 'field_image')) {
  FieldStorageConfig::create([
    'field_name' => 'field_image',
    'entity_type' => 'node',
    'type' => 'image',
    'cardinality' => 1,
    'settings' => [
      'uri_scheme' => 'public',
      'default_image' => [
        'uuid' => '',
        'alt' => '',
        'title' => '',
        'width' => NULL,
        'height' => NULL,
      ],
    ],
  ])->save();
  echo "✅ field_image storage créé\n";
} else {
  echo "ℹ️  field_image storage existe déjà\n";
}

// 2. Create field instance on article if not exists.
if (!FieldConfig::loadByName('node', 'article', 'field_image')) {
  FieldConfig::create([
    'field_name' => 'field_image',
    'entity_type' => 'node',
    'bundle' => 'article',
    'label' => 'Image de couverture',
    'required' => FALSE,
    'settings' => [
      'file_extensions' => 'png gif jpg jpeg webp',
      'file_directory' => 'articles',
      'max_filesize' => '2 MB',
      'alt_field' => TRUE,
      'alt_field_required' => FALSE,
      'title_field' => FALSE,
      'max_resolution' => '',
      'min_resolution' => '',
      'default_image' => [
        'uuid' => '',
        'alt' => '',
        'title' => '',
        'width' => NULL,
        'height' => NULL,
      ],
    ],
    'description' => 'Image affichée en vignette dans la liste blog et en en-tête de l\'article.',
  ])->save();
  echo "✅ field_image instance créée sur article\n";
} else {
  echo "ℹ️  field_image existe déjà sur article\n";
}

// 3. Add to default display.
$default_display = EntityViewDisplay::load('node.article.default');
if ($default_display && !$default_display->getComponent('field_image')) {
  $default_display->setComponent('field_image', [
    'type' => 'image',
    'label' => 'hidden',
    'settings' => [
      'image_style' => 'large',
      'image_link' => '',
    ],
    'weight' => 0,
    'region' => 'content',
  ])->save();
  echo "✅ field_image ajouté au display default\n";
}

// 4. Add to teaser display (create if needed).
$teaser_display = EntityViewDisplay::load('node.article.teaser');
if (!$teaser_display) {
  $teaser_display = EntityViewDisplay::create([
    'targetEntityType' => 'node',
    'bundle' => 'article',
    'mode' => 'teaser',
    'status' => TRUE,
  ]);
}
if (!$teaser_display->getComponent('field_image')) {
  $teaser_display->setComponent('field_image', [
    'type' => 'image',
    'label' => 'hidden',
    'settings' => [
      'image_style' => 'medium',
      'image_link' => 'content',
    ],
    'weight' => 0,
    'region' => 'content',
  ]);
}
if (!$teaser_display->getComponent('body')) {
  $teaser_display->setComponent('body', [
    'type' => 'text_summary_or_trimmed',
    'label' => 'hidden',
    'settings' => ['trim_length' => 280],
    'weight' => 1,
    'region' => 'content',
  ]);
}
$teaser_display->save();
echo "✅ display teaser mis à jour\n";

// 5. Add to form display.
$form_display = \Drupal::entityTypeManager()
  ->getStorage('entity_form_display')
  ->load('node.article.default');
if ($form_display && !$form_display->getComponent('field_image')) {
  $form_display->setComponent('field_image', [
    'type' => 'image_image',
    'weight' => 2,
    'settings' => [
      'preview_image_style' => 'thumbnail',
      'progress_indicator' => 'throbber',
    ],
    'region' => 'content',
  ])->save();
  echo "✅ field_image ajouté au formulaire article\n";
}

echo "\n✅ Script terminé. Lancez 'drush cex -y' pour exporter la config.\n";
