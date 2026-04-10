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
 * Les modèles de personnalisation sont créés automatiquement si absents, ignorés si présents.
 *
 * Périmètre v1 :
 *  - 1 variation par produit (première variante)
 *  - Images téléchargées depuis CDN Shopify et créées en Media Drupal
 *  - Chemin alias /products/{handle}
 *  - Personnalisation : modèles créés + assignés automatiquement depuis options Easify
 *  - Multi-variantes (10 produits) → Phase 2 (attributs Commerce)
 *
 * Usage :
 *   node scripts/fetch-shopify-catalogue.js   # d'abord générer catalogue.json
 *   ddev drush php-script scripts/migrate-catalogue.php
 *   terminus drush atelierodesign.dev -- php-script scripts/migrate-catalogue.php
 *
 * Variables d'environnement optionnelles :
 *   CATALOGUE_PATH  : chemin vers catalogue.json (défaut : auto-détecté)
 *   CSV_PATH        : chemin vers le CSV (fallback si catalogue.json absent)
 *   DRY_RUN         : si "1", analyse sans créer d'entités
 *   LIMIT           : nombre de produits à migrer (0 = tous)
 *   SKIP_PERSO      : si "1", ne crée pas les modèles de personnalisation
 */

// ---------------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------------

$dry_run   = (getenv('DRY_RUN') === '1');
$limit     = (int) (getenv('LIMIT') ?: 0);
$skip_perso = (getenv('SKIP_PERSO') === '1');

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
 *
 * L'extension est déterminée par priorité :
 *   1. Content-Type HTTP (source la plus fiable)
 *   2. Extension dans le path de l'URL
 *   3. Fallback 'jpg'
 */
function download_image(string $url, string $filename): ?object {
  try {
    $response = \Drupal::httpClient()->get($url, [
      'timeout'         => 15,
      'connect_timeout' => 8,  // Détection rapide d'hôte mort (Shopify CDN).
    ]);
    $data = (string) $response->getBody();
    if (empty($data)) {
      return NULL;
    }

    // Extension : Content-Type > URL path > fallback 'jpg'.
    // Le Content-Type est la source la plus fiable : il reflète le format réel
    // même quand l'URL CDN ne contient pas d'extension explicite.
    $ct_map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $ct     = strtolower(explode(';', $response->getHeaderLine('Content-Type'), 2)[0]);
    $ext    = $ct_map[$ct] ?? '';

    if ($ext === '') {
      $url_ext = strtolower(pathinfo(explode('?', $url, 2)[0], PATHINFO_EXTENSION));
      $ext = in_array($url_ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']) ? $url_ext : 'jpg';
    }
    // Normaliser jpeg → jpg pour cohérence avec Drupal (extensions autorisées).
    if ($ext === 'jpeg') {
      $ext = 'jpg';
    }

    $safe_name = preg_replace('/[^a-z0-9_\-]/', '-', strtolower($filename)) . '.' . $ext;
    $uri       = 'public://produits/' . $safe_name;

    // Créer le répertoire si nécessaire — MODIFY_PERMISSIONS corrige les droits
    // d'un répertoire existant avec des permissions incorrectes.
    $dir = dirname($uri);
    \Drupal::service('file_system')->prepareDirectory(
      $dir,
      \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS
    );

    return \Drupal::service('file.repository')->writeData($data, $uri, \Drupal\Core\File\FileExists::Replace);
  }
  catch (\Exception $e) {
    echo "  WARN image téléchargement échoué ({$url}): " . $e->getMessage() . "\n";
    return NULL;
  }
}

/**
 * Crée une entité Media de type product_image depuis un fichier Drupal.
 *
 * @return object|null  L'entité Media créée, ou null en cas d'erreur.
 */
function create_media(object $file, string $name, string $alt = ''): ?object {
  // Le champ name de l'entité Media est limité à 255 chars.
  // Un titre produit long + suffixe peut dépasser cette limite.
  $name = mb_substr($name, 0, 255);
  try {
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
  catch (\Exception $e) {
    echo "  WARN media création échouée ({$name}): " . $e->getMessage() . "\n";
    return NULL;
  }
}

// ---------------------------------------------------------------------------
// Helpers — personnalisation (TCK-040)
// ---------------------------------------------------------------------------

/** Champs internes Easify à ne pas exposer au client. */
$PERSO_INTERNAL = ['_tpo_add_by', '_tpo_price', '_tpo_custom_options'];

/**
 * Filtre les options Easify pour ne garder que les champs visibles par le client.
 */
function perso_user_options(array $options, array $internal): array {
  return array_values(array_filter($options, fn($o) => !in_array($o['property'] ?? '', $internal)));
}

/**
 * Normalise un nom de propriété Easify générique en libellé français lisible.
 * Les labels déjà configurés côté Shopify sont conservés tels quels.
 */
function normalize_perso_label(string $property): string {
  $map = [
    'text-box-1'      => 'Personnalisation',
    'text-box-2'      => 'Personnalisation (2)',
    'text-box-3'      => 'Personnalisation (3)',
    'file-upload-1'   => 'Photo à joindre',
    'file-upload-1-1' => 'Photo à joindre',
    'quantity-1'      => 'Quantité',
    'checkbox-1'      => 'Option',
    'checkbox-2'      => 'Option (2)',
  ];
  return $map[$property] ?? $property;
}

/**
 * Détecte le type de champ Drupal d'après le type Easify ou le nom de la propriété.
 * Priorité : type explicite du JSON Easify → heuristique sur le nom.
 */
function detect_perso_field_type(string $property, string $json_type = ''): string {
  // Utiliser le type déclaré par Easify quand disponible.
  if ($json_type !== '') {
    $type_map = [
      'text'     => 'text',
      'textarea' => 'text',
      'file'     => 'file',
      'number'   => 'number',
      'date'     => 'date',
      'checkbox' => 'checkboxes',
    ];
    if (isset($type_map[$json_type])) {
      return $type_map[$json_type];
    }
  }
  // Fallback : heuristique sur le nom de la propriété.
  $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($property)) ?: mb_strtolower($property);
  if (str_contains($ascii, 'date'))                           return 'date';
  if (preg_match('/photo|image|file|upload/', $ascii))        return 'file';
  if (preg_match('/quantit|quantity|nombre|number/', $ascii)) return 'number';
  if (preg_match('/checkbox|case/', $ascii))                  return 'checkboxes';
  return 'text';
}

/**
 * Retourne une signature canonique d'un jeu d'options (pour déduplication).
 * Deux produits avec les mêmes champs (mêmes noms, ordre ignoré) partagent la même signature.
 */
function perso_signature(array $user_options): string {
  $keys = array_map(fn($o) => mb_strtolower(trim($o['property'])), $user_options);
  sort($keys);
  return implode('|', $keys);
}

/**
 * Génère un identifiant système court et stable pour un modèle.
 * Reproductible : même signature → même id à chaque exécution.
 */
function perso_template_id(string $signature): string {
  return 'import_' . substr(md5($signature), 0, 8);
}

/**
 * Génère un libellé lisible pour l'interface à partir des champs du modèle.
 */
function perso_template_label(array $user_options): string {
  $labels = array_map(fn($o) => normalize_perso_label($o['property']), $user_options);
  $label  = implode(' + ', $labels);
  return mb_strlen($label) > 60 ? mb_substr($label, 0, 57) . '…' : $label;
}

/**
 * Crée ou charge un PersonalizationTemplate à partir d'un jeu d'options Easify.
 * Idempotent : si un modèle avec cet id existe déjà, le retourne tel quel.
 *
 * @return object|null  L'entité PersonalizationTemplate, ou null en cas d'erreur.
 */
function ensure_perso_template(string $template_id, string $template_label, array $user_options, bool $dry_run, bool &$was_created = FALSE): ?object {
  $storage = \Drupal::entityTypeManager()->getStorage('personalization_template');

  $existing = $storage->load($template_id);
  if ($existing) {
    echo "  ↻ Modèle existant réutilisé : [{$template_id}] {$template_label}\n";
    $was_created = FALSE;
    return $existing;
  }

  if ($dry_run) {
    echo "  DRY-RUN modèle : [{$template_id}] {$template_label} (" . count($user_options) . " champs)\n";
    return NULL;
  }

  $fields = [];
  foreach ($user_options as $i => $opt) {
    $label  = normalize_perso_label($opt['property']);
    $type   = detect_perso_field_type($opt['property'], $opt['type'] ?? '');
    $fields[] = [
      'id'          => 'field_' . ($i + 1),
      'label'       => $label,
      'type'        => $type,
      'required'    => FALSE,
      'placeholder' => '',
      'help'        => '',
      'maxlength'   => ($type === 'text') ? 80 : 0,
      'options'     => [],
      'group_max'   => 5,
      'price_modifier' => [
        'type'   => 'none',
        'amount' => '0',
        'label'  => '',
      ],
      'weight' => $i,
    ];
  }

  try {
    $template = \Drupal\odesign_personalization\Entity\PersonalizationTemplate::create([
      'id'          => $template_id,
      'label'       => $template_label,
      'description' => 'Créé automatiquement lors de l\'import catalogue Shopify. À affiner dans le backoffice.',
      'fields'      => $fields,
      'status'      => TRUE,
    ]);
    $template->save();
    echo "  ✦ Modèle créé : [{$template_id}] {$template_label} (" . count($fields) . " champs)\n";
    $was_created = TRUE;
    return $template;
  }
  catch (\Exception $e) {
    echo "  WARN modèle '{$template_id}' non créé : " . $e->getMessage() . "\n";
    return NULL;
  }
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
  $raw_content = file_get_contents($catalogue_path);
  if ($raw_content === FALSE) {
    echo "ERREUR : impossible de lire catalogue.json (vérifier les permissions).\n";
    return;
  }
  $raw = json_decode($raw_content, TRUE);
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
    foreach (($item['variants'] ?? []) as $idx => $v) {
      // Vérifier isset plutôt que !empty : un prix à "0" ou "0.00" est valide.
      if (isset($v['price']) && $v['price'] !== '') {
        // SKU : utiliser le SKU Shopify s'il est renseigné, sinon générer {handle}-NNN (index).
        // La génération par index garantit l'unicité par variante au sein du même produit.
        $sku_raw = trim($v['sku'] ?? '');
        $sku = $sku_raw !== '' ? $sku_raw : ($h . '-' . str_pad((string) ($idx + 1), 3, '0', STR_PAD_LEFT));
        $variants[] = [
          'sku'     => $sku,
          'price'   => $v['price'],
          'opt1n'   => $v['opt1_name'] ?? '',   // libellé de l'axe 1 (ex: "Taille")
          'opt1v'   => $v['opt1_value'] ?? '',   // valeur de l'axe 1 (ex: "A4")
          'opt2n'   => $v['opt2_name'] ?? '',
          'opt2v'   => $v['opt2_value'] ?? '',
          'opt3n'   => $v['opt3_name'] ?? '',
          'opt3v'   => $v['opt3_value'] ?? '',
          'suspect' => !empty($v['suspect']),   // variante à valeur numérique pure — à vérifier manuellement
        ];
      }
    }
    $products[$h] = [
      'handle'          => $h,
      'title'           => trim($item['title']),
      'body'            => trim($item['body'] ?? ''),
      // tags : capturé depuis Shopify mais non importé en v1 (pas de field_tags sur
      // commerce_product). Conserver pour traçabilité et Phase 2.
      'tags'            => trim($item['tags'] ?? ''),
      'seo_title'       => trim($item['seo_title'] ?? ''),
      'seo_desc'        => trim($item['seo_desc'] ?? ''),
      'status'          => $item['status'] ?? 'active',
      'variants'        => $variants,
      // Filtrer les URLs d'images null/vides : une entrée null provoquerait
      // un TypeError PHP 8.1 dans download_image (paramètre string non-nullable).
      'images'          => array_values(array_filter(
        $item['images'] ?? [],
        fn($u) => is_string($u) && $u !== ''
      )),
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
  if (!is_array($headers) || empty($headers)) {
    echo "ERREUR : CSV vide ou entête illisible.\n";
    fclose($fh);
    return;
  }

  // Vérifier la présence des colonnes requises pour éviter des skips silencieux.
  $required_cols = ['Handle', 'Title', 'Variant Price'];
  $missing_cols  = array_diff($required_cols, $headers);
  if (!empty($missing_cols)) {
    echo "ERREUR : colonnes requises manquantes dans le CSV : " . implode(', ', $missing_cols) . "\n";
    echo "Colonnes détectées : " . implode(', ', $headers) . "\n";
    fclose($fh);
    return;
  }

  while (($row = fgetcsv($fh)) !== FALSE) {
    // array_slice tronque les lignes plus longues que l'entête avant array_pad,
    // évitant un ValueError dans array_combine (count mismatch).
    $r = array_combine($headers, array_pad(array_slice($row, 0, count($headers)), count($headers), ''));
    $h = trim($r['Handle']);
    if (empty($h)) {
      continue;
    }

    if (!isset($products[$h])) {
      $products[$h] = [
        'handle'          => $h,
        'title'           => trim($r['Title'] ?? ''),
        // Colonnes optionnelles : utiliser ?? '' pour éviter trim(null) → deprecation PHP 8.1.
        'body'            => trim($r['Body (HTML)'] ?? ''),
        'tags'            => trim($r['Tags'] ?? ''),
        'seo_title'       => trim($r['SEO Title'] ?? ''),
        'seo_desc'        => trim($r['SEO Description'] ?? ''),
        'status'          => trim($r['Status'] ?? ''),
        'variants'        => [],
        'images'          => [],
        'personalization' => [],
      ];
    }

    $price = trim($r['Variant Price'] ?? '');
    if ($price !== '') {
      $sku_csv = trim($r['Variant SKU'] ?? '');
      $var_idx = count($products[$h]['variants']);
      $products[$h]['variants'][] = [
        // SKU : valeur CSV si renseignée, sinon {handle}-NNN (index) — cohérent avec le path JSON.
        'sku'     => $sku_csv !== '' ? $sku_csv : ($h . '-' . str_pad((string) ($var_idx + 1), 3, '0', STR_PAD_LEFT)),
        'price'   => $price,
        'opt1n'   => trim($r['Option1 Name'] ?? ''),
        'opt1v'   => trim($r['Option1 Value'] ?? ''),
        'opt2n'   => trim($r['Option2 Name'] ?? ''),
        'opt2v'   => trim($r['Option2 Value'] ?? ''),
        'opt3n'   => trim($r['Option3 Name'] ?? ''),
        'opt3v'   => trim($r['Option3 Value'] ?? ''),
        'suspect' => FALSE,  // détection suspect non disponible en path CSV
      ];
    }

    $img = trim($r['Image Src'] ?? '');
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
// Pré-pass : création des modèles de personnalisation
// Parcourt tout le catalogue, déduplique les profils et crée les modèles.
// Résultat : $perso_map[handle] = PersonalizationTemplate entity (ou NULL si pas de perso)
// ---------------------------------------------------------------------------

$perso_map         = [];  // handle → PersonalizationTemplate entity|null
$perso_templates   = [];  // signature → template_entity (cache session)
$count_perso_new   = 0;
$count_perso_reuse = 0;

if (!$skip_perso && $catalogue_path) {
  echo "\n--- Pré-pass personnalisation ---\n";

  foreach ($products as $handle => $data) {
    $user_opts = perso_user_options($data['personalization'] ?? [], $PERSO_INTERNAL);
    if (empty($user_opts)) {
      $perso_map[$handle] = NULL;
      continue;
    }

    $sig         = perso_signature($user_opts);
    $template_id = perso_template_id($sig);

    if (!array_key_exists($sig, $perso_templates)) {
      $label       = perso_template_label($user_opts);
      $was_created = FALSE;
      $entity      = ensure_perso_template($template_id, $label, $user_opts, $dry_run, $was_created);
      $perso_templates[$sig] = $entity;

      if ($entity && !$dry_run) {
        // Distinguer création réelle vs chargement d'un modèle déjà existant (run précédent).
        if ($was_created) {
          $count_perso_new++;
        }
        else {
          $count_perso_reuse++;
        }
      }
    }
    else {
      // Modèle déjà traité dans ce run (même signature, produit différent).
      if ($perso_templates[$sig] !== NULL && !$dry_run) {
        $count_perso_reuse++;
      }
    }

    // Stocker l'entité directement — évite de recalculer la signature en boucle principale.
    $perso_map[$handle] = $perso_templates[$sig];
  }

  $perso_products = count(array_filter($perso_map));
  echo "Produits avec personnalisation : {$perso_products} / " . count($products) . "\n";
  echo "Modèles créés : {$count_perso_new} — Modèles réutilisés : {$count_perso_reuse}\n";
  echo "---\n\n";
}
elseif ($skip_perso) {
  echo "SKIP_PERSO=1 : modèles de personnalisation ignorés.\n";
}

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

  // Filtrer les produits inactifs en premier — évite deux requêtes DB inutiles.
  if (in_array($data['status'], ['draft', 'archived'])) {
    echo "[{$n}] SKIP ({$data['status']}) : '{$data['title']}'\n";
    $count_skipped++;
    continue;
  }

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

  // Signaler les variantes suspectes seulement pour les produits qui vont être créés.
  // (après idempotence — évite le bruit sur les produits déjà importés)
  $suspect_variants = array_filter($data['variants'] ?? [], fn($v) => !empty($v['suspect']));
  if (!empty($suspect_variants)) {
    $suspect_vals = implode(', ', array_map(fn($v) => '"' . $v['opt1v'] . '"', $suspect_variants));
    echo "[{$n}] WARN variantes suspectes ({$suspect_vals}) : '{$data['title']}' — vérifier le sens des valeurs d'option avant la Phase 2.\n";
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
    $img_num  = $idx + 1;  // Index 1-based pour noms de fichiers, logs et labels Media.
    $img_name = $handle . '-' . $img_num;
    $file     = download_image($img_url, $img_name);
    if ($file) {
      $media = create_media($file, $data['title'] . ' image ' . $img_num, $data['title']);
      if ($media) {
        $media_ids[] = ['target_id' => $media->id()];
        echo "  Image {$img_num} → media id={$media->id()}\n";
      }
    }
  }

  // --- Création de la variation (première variante). ---
  $first_variant = $data['variants'][0];
  $sku           = $first_variant['sku'];
  // Éviter les SKU dupliqués en DB (ex: re-run partiel ou collision inter-produits).
  // La déduplication par index utilise un suffixe croissant pour garantir l'unicité,
  // contrairement à md5($handle) qui serait identique pour toutes les variantes du même produit.
  $attempt = 0;
  while (!empty($variation_storage->loadByProperties(['sku' => $sku]))) {
    $attempt++;
    $sku = $first_variant['sku'] . '-dup' . $attempt;
    if ($attempt >= 10) {
      // Sécurité anti-boucle infinie — ne devrait jamais se produire.
      break;
    }
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
    'type'       => 'default',
    'title'      => $data['title'],
    'stores'     => [['target_id' => $store_id]],
    'variations' => [['target_id' => $variation->id()]],
    'status'     => TRUE,
    'path'       => [['alias' => $alias]],
  ];

  if (!empty($data['body'])) {
    $product_data['body'] = [
      // full_html : le corps Shopify peut contenir du HTML arbitraire (tableaux, attributs, etc.)
      'value'  => $data['body'],
      'format' => 'full_html',
    ];
  }

  if (!empty($media_ids)) {
    $product_data['field_product_images'] = $media_ids;
  }

  // SEO : metatag title / description issus du scraping Shopify.
  $meta = [];
  if (!empty($data['seo_title'])) {
    $meta['title'] = ['value' => trim($data['seo_title'])];
  }
  if (!empty($data['seo_desc'])) {
    $meta['description'] = ['value' => trim($data['seo_desc'])];
  }
  if (!empty($meta)) {
    $product_data['metatag'] = serialize($meta);
  }

  // Modèle de personnalisation — assigné avant le premier save pour éviter un double write.
  $template_entity = $perso_map[$handle] ?? NULL;
  if ($template_entity !== NULL && !$skip_perso) {
    $product_data['field_personalization_template'] = ['target_id' => $template_entity->id()];
  }

  try {
    $product = \Drupal\commerce_product\Entity\Product::create($product_data);
    $product->save();
    echo "  ✓ Produit id={$product->id()} variante id={$variation->id()}\n";

    if ($template_entity !== NULL && !$skip_perso) {
      echo "  ✦ Modèle perso assigné : [{$template_entity->id()}] {$template_entity->label()}\n";
    }

    $count_created++;
  }
  catch (\Exception $e) {
    echo "  ERREUR produit : " . $e->getMessage() . "\n";
    // Supprimer la variation orpheline.
    try {
      $variation->delete();
    }
    catch (\Exception $del_e) {
      echo "  WARN variation orpheline id={$variation->id()} non supprimée : " . $del_e->getMessage() . "\n";
    }
    // Supprimer les Media orphelins créés avant l'échec du produit.
    if (!empty($media_ids)) {
      $media_storage = \Drupal::entityTypeManager()->getStorage('media');
      foreach ($media_ids as $mid_ref) {
        try {
          $orphan = $media_storage->load($mid_ref['target_id']);
          if ($orphan) {
            $orphan->delete();
          }
        }
        catch (\Exception $del_e) {
          echo "  WARN media orphelin id={$mid_ref['target_id']} non supprimé : " . $del_e->getMessage() . "\n";
        }
      }
    }
    $count_error++;
  }
}

// ---------------------------------------------------------------------------
// Rapport
// ---------------------------------------------------------------------------

echo "\n=== RÉSULTAT MIGRATION ===\n";
echo "Source          : " . ($catalogue_path ? 'catalogue.json' : 'CSV (fallback)') . "\n";
echo "Créés           : {$count_created}\n";
echo "Skippés         : {$count_skipped}\n";
echo "Erreurs         : {$count_error}\n";
echo "Total           : " . ($count_created + $count_skipped + $count_error) . "\n";
if (!$skip_perso && $catalogue_path) {
  echo "Modèles perso   : {$count_perso_new} créés, {$count_perso_reuse} réutilisés (run précédent ou même signature)\n";
  $assigned = count(array_filter($perso_map, fn($entity) => $entity !== NULL));
  echo "Produits avec modèle perso : {$assigned}\n";
}
echo "\nNote : les produits multi-variantes sont migrés avec leur première variante uniquement.\n";
echo "Phase 2  : création des attributs Commerce pour les variantes restantes.\n";
echo "Perso    : les modèles créés automatiquement sont à affiner dans le backoffice\n";
echo "           (/admin/boutique/personnalisation/templates) pour ajuster labels et types.\n";
