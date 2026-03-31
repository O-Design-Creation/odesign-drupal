<?php
// Masquer le champ "created" sur le display mode teaser de commerce_product
$storage = \Drupal::entityTypeManager()->getStorage('entity_view_display');

// Charger le display teaser du produit
$display = $storage->load('commerce_product.default.teaser');
if (!$display) {
  echo "Display mode teaser introuvable, tentative de création...\n";
  $display = $storage->create([
    'targetEntityType' => 'commerce_product',
    'bundle' => 'default',
    'mode' => 'teaser',
    'status' => TRUE,
  ]);
}

// Masquer le champ created (date de création)
$display->removeComponent('created');

// Masquer aussi uid (auteur) s'il est affiché
$display->removeComponent('uid');

// S'assurer que les images et prix sont bien configurés
$display->save();
echo "Display mode teaser commerce_product : created et uid masqués.\n";

// Pareil pour le display full
$full = $storage->load('commerce_product.default.full');
if ($full) {
  $full->removeComponent('created');
  $full->removeComponent('uid');
  $full->save();
  echo "Display mode full commerce_product : created et uid masqués.\n";
}

echo "Done.\n";
