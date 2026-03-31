<?php
// Le bouton add-to-cart Drupal Commerce est géré via le form alter
// Dans Drupal Commerce, le texte "Add to cart" vient de AddToCartForm::buildForm
// On peut le surcharger via un hook_form_alter dans le module custom, ou via une traduction FR

// Vérifier la traduction actuelle
$langcode = 'fr';
$storage = \Drupal::service('locale.storage');
// Chercher la traduction de "Add to cart"
$result = \Drupal::database()->query(
  "SELECT s.source, t.translation FROM {locales_source} s
   LEFT JOIN {locales_target} t ON s.lid = t.lid AND t.language = :lang
   WHERE s.source LIKE '%add to cart%' OR s.source LIKE '%Add to cart%'",
  [':lang' => $langcode]
)->fetchAll();
foreach ($result as $r) {
  echo "Source: {$r->source}\nTrad: {$r->translation}\n---\n";
}

// Vérifier "Save" translations aussi (le bouton dit "Enregistrer la commande")
$result2 = \Drupal::database()->query(
  "SELECT s.source, t.translation FROM {locales_source} s
   LEFT JOIN {locales_target} t ON s.lid = t.lid AND t.language = :lang
   WHERE (s.source LIKE '%Enregistrer%' OR t.translation LIKE '%Enregistrer%') LIMIT 5",
  [':lang' => $langcode]
)->fetchAll();
foreach ($result2 as $r) {
  echo "Source: {$r->source} | Trad: {$r->translation}\n";
}
