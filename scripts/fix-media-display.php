<?php
$storage = \Drupal::entityTypeManager()->getStorage('entity_view_display');
$display = $storage->load('media.product_image.default');
if (!$display) {
  echo "Display not found!\n";
  return;
}
// Hide created, uid, thumbnail — keep only field_media_image
$hidden = (array) $display->get('hidden');
foreach (['created', 'uid', 'thumbnail'] as $field) {
  $display->removeComponent($field);
  $hidden[$field] = TRUE;
}
$display->set('hidden', $hidden);
$display->save();
\Drupal::service('cache.render')->invalidateAll();
echo "Media product_image.default updated: created/uid/thumbnail hidden\n";
// Verify
$display2 = $storage->load('media.product_image.default');
echo "VISIBLE: " . json_encode(array_keys($display2->getComponents())) . "\n";
