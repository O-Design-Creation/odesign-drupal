<?php
// Check extra fields for commerce_product display
$extra = \Drupal::service('entity_field.manager')->getExtraFields('commerce_product', 'default');
echo "EXTRA FORM: " . json_encode(array_keys($extra['form'] ?? [])) . "\n";
echo "EXTRA DISPLAY: " . json_encode(array_keys($extra['display'] ?? [])) . "\n";
