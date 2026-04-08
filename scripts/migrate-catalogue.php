<?php

/**
 * @file
 * Migration du catalogue Shopify → Drupal Commerce.
 *
 * Source principale : scripts/catalogue.json
 *   Généré par : node scripts/fetch-shopify-catalogue.js
 *   Contient : titre, body, tags, SEO, variantes, images, options Easify (personnalisation)
 *
 * Fallback (rétrocompatibilité) : fichier CSV Shopify products_export_1.csv
 *   Utilisé si catalogue.json est absent. Pas de données SEO ni de personnalisation.
 *
 * Ce script est idempotent : il skipe les produits déjà présents (par handle/alias).
 *
 * Périmètre v1 :
 *  - 1 variation par produit (première variante)
 *  - Images téléchargées depuis CDN Shopify et créées en Media Drupal
 *  - Chemin alias /products/{handle}
 *  - Personnalisation (options Easify) : stockée dans field_personalization_options
 *    si le champ existe, sinon loguée uniquement
 *  - Multi-variantes (10 produits) → Phase 2 (attributs Commerce)
 *
 * Usage :
 *   node scripts/fetch-shopify-catalogue.js   # d'abord générer catalogue.json
 *   ddev drush php-script scripts/migrate-catalogue.php
 *   terminus drush atelierodesign.dev -- php-script scripts/migrate-catalogue.php
 *
 * Variables d'environnement optionnelles :
 *   CATALOGUE_PATH : chemin vers catalogue.json (défaut : auto-détecté)
 *   CSV_PATH       : chemin vers le CSV (fallback si catalogue.json absent)
 *   DRY_RUN        : si "1", analyse sans créer d'entités
 *   LIMIT          : nombre de produits à migrer (0 = tous)
 */

// ---------------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------------

$dry_run = (getenv('DRY_RUN') === '1');
$limit   = (int) (getenv('LIMIT') ?? 0);

// ---------------------------------------------------------------------------
// Source 1 (prioritaire) : catalogue.json généré par fetch-shopify-catalogue.js
// ---------------------------------------------------------------------------

$catalogue_candidates = [
  __DIR__ . '/catalogue.json',
  // Pantheon
  '/code/scripts/catalogue.json',
  // Chemin explicite
  getenv('CATALOGUE_PATH') ?: '',
];

$catalogue_path = '';
foreach ($catalogue_candidates as $c) {
  if ($c && file_exists(realpath($c) ?: $c)) {
    $catalogue_path = realpath($c) ?: $c;
    break;
  }
}

// ---------------------------------------------------------------------------
// Source 2 (fallback) : CSV Shopify d'export
// ---------------------------------------------------------------------------

$csv_candidates = [
  __DIR__ . '/products_export_1.csv',
  '/code/../odesign-docs/collecte/shopify/exports/produits/products_export_1.csv',
  '/var/www/html/../../../odesign-docs/collecte/shopify/exports/produits/products_export_1.csv',
  getenv('CSV_PATH') ?: '',
];

$csv_path = '';
if (!$catalogue_path) {
  foreach ($csv_candidates as $c) {
    if ($c && file_exists(realpath($c) ?: $c)) {
      $csv_path = realpath($c) ?: $c;
      break;
    }
  }
  if (!$csv_path) {
    $possible = glob('/code/*/collecte/shopify/exports/produits/products_export_1.csv');
    if (!empty($possible)) {
      $csv_path = $possible[0];
    }
  }
}

if (!$catalogue_path && !$csv_path) {
  echo "ERREUR : aucune source de données trouvée.\n";
  echo "Solution recommandée : node scripts/fetch-shopify-catalogue.js\n";
  echo "Fallback CSV        : définir CSV_PATH ou placer odesign-docs à côté d'odesign-drupal.\n";
  return;
}

$source_label = $catalogue_path ? "catalogue.json : {$catalogue_path}" : "CSV (fallback) : {$csv_path}";
echo "Source : {$source_label}\n";
echo ($dry_run ? "MODE DRY-RUN activé — aucune entité créée\n" : "");

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Télécharge une image depuis une URL et retourne un file Drupal géré.
 */
function download_image(string $url, string $filename): ?object {
  try {
    $response = \Drupal::httpClient()->get($url, ['timeout' => 15]);
    $data = (string) $response->getBody();
    if (empty($data)) {
      return NULL;
    }
    // Déterminer l'extension depuis le content-type ou l'URL.
    $ext = pathinfo(strtok($url, '?'), PATHINFO_EXTENSION) ?: 'jpg';
    $ext = in_array(strtolower($ext), ['jpg','jpeg','png','webp','gif']) ? strtolower($ext) : 'jpg';
    $safe_name = preg_replace('/[^a-z0-9_\-]/', '-', strtolower($filename)) . '.' . $ext;
    $uri = 'public://produits/' . $safe_name;

    // Créer le répertoire si nécessaire.
    $dir = dirname($uri);
    \Drupal::service('file_system')->prepareDirectory($dir, \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY);

    return \Drupal::service('file.repository')->writeData($data, $uri, \Drupal\Core\File\FileExists::Replace);
  }
  catch (\Exception $e) {
    echo "  WARN image téléchargement échoué ({$url}): " . $e->getMessage() . "\n";
    return NULL;
  }
}

/**
 * Crée une entité Media de type product_image depuis un fichier Drupal.
 */
function create_media(object $file, string $name, string $alt = ''): object {
  $media = \Drupal\media\Entity\Media::create([
    'bundle'            => 'product_image',
    'name'              => $name,
    'field_media_image' => [
      'target_id' => $file->id(),
      'alt'       => $alt ?: $name,
      'title'     => $name,
    ],
    'status'            => TRUE,
  ]);
  $media->save();
  return $media;
}

/**
 * Retourne les ids de produits déjà liés à un alias donné.
 */
function find_product_ids_by_alias(string $alias): array {
  $query = \Drupal::database()->select('path_alias', 'pa')
    ->fields('pa', ['path'])
    ->condition('alias', $alias);

  $paths = $query->execute()->fetchCol();
  $ids = [];

  foreach ($paths as $path) {
    if (preg_match('#^/product/(\d+)$#', $path, $matches)) {
      $ids[] = (int) $matches[1];
    }
  }

  return array_values(array_unique($ids));
}

// ---------------------------------------------------------------------------
// Lecture et groupement du CSV par handle
// ---------------------------------------------------------------------------

$products = [];

// ---------------------------------------------------------------------------
// Lecture depuis catalogue.json (source principale)
// ---------------------------------------------------------------------------

if ($catalogue_path) {
  $raw = json_decode(file_get_contents($catalogue_path), TRUE);
  if (!is_array($raw)) {
    echo "ERREUR : catalogue.json invalide ou vide.\n";
    return;
  }
  foreach ($raw as $item) {
    $h = trim($item['handle'] ?? '');
    if (empty($h) || empty($item['title'])) {
      continue;
    }
    $variants = [];
    foreach (($item['variants'] ?? []) as $v) {
      if (!empty($v['price'])) {
        $variants[] = [
          'sku'   => $v['sku'] ?: ($h . '-001'),
          'price' => $v['price'],
          'opt1n' => $v['opt1_name'] ?? '',
          'opt1v' => $v['opt1_value'] ?? '',
        ];
      }
    }
    $products[$h] = [
      'handle'          => $h,
      'title'           => trim($item['title']),
      'body'            => trim($item['body'] ?? ''),
      'tags'            => trim($item['tags'] ?? ''),
      'seo_title'       => trim($item['seo_title'] ?? ''),
      'seo_desc'        => trim($item['seo_desc'] ?? ''),
      'status'          => $item['status'] ?? 'active',
      'variants'        => $variants,
      'images'          => $item['images'] ?? [],
      'personalization' => $item['personalization'] ?? [],
    ];
  }
}
// ---------------------------------------------------------------------------
// Fallback : lecture depuis le CSV Shopify
// ---------------------------------------------------------------------------
elseif ($csv_path) {
  echo "AVERTISSEMENT : catalogue.json absent — fallback CSV. Données SEO et personnalisation non disponibles.\n";
  echo "              Générer catalogue.json : node scripts/fetch-shopify-catalogue.js\n\n";

  if (($fh = fopen($csv_path, 'r')) === FALSE) {
    echo "ERREUR : impossible d'ouvrir le CSV.\n";
    return;
  }

  $headers = fgetcsv($fh);

  while (($row = fgetcsv($fh)) !== FALSE) {
    $r = array_combine($headers, array_pad($row, count($headers), ''));
    $h = trim($r['Handle']);
    if (empty($h)) {
      continue;
    }

    if (!isset($products[$h])) {
      $products[$h] = [
        'handle'          => $h,
        'title'           => trim($r['Title']),
        'body'            => trim($r['Body (HTML)']),
        'tags'            => trim($r['Tags']),
        'seo_title'       => trim($r['SEO Title']),
        'seo_desc'        => trim($r['SEO Description']),
        'status'          => trim($r['Status']),
        'variants'        => [],
        'images'          => [],
        'personalization' => [],
      ];
    }

    $price = trim($r['Variant Price']);
    if ($price) {
      $products[$h]['variants'][] = [
        'sku'   => trim($r['Variant SKU']) ?: ($h . '-001'),
        'price' => $price,
        'opt1n' => trim($r['Option1 Name']),
        'opt1v' => trim($r['Option1 Value']),
      ];
    }

    $img = trim($r['Image Src']);
    if ($img && !in_array($img, $products[$h]['images'])) {
      $products[$h]['images'][] = $img;
    }
  }
  fclose($fh);
}

// Retirer les produits sans titre ni variante.
$products = array_filter($products, fn($p) => !empty($p['title']) && !empty($p['variants']));

$source_count = count($products);
echo "Produits lus depuis la source : {$source_count}\n";

// ---------------------------------------------------------------------------
// Vérifier le store
// ---------------------------------------------------------------------------

$stores = \Drupal::entityTypeManager()->getStorage('commerce_store')->loadMultiple();
if (empty($stores)) {
  echo "ERREUR : aucun store. Exécuter seed-shipping-methods.php d'abord.\n";
  return;
}
$store    = reset($stores);
$store_id = $store->id();
echo "Store : " . $store->getName() . " (id={$store_id})\n";

// ---------------------------------------------------------------------------
// Migration
// ---------------------------------------------------------------------------

$product_storage   = \Drupal::entityTypeManager()->getStorage('commerce_product');
$variation_storage = \Drupal::entityTypeManager()->getStorage('commerce_product_variation');

$count_created = 0;
$count_skipped = 0;
$count_error   = 0;
$n             = 0;

foreach ($products as $handle => $data) {
  if ($limit > 0 && $n >= $limit) {
    echo "LIMIT={$limit} atteinte, arrêt.\n";
    break;
  }
  $n++;

  $alias = '/products/' . $handle;

  // --- Idempotence forte : alias Shopify d'abord, titre ensuite en fallback. ---
  $existing_ids_by_alias = find_product_ids_by_alias($alias);
  if (!empty($existing_ids_by_alias)) {
    $duplicate_note = count($existing_ids_by_alias) > 1
      ? ' — ALERTE doublons locaux alias=' . count($existing_ids_by_alias)
      : '';
    echo "[{$n}] SKIP alias : '{$data['title']}' ({$alias}) existe déjà{$duplicate_note}.\n";
    $count_skipped++;
    continue;
  }

  $existing_products = $product_storage->loadByProperties(['title' => $data['title']]);
  if (!empty($existing_products)) {
    echo "[{$n}] SKIP titre : '{$data['title']}' existe déjà.\n";
    $count_skipped++;
    continue;
  }

  // Filtrer les produits inactifs (draft/archived).
  if (in_array($data['status'], ['draft', 'archived'])) {
    echo "[{$n}] SKIP ({$data['status']}) : '{$data['title']}'\n";
    $count_skipped++;
    continue;
  }

  if ($dry_run) {
    echo "[{$n}] DRY-RUN : '{$data['title']}' ({$handle}) — " . count($data['images']) . " images, " . count($data['variants']) . " variantes\n";
    $count_created++;
    continue;
  }

  echo "[{$n}] Création : '{$data['title']}' ({$handle})…\n";

  // --- Téléchargement des images. ---
  $media_ids = [];
  foreach ($data['images'] as $idx => $img_url) {
    $img_name = $handle . '-' . ($idx + 1);
    $file = download_image($img_url, $img_name);
    if ($file) {
      $media = create_media($file, $data['title'] . ' image ' . ($idx + 1), $data['title']);
      $media_ids[] = ['target_id' => $media->id()];
      echo "  Image {$idx} → media id={$media->id()}\n";
    }
  }

  // --- Création de la variation (première variante du CSV). ---
  $first_variant = $data['variants'][0];
  $sku           = $first_variant['sku'];
  // Éviter les SKU dupliqués.
  $sku_exists = $variation_storage->loadByProperties(['sku' => $sku]);
  if (!empty($sku_exists)) {
    $sku = $sku . '-' . substr(md5($handle), 0, 4);
  }

  try {
    $variation = \Drupal\commerce_product\Entity\ProductVariation::create([
      'type'   => 'default',
      'sku'    => $sku,
      'title'  => $data['title'],
      'price'  => [
        'number'        => number_format((float) $first_variant['price'], 2, '.', ''),
        'currency_code' => 'EUR',
      ],
      'status' => TRUE,
    ]);
    $variation->save();
  }
  catch (\Exception $e) {
    echo "  ERREUR variation : " . $e->getMessage() . "\n";
    $count_error++;
    continue;
  }

  // --- Création du produit. ---
  $product_data = [
    'type'                 => 'default',
    'title'                => $data['title'],
    'stores'               => [['target_id' => $store_id]],
    'variations'           => [['target_id' => $variation->id()]],
    'status'               => TRUE,
    'path'                 => [['alias' => $alias]],
  ];

  if (!empty($data['body'])) {
    $product_data['body'] = [
      'value'  => $data['body'],
      'format' => 'basic_html',
    ];
  }

  if (!empty($media_ids)) {
    $product_data['field_product_images'] = $media_ids;
  }

  try {
    $product = \Drupal\commerce_product\Entity\Product::create($product_data);
    $product->save();
    echo "  ✓ Produit id={$product->id()} variante id={$variation->id()}\n";

    // Log des options de personnalisation (utilisé par TCK-040 quand implémenté).
    $user_perso = array_filter(
      $data['personalization'] ?? [],
      fn($o) => !in_array($o['property'] ?? '', ['_tpo_add_by', '_tpo_price', '_tpo_custom_options'])
    );
    if (!empty($user_perso)) {
      $perso_summary = implode(', ', array_column($user_perso, 'property'));
      echo "  ℹ Personnalisation (TCK-040) : {$perso_summary}\n";
    }

    $count_created++;
  }
  catch (\Exception $e) {
    echo "  ERREUR produit : " . $e->getMessage() . "\n";
    // Supprimer la variation orpheline.
    $variation->delete();
    $count_error++;
  }
}

// ---------------------------------------------------------------------------
// Rapport
// ---------------------------------------------------------------------------

echo "\n=== RÉSULTAT MIGRATION ===\n";
echo "Source   : " . ($catalogue_path ? 'catalogue.json' : 'CSV (fallback)') . "\n";
echo "Créés    : {$count_created}\n";
echo "Skippés  : {$count_skipped}\n";
echo "Erreurs  : {$count_error}\n";
echo "Total    : " . ($count_created + $count_skipped + $count_error) . "\n";
echo "\nNote : les produits multi-variantes sont migrés avec leur première variante uniquement.\n";
echo "Phase 2  : création des attributs Commerce pour les variantes restantes.\n";
echo "TCK-040  : les options de personnalisation sont disponibles dans catalogue.json,\n";
echo "           prêtes à être importées quand l'architecture sera validée.\n";
