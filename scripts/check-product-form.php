<?php
$fd = Drupal::entityTypeManager()->getStorage('entity_form_display')->load('commerce_product.default.default');
$components = array_keys($fd->getComponents());
sort($components);
echo "Components: " . implode(', ', $components) . "\n";
$third_party = $fd->getThirdPartySettings('field_group');
echo "Field groups: " . implode(', ', array_keys($third_party)) . "\n";
foreach ($third_party as $gname => $gdata) {
  echo "  $gname children: " . implode(', ', $gdata['children'] ?? []) . "\n";
}
