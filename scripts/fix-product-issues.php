<?php

// 1. Masquer la date (created) sur le display 'full' des produits
$storage = \Drupal::entityTypeManager()->getStorage('entity_view_display');
$full = $storage->load('commerce_product.default.full');
if ($full) {
  $full->removeComponent('created');
  $full->removeComponent('uid');
  $full->save();
  echo "Display full commerce_product : created + uid masqués ✓\n";
} else {
  echo "Display full non trouvé\n";
}

// Aussi sur les types de bundle courants
foreach (['default', 'odesign_product'] as $bundle) {
  foreach (['default', 'full', 'teaser'] as $mode) {
    $d = $storage->load("commerce_product.$bundle.$mode");
    if ($d) {
      $d->removeComponent('created');
      $d->removeComponent('uid');
      $d->save();
      echo "Display $bundle.$mode : created masqué ✓\n";
    }
  }
}

// 2. Corriger le label du bouton add-to-cart
// Drupal Commerce : le bouton vient de AddToCartForm, configurable via config
// Chercher dans les configs du formulaire
$configs = \Drupal::configFactory()->listAll('commerce_cart');
echo "Commerce cart configs :\n";
foreach ($configs as $c) echo "  $c\n";

// 3. Menu - forcer la reconstruction du menu tree
\Drupal::service('plugin.manager.menu.link')->rebuild();
echo "Menu tree rebuilt ✓\n";

echo "\nDone.\n";
