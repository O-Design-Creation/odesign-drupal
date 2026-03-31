<?php
$storage = \Drupal::entityTypeManager()->getStorage('entity_view_display');
$displays = $storage->loadMultiple();
foreach ($displays as $display) {
  if (strpos($display->id(), 'commerce_product') !== FALSE) {
    $components = $display->getComponents();
    if (isset($components['created'])) {
      echo $display->id() . ': created VISIBLE' . "\n";
    }
  }
}
echo 'Done' . "\n";
