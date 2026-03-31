<?php
/**
 * Ajoute filtre texte + filtre catégorie (select) à la vue catalogue.
 * v3 — plugin_id correct pour taxonomy_index_tid avec vid configuré.
 */
$viewStorage = \Drupal::entityTypeManager()->getStorage('view');
$view = $viewStorage->load('catalogue');
if (!$view) { echo "❌ Vue 'catalogue' introuvable\n"; exit(1); }

$display = $view->getDisplay('default');
$filters = $display['display_options']['filters'] ?? [];

// 1. Filtre texte "Rechercher"
if (!isset($filters['title'])) {
  $filters['title'] = [
    'id'        => 'title',
    'table'     => 'commerce_product_field_data',
    'field'     => 'title',
    'plugin_id' => 'string',
    'operator'  => 'contains',
    'value'     => '',
    'group'     => 1,
    'exposed'   => TRUE,
    'expose'    => [
      'operator_id'              => 'title_op',
      'label'                    => 'Rechercher',
      'description'              => '',
      'use_operator'             => FALSE,
      'operator'                 => 'title_op',
      'operator_limit_selection' => FALSE,
      'operator_list'            => [],
      'identifier'               => 'recherche',
      'required'                 => FALSE,
      'remember'                 => FALSE,
      'multiple'                 => FALSE,
      'remember_roles'           => ['authenticated' => 'authenticated'],
    ],
    'is_grouped' => FALSE,
  ];
  echo "✅ Filtre texte ajouté\n";
}

// 2. Filtre catégorie via taxonomy_index_tid (avec vocabulary configuré)
if (!isset($filters['field_category_target_id'])) {
  $filters['field_category_target_id'] = [
    'id'           => 'field_category_target_id',
    'table'        => 'commerce_product__field_category',
    'field'        => 'field_category_target_id',
    'relationship' => 'none',
    'plugin_id'    => 'taxonomy_index_tid',
    'operator'     => 'or',
    'value'        => [],
    'type'         => 'select',
    'hierarchy'    => FALSE,
    'error_message'=> TRUE,
    'vid'          => 'product_category',
    'group'        => 1,
    'exposed'      => TRUE,
    'expose'       => [
      'operator_id'              => 'field_category_target_id_op',
      'label'                    => 'Catégorie',
      'description'              => '',
      'use_operator'             => FALSE,
      'operator'                 => 'field_category_target_id_op',
      'operator_limit_selection' => FALSE,
      'operator_list'            => [],
      'identifier'               => 'categorie',
      'required'                 => FALSE,
      'remember'                 => FALSE,
      'multiple'                 => FALSE,
      'remember_roles'           => ['authenticated' => 'authenticated'],
      'reduce'                   => FALSE,
    ],
    'is_grouped' => FALSE,
  ];
  echo "✅ Filtre catégorie ajouté\n";
}

// 3. Formulaire exposé
$display['display_options']['filters']      = $filters;
$display['display_options']['exposed_form'] = [
  'type'    => 'basic',
  'options' => [
    'submit_button'       => 'Filtrer',
    'reset_button'        => TRUE,
    'reset_button_label'  => 'Réinitialiser',
    'exposed_sorts_label' => 'Trier par',
    'expose_sort_order'   => TRUE,
    'sort_asc_label'      => 'Asc',
    'sort_desc_label'     => 'Desc',
  ],
];

// On remet le display modifié dans la vue
$displays = $view->get('display');
$displays['default'] = $display;
$view->set('display', $displays);

try {
  $view->save();
  echo "✅ Vue catalogue sauvegardée\n";
} catch (\Exception $e) {
  echo "❌ Erreur save : " . $e->getMessage() . "\n";
  // Essai sans le filtre taxonomy (fallback numeric)
  $filters['field_category_target_id']['plugin_id'] = 'numeric';
  unset($filters['field_category_target_id']['vid']);
  unset($filters['field_category_target_id']['hierarchy']);
  unset($filters['field_category_target_id']['type']);
  $displays['default']['display_options']['filters'] = $filters;
  $view->set('display', $displays);
  $view->save();
  echo "✅ Vue sauvegardée avec filtre numeric (fallback)\n";
}
