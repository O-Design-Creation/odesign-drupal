<?php
$storage = \Drupal::entityTypeManager()->getStorage('entity_view_display');
$displays = $storage->loadMultiple();
foreach ($displays as $display) {
  if (strpos($display->id(), 'media.') !== FALSE || strpos($display->id(), 'image') !== FALSE) {
    $components = $display->getComponents();
    $hidden = $display->get('hidden');
    if (isset($components['created']) || (is_array($hidden) && isset($hidden['created']) && empty($hidden['created']))) {
      echo $display->id() . ': created present in components' . "\n";
      echo '  visible: ' . json_encode(array_keys($components)) . "\n";
    }
  }
}
echo 'Done' . "\n";
