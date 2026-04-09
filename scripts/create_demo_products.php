<?php
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;

$entity_manager = \Drupal::entityTypeManager();
$store_ids = $entity_manager->getStorage('commerce_store')->getQuery()->accessCheck(FALSE)->execute();
$store_id = reset($store_ids);

$demo_products = [
  [
    'title' => 'Affiche aquarelle - Paysage nordique',
    'body'  => '<p>Impression sur papier Fine Art 300 g/m2. Encres pigmentaires longue duree, rendu texture fidele a l\'original.</p>',
    'variations' => [
      ['label' => 'Format A4', 'sku' => 'AFFI-NOR-A4', 'price' => '12.90'],
      ['label' => 'Format A3', 'sku' => 'AFFI-NOR-A3', 'price' => '19.90'],
      ['label' => 'Format A2', 'sku' => 'AFFI-NOR-A2', 'price' => '29.90'],
    ],
  ],
  [
    'title' => 'Bracelet grave - Prenom personnalise',
    'body'  => '<p>Bracelet en laiton dore a l\'or fin. Gravure laser de votre prenom (20 caracteres max). Livre dans un ecrin kraft.</p>',
    'variations' => [
      ['label' => 'Taille S (15 cm)', 'sku' => 'BRAC-S', 'price' => '24.90'],
      ['label' => 'Taille M (17 cm)', 'sku' => 'BRAC-M', 'price' => '24.90'],
      ['label' => 'Taille L (19 cm)', 'sku' => 'BRAC-L', 'price' => '24.90'],
    ],
  ],
  [
    'title' => 'Poster typographie - Citation minimaliste',
    'body'  => '<p>Impression numerique sur papier satine 250 g/m2. Design exclusif O'Design Création. Sans cadre.</p>',
    'variations' => [
      ['label' => 'Format 30x40 cm', 'sku' => 'POST-TYP-3040', 'price' => '14.90'],
      ['label' => 'Format 40x50 cm', 'sku' => 'POST-TYP-4050', 'price' => '22.90'],
    ],
  ],
  [
    'title' => 'Carte de voeux illustree - Lot de 5',
    'body'  => '<p>5 cartes doubles (14,8 x 10,5 cm) avec enveloppes. Illustrations originales, impression offset. Papier recycle 350 g/m2.</p>',
    'variations' => [
      ['label' => 'Motif Foret',     'sku' => 'CARTE-FORET', 'price' => '8.90'],
      ['label' => 'Motif Botanique', 'sku' => 'CARTE-BOTA',  'price' => '8.90'],
      ['label' => 'Motif Geometrie', 'sku' => 'CARTE-GEO',   'price' => '8.90'],
    ],
  ],
  [
    'title' => 'Tote bag serigraphie - Collection Studio',
    'body'  => '<p>Sac en coton naturel non blanchi (150 g/m2). Serigraphie 1 couleur. 38 x 42 cm. Lavable a 30 degres.</p>',
    'variations' => [
      ['label' => 'Motif Arc-en-ciel', 'sku' => 'TOTE-ARC', 'price' => '18.00'],
      ['label' => 'Motif Lignes',      'sku' => 'TOTE-LIG', 'price' => '18.00'],
    ],
  ],
  [
    'title' => 'Sticker pack - 12 illustrations',
    'body'  => '<p>12 stickers vinyle resistants a l\'eau. Format moyen (5-8 cm). Planche 21 x 29,7 cm.</p>',
    'variations' => [
      ['label' => 'Theme Nature',  'sku' => 'STICK-NAT', 'price' => '6.90'],
      ['label' => 'Theme Voyage',  'sku' => 'STICK-VOY', 'price' => '6.90'],
      ['label' => 'Theme Animaux', 'sku' => 'STICK-ANI', 'price' => '6.90'],
    ],
  ],
];

$created = 0;
$skipped = 0;

foreach ($demo_products as $data) {
  $existing = $entity_manager->getStorage('commerce_product')
    ->getQuery()
    ->condition('title', $data['title'])
    ->accessCheck(FALSE)
    ->execute();
  if (!empty($existing)) {
    echo '- Ignore (existe deja) : ' . $data['title'] . PHP_EOL;
    $skipped++;
    continue;
  }

  $variations = [];
  foreach ($data['variations'] as $v) {
    $variation = ProductVariation::create([
      'type'   => 'default',
      'sku'    => $v['sku'],
      'title'  => $v['label'],
      'price'  => new Price($v['price'], 'EUR'),
      'status' => TRUE,
    ]);
    $variation->save();
    $variations[] = $variation;
  }

  $product = Product::create([
    'type'       => 'default',
    'title'      => $data['title'],
    'body'       => ['value' => $data['body'], 'format' => 'basic_html'],
    'stores'     => [$store_id],
    'variations' => $variations,
    'status'     => TRUE,
  ]);
  $product->save();
  echo '+ Cree : ' . $data['title'] . PHP_EOL;
  $created++;
}

echo PHP_EOL . "Resultat : $created crees, $skipped ignores." . PHP_EOL;
