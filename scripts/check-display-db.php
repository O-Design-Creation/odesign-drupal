<?php
$display = \Drupal::entityTypeManager()->getStorage('entity_view_display')->load('commerce_product.default.default');
$hidden = $display ? $display->get('hidden') : [];
$components = $display ? $display->getComponents() : [];
echo "HIDDEN: " . json_encode(array_keys($hidden)) . "\n";
echo "VISIBLE: " . json_encode(array_keys($components)) . "\n";
