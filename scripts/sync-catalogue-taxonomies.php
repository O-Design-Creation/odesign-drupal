<?php

/**
 * @file
 * Enrichit les produits Commerce à partir des exports Shopify.
 *
 * Objectifs :
 * - alimenter `field_category` (lecture secondaire par type de produit)
 * - alimenter `field_life_occasion` (lecture principale par occasions de vie)
 * - créer les termes manquants si nécessaire
 *
 * Usage :
 *   ddev drush php-script scripts/sync-catalogue-taxonomies.php
 *   terminus drush atelierodesign.dev -- php-script scripts/sync-catalogue-taxonomies.php
 */

$csv_candidates = [
  __DIR__ . '/products_export_1.csv',
  '/code/../odesign-docs/collecte/shopify/exports/produits/products_export_1.csv',
  '/var/www/html/../../../odesign-docs/collecte/shopify/exports/produits/products_export_1.csv',
  getenv('CSV_PATH') ?: '',
];

$csv_path = '';
foreach ($csv_candidates as $candidate) {
  if ($candidate && file_exists(realpath($candidate) ?: $candidate)) {
    $csv_path = realpath($candidate) ?: $candidate;
    break;
  }
}

if (!$csv_path) {
  echo "ERREUR : CSV Shopify introuvable.\n";
  return;
}

/**
 * Normalise une chaîne en ASCII simple pour les comparaisons.
 */
function normalize_text(string $value): string {
  $value = mb_strtolower(trim($value));
  $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
  $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
  return trim($value);
}

/**
 * Retourne la feuille la plus basse d'une catégorie Google/Shopify.
 */
function leaf_category(string $value): string {
  $parts = array_filter(array_map('trim', explode('>', $value)));
  return $parts ? end($parts) : trim($value);
}

/**
 * Charge ou crée un terme dans un vocabulaire.
 */
function load_or_create_term(string $vocabulary, string $name): \Drupal\taxonomy\TermInterface {
  $terms = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadByProperties([
      'vid' => $vocabulary,
      'name' => $name,
    ]);

  if ($terms) {
    return reset($terms);
  }

  $term = \Drupal\taxonomy\Entity\Term::create([
    'vid' => $vocabulary,
    'name' => $name,
    'status' => TRUE,
  ]);
  $term->save();

  return $term;
}

/**
 * Déduit les univers à associer à partir des données Shopify.
 */
function derive_life_occasion_labels(array $row): array {
  $haystack = normalize_text(implode(' ', [
    $row['Title'] ?? '',
    $row['Type'] ?? '',
    $row['Tags'] ?? '',
    $row['Type de célébration (product.metafields.shopify.celebration-type)'] ?? '',
    $row['Thème (product.metafields.shopify.theme)'] ?? '',
    $row['Product Category'] ?? '',
  ]));

  $labels = [];

  $pancarte_keywords = [
    'pancarte', 'panneau', 'signaletique', 'signaletique',
    'evenement', 'arche', 'bienvenue', 'plan table',
  ];
  foreach ($pancarte_keywords as $keyword) {
    if (str_contains($haystack, $keyword)) {
      $labels[] = 'Pancarte événementielles';
      break;
    }
  }

  $mariage_keywords = [
    'mariage', 'maries', 'alliance', 'temoin', 'urne',
    'livre or', 'wedding', 'deux oui', 'vin honneur',
  ];
  foreach ($mariage_keywords as $keyword) {
    if (str_contains($haystack, $keyword)) {
      $labels[] = 'Mariage';
      break;
    }
  }

  $birth_keywords = [
    'naissance', 'bebe', 'grossesse', 'prenatale',
    'jumeaux', 'fete prenatale', 'chambre', 'enfant',
  ];
  foreach ($birth_keywords as $keyword) {
    if (str_contains($haystack, $keyword)) {
      $labels[] = 'Naissance & bébé';
      break;
    }
  }

  if (!$labels) {
    $labels[] = 'Cadeaux personnalisés / famille';
  }

  return array_values(array_unique($labels));
}

/**
 * Déduit une catégorie secondaire exploitable.
 */
function derive_product_category(array $row): ?string {
  $type = trim((string) ($row['Type'] ?? ''));
  if ($type !== '') {
    return $type;
  }

  $leaf = leaf_category((string) ($row['Product Category'] ?? ''));

  $translations = [
    'Wedding Ceremony Supplies' => 'Accessoires de mariage',
    'Party Supplies' => 'Articles de fête',
    'Decorative Plaques' => 'Plaques décoratives',
    'Greeting Cards' => 'Cartes personnalisées',
    'Baby Gift Sets' => 'Cadeaux bébé',
    'Refrigerator Magnets' => 'Magnets',
    'Window Magnets' => 'Magnets',
    'Picture Frames' => 'Cadres',
    'Place Cards' => 'Marque-places',
    'Keychains' => 'Porte-clés',
    'Household Storage Containers' => 'Rangement',
    'Uncategorized' => 'Autres créations',
  ];

  return $translations[$leaf] ?? ($leaf !== '' ? $leaf : NULL);
}

$life_terms = [
  'Mariage' => load_or_create_term('life_occasion', 'Mariage'),
  'Naissance & bébé' => load_or_create_term('life_occasion', 'Naissance & bébé'),
  'Cadeaux personnalisés / famille' => load_or_create_term('life_occasion', 'Cadeaux personnalisés / famille'),
  'Pancarte événementielles' => load_or_create_term('life_occasion', 'Pancarte événementielles'),
];

$rows_by_handle = [];
if (($handle = fopen($csv_path, 'r')) !== FALSE) {
  $headers = fgetcsv($handle);
  while (($row = fgetcsv($handle)) !== FALSE) {
    $row = array_combine($headers, array_pad($row, count($headers), ''));
    $shopify_handle = trim((string) ($row['Handle'] ?? ''));
    $product_title = trim((string) ($row['Title'] ?? ''));
    $status = trim((string) ($row['Status'] ?? ''));
    if ($shopify_handle === '' || $product_title === '' || $status === 'draft') {
      continue;
    }
    if (!isset($rows_by_handle[$shopify_handle])) {
      $rows_by_handle[$shopify_handle] = $row;
    }
  }
  fclose($handle);
}

$product_storage = \Drupal::entityTypeManager()->getStorage('commerce_product');
$products = $product_storage->loadMultiple();
$updated = 0;
$missing = 0;
$duplicate_aliases = [];

foreach ($products as $product) {
  $alias = $product->toUrl()->toString();
  if (!str_starts_with($alias, '/products/')) {
    $missing++;
    echo "SKIP hors alias Shopify : {$product->label()} ({$alias})\n";
    continue;
  }

  $shopify_handle = substr($alias, strlen('/products/'));
  if ($shopify_handle === '') {
    $missing++;
    echo "SKIP alias vide : {$product->label()} ({$alias})\n";
    continue;
  }

  if (!isset($rows_by_handle[$shopify_handle])) {
    $missing++;
    echo "SKIP sans ligne CSV : {$product->label()} ({$shopify_handle})\n";
    continue;
  }

  $duplicate_aliases[$shopify_handle] = ($duplicate_aliases[$shopify_handle] ?? 0) + 1;
  $row = $rows_by_handle[$shopify_handle];
  $life_labels = derive_life_occasion_labels($row);
  $life_target_ids = [];
  foreach ($life_labels as $life_label) {
    if (isset($life_terms[$life_label])) {
      $life_target_ids[] = (int) $life_terms[$life_label]->id();
    }
  }
  $life_target_ids = array_values(array_unique($life_target_ids));
  $secondary = derive_product_category($row);

  $changed = FALSE;

  if ($product->hasField('field_life_occasion')) {
    $current_ids = array_map(static fn($item) => (int) $item['target_id'], $product->get('field_life_occasion')->getValue());
    sort($current_ids);
    $expected_ids = $life_target_ids;
    sort($expected_ids);
    if ($current_ids !== $expected_ids) {
      $product->set('field_life_occasion', array_map(
        static fn(int $target_id): array => ['target_id' => $target_id],
        $expected_ids
      ));
      $changed = TRUE;
    }
  }

  if ($secondary && $product->hasField('field_category')) {
    $secondary_term = load_or_create_term('product_category', $secondary);
    $current_category = $product->get('field_category')->target_id ? (int) $product->get('field_category')->target_id : NULL;
    if ($current_category !== (int) $secondary_term->id()) {
      $product->set('field_category', ['target_id' => $secondary_term->id()]);
      $changed = TRUE;
    }
  }

  if ($changed) {
    $product->save();
    $updated++;
    echo "UPDATE : {$product->label()} ({$shopify_handle}) -> " . implode(' + ', $life_labels) . ($secondary ? " / {$secondary}" : '') . "\n";
  }
}

echo "\n=== SYNCHRONISATION TAXONOMIES ===\n";
echo "Produits mis à jour : {$updated}\n";
echo "Produits sans ligne CSV : {$missing}\n";
echo "Aliases produits dupliqués localement : " . count(array_filter($duplicate_aliases, static fn($count) => $count > 1)) . "\n";
echo "CSV source : {$csv_path}\n";
