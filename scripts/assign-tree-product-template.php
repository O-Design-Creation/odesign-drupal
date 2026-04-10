<?php

declare(strict_types=1);

use Drupal\commerce_product\Entity\Product;

$target_product_id = 81;
$legacy_product_id = 30;
$template_id = 'import_9b69e1cb';

$template = \Drupal::entityTypeManager()->getStorage('personalization_template')->load($template_id);
if (!$template) {
  throw new \RuntimeException("Template introuvable: {$template_id}");
}

$target = Product::load($target_product_id);
if (!$target) {
  throw new \RuntimeException("Produit cible introuvable: {$target_product_id}");
}
if (!$target->hasField('field_personalization_template')) {
  throw new \RuntimeException("Le produit {$target_product_id} n'a pas le champ field_personalization_template.");
}

$target->set('field_personalization_template', ['target_id' => $template->id()]);
$target->save();
print "OK: produit {$target_product_id} -> template {$template->id()}\n";

$legacy = Product::load($legacy_product_id);
if ($legacy && $legacy->hasField('field_personalization_template')) {
  $legacy->set('field_personalization_template', []);
  $legacy->save();
  print "OK: produit {$legacy_product_id} template vidé\n";
}

