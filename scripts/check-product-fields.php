<?php
$entityTypeManager = \Drupal::entityTypeManager();
$fieldDefinitions = \Drupal::service('entity_field.manager')
  ->getFieldDefinitions('commerce_product', 'default');

foreach ($fieldDefinitions as $field_name => $definition) {
  $type = $definition->getType();
  $label = $definition->getLabel();
  echo "  $field_name ($type) : $label\n";
}
