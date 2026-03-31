<?php
$zones = \Drupal::entityTypeManager()->getStorage('commerce_tax_type')->loadMultiple();
foreach ($zones as $id => $zone) {
  echo $id . ' : ' . $zone->label() . ' (plugin: ' . $zone->getPluginId() . ')' . PHP_EOL;
}
if (empty($zones)) {
  echo "Aucun type de taxe configuré\n";
}
