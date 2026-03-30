<?php
/**
 * Script : finalise la config des onglets produit.
 * - Ajoute variations + body à l'onglet principal
 * - Masque les champs techniques (uid, langcode, created)
 * - Ajoute un onglet Description
 */

$entity_type = 'commerce_product';
$bundle      = 'default';
$mode        = 'default';

$fd = Drupal::entityTypeManager()
  ->getStorage('entity_form_display')
  ->load("$entity_type.$bundle.$mode");

// 1. Masquer les champs techniques inutiles pour le gestionnaire.
$technical_fields = ['uid', 'langcode', 'created', 'path'];
foreach ($technical_fields as $field) {
  if ($fd->getComponent($field)) {
    $fd->removeComponent($field);
    echo "Masked technical field: $field\n";
  }
}

// 2. Récupérer les groups field_group existants.
$groups = $fd->getThirdPartySettings('field_group');

// 3. Ajouter variations + body à l'onglet principal s'ils existent.
$principal_group = $groups['group_produit_principal'] ?? NULL;
if ($principal_group) {
  $children = $principal_group['children'];
  // variations : widget inline sur le product form (toujours présent dans Commerce)
  if (!in_array('variations', $children)) {
    $children[] = 'variations';
  }
  $groups['group_produit_principal']['children'] = $children;
  echo "variations added to group_produit_principal\n";
}

// 4. Ajouter un onglet Description (body) si body existe.
if ($fd->getComponent('body') && !isset($groups['group_produit_description'])) {
  $groups['group_produit_description'] = [
    'label'       => '📝 Description',
    'weight'      => 3,
    'children'    => ['body'],
    'parent_name' => 'group_produit_tabs',
    'format_type' => 'tab',
    'format_settings' => [
      'label'            => '📝 Description',
      'show_empty_fields' => FALSE,
      'id'               => '',
      'classes'          => 'group-produit-description',
      'description'      => '',
      'required_fields'  => FALSE,
    ],
    'region' => 'content',
    'context' => 'form',
    'entity_type' => $entity_type,
    'bundle' => $bundle,
    'mode' => $mode,
  ];
  // Ajouter au wrapper tabs.
  if (!in_array('group_produit_description', $groups['group_produit_tabs']['children'])) {
    $groups['group_produit_tabs']['children'][] = 'group_produit_description';
  }
  echo "Tab 'group_produit_description' added with: body\n";
}

// 5. Sauvegarder tous les groupes mis à jour.
foreach ($groups as $gname => $gdata) {
  $fd->setThirdPartySetting('field_group', $gname, $gdata);
}
$fd->save();
echo "Form display saved.\n";

// 6. Ajouter description d'aide sur body (si pas déjà fait).
echo "Done.\n";
