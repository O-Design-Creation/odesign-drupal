<?php
// Vérifie que les champs taxonomie sont bien dans les form displays
$storage = \Drupal::entityTypeManager()->getStorage('entity_form_display');

$checks = [
  ['commerce_product.default.default', ['field_category']],
  ['node.article.default',             ['field_tags', 'field_blog_category']],
];
foreach ($checks as [$displayId, $fields]) {
  $display = $storage->load($displayId);
  if (!$display) { echo "❌ Display '$displayId' introuvable\n"; continue; }
  $components = $display->getComponents();
  echo "Display : $displayId\n";
  foreach ($fields as $field) {
    if (isset($components[$field])) {
      echo "  ✅ $field (widget: " . $components[$field]['type'] . ")\n";
    } else {
      echo "  ❌ $field ABSENT\n";
    }
  }
}
