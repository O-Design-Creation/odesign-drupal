<?php
$configs = [
  'views.view.recherche_produits',
  'facets.facet.life_occasion_facet',
  'facets.facet.category_recherche_facet',
  'search_api_autocomplete.search.products',
];
foreach ($configs as $config_name) {
  $file = '/var/www/html/config/sync/' . $config_name . '.yml';
  if (file_exists($file)) {
    $data = \Drupal\Core\Serialization\Yaml::decode(file_get_contents($file));
    unset($data['uuid']);
    \Drupal::service('config.factory')->getEditable($config_name)->setData($data)->save();
    echo "Imported: $config_name\n";
  } else {
    echo "MISSING: $config_name\n";
  }
}
