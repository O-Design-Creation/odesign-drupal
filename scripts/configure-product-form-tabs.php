<?php
/**
 * Script drush scr : configure les onglets field_group sur le formulaire produit.
 *
 * Crée 4 onglets métier sur le form display commerce_product.default.default :
 *   - Informations principales (titre, statut, boutique, variantes/prix)
 *   - Images (field_product_images)
 *   - Personnalisation (champs poc si présents)
 *   - SEO & visibilité (metatag si présent)
 *
 * Usage: ddev drush scr scripts/configure-product-form-tabs.php
 */

use Drupal\field_group\Form\FieldGroupAddForm;

$entity_type = 'commerce_product';
$bundle      = 'default';
$context     = 'form';
$mode        = 'default';

$form_display = Drupal::entityTypeManager()
  ->getStorage('entity_form_display')
  ->load("$entity_type.$bundle.$mode");

if (!$form_display) {
  echo "ERROR: form display $entity_type.$bundle.$mode not found.\n";
  return;
}

// Définition des onglets.
$tabs = [
  'group_produit_principal' => [
    'label'       => '📋 Informations principales',
    'weight'      => 0,
    'children'    => ['title', 'status', 'stores', 'variations'],
    'format_type' => 'tab',
    'format_settings' => [
      'label'            => '📋 Informations principales',
      'show_empty_fields' => FALSE,
      'id'               => '',
      'classes'          => 'group-produit-principal',
      'description'      => '',
      'required_fields'  => TRUE,
    ],
  ],
  'group_produit_images' => [
    'label'       => '🖼️ Images',
    'weight'      => 1,
    'children'    => ['field_product_images'],
    'format_type' => 'tab',
    'format_settings' => [
      'label'            => '🖼️ Images',
      'show_empty_fields' => FALSE,
      'id'               => '',
      'classes'          => 'group-produit-images',
      'description'      => '',
      'required_fields'  => FALSE,
    ],
  ],
  'group_produit_personnalisation' => [
    'label'       => '✏️ Personnalisation',
    'weight'      => 2,
    'children'    => ['field_poc_text', 'field_poc_finish', 'field_poc_need_upload', 'field_poc_tree_names', 'field_poc_tree_wall_mount'],
    'format_type' => 'tab',
    'format_settings' => [
      'label'            => '✏️ Personnalisation',
      'show_empty_fields' => FALSE,
      'id'               => '',
      'classes'          => 'group-produit-personnalisation',
      'description'      => '',
      'required_fields'  => FALSE,
    ],
  ],
];

// Créer le wrapper tabs (conteneur des onglets).
$wrapper = (object) [
  'group_name'       => 'group_produit_tabs',
  'entity_type'      => $entity_type,
  'bundle'           => $bundle,
  'mode'             => $mode,
  'context'          => $context,
  'children'         => array_keys($tabs),
  'parent_name'      => '',
  'weight'           => -20,
  'label'            => 'Onglets produit',
  'format_type'      => 'tabs',
  'format_settings'  => [
    'show_empty_fields' => FALSE,
    'id'               => '',
    'classes'          => 'group-produit-tabs',
    'direction'        => 'horizontal',
  ],
  'region'           => 'content',
];

field_group_group_save($wrapper);
echo "Tabs wrapper 'group_produit_tabs' created.\n";

// Créer chaque onglet.
foreach ($tabs as $group_name => $tab) {
  // Filtrer les enfants qui existent réellement sur le form display.
  $existing_children = [];
  foreach ($tab['children'] as $child) {
    $component = $form_display->getComponent($child);
    if ($component !== NULL) {
      $existing_children[] = $child;
    }
    // Vérifier aussi dans les groupes field_group existants.
  }

  if (empty($existing_children)) {
    echo "Skipping '$group_name' — no existing children found.\n";
    continue;
  }

  $group = (object) [
    'group_name'      => $group_name,
    'entity_type'     => $entity_type,
    'bundle'          => $bundle,
    'mode'            => $mode,
    'context'         => $context,
    'children'        => $existing_children,
    'parent_name'     => 'group_produit_tabs',
    'weight'          => $tab['weight'],
    'label'           => $tab['label'],
    'format_type'     => $tab['format_type'],
    'format_settings' => $tab['format_settings'],
    'region'          => 'content',
  ];

  field_group_group_save($group);
  echo "Tab '$group_name' created with children: " . implode(', ', $existing_children) . "\n";
}

echo "Done. Clear cache: ddev drush cr\n";
