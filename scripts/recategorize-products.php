<?php
$productStorage = \Drupal::entityTypeManager()->getStorage('commerce_product');
$termStorage    = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$terms = [];
foreach ($termStorage->loadByProperties(['vid' => 'product_category']) as $term) {
  $terms[$term->label()] = $term->id();
}

$rules = [
  // Naissance
  'naissance'     => 'Naissance',
  'bébé'          => 'Naissance',
  'bracelet de naissance' => 'Naissance',
  'annonce grossesse' => 'Naissance',
  'annonce sexe'  => 'Naissance',
  'étapes naissance' => 'Naissance',
  // Mariage
  'mariage'       => 'Mariage',
  'mariés'        => 'Mariage',
  'marié'         => 'Mariage',
  'noces'         => 'Mariage',
  'Elle & Lui'    => 'Mariage',
  'alliances'     => 'Mariage',
  'marque place'  => 'Mariage',
  'numéro de table' => 'Mariage',
  'étiquettes en bois cadeaux' => 'Mariage',
  'pancarte'      => 'Mariage',
  'piste de danse' => 'Mariage',
  'arbre à empreintes' => 'Mariage',
  'livre d\'or'   => 'Mariage',
  // Décoration
  'arbre de vie'  => 'Décoration',
  'calendrier'    => 'Décoration',
  'cadre'         => 'Décoration',
  'tableau'       => 'Décoration',
  'puzzle'        => 'Décoration',
  'vase'          => 'Décoration',
  'magnet'        => 'Décoration',
  'porte clé'     => 'Décoration',
  'support médailles' => 'Décoration',
  'support de cartes' => 'Décoration',
  'support bracelet' => 'Décoration',
];

$stats = array_fill_keys(array_keys($terms) + ['Décoration'], 0);
$products = $productStorage->loadMultiple();
foreach ($products as $product) {
  $title = $product->getTitle();
  $titleLower = mb_strtolower($title);
  $category = 'Décoration';
  foreach ($rules as $keyword => $cat) {
    if (str_contains($titleLower, mb_strtolower($keyword))) { $category = $cat; break; }
  }
  if (isset($terms[$category])) {
    $product->set('field_category', ['target_id' => $terms[$category]]);
    $product->save();
    $stats[$category] = ($stats[$category] ?? 0) + 1;
  }
}
foreach ($stats as $cat => $count) {
  if ($count > 0) echo "  $cat : $count produits\n";
}
echo "\n✅ Recatégorisation terminée\n";
