<?php
/**
 * @file
 * Import d'articles de blog Shopify -> Drupal (type node: article).
 *
 * Usage:
 *   ddev drush php:script scripts/create-blog-articles.php
 *
 * Variables optionnelles:
 *   BLOG_SOURCE_JSON=/chemin/articles-shopify.json
 *   DRY_RUN=1
 *
 * Schéma d'entrée JSON attendu (tolérant):
 * [
 *   {
 *     "id": "gid://shopify/Article/1234567890",
 *     "handle": "mon-article",
 *     "title": "Mon article",
 *     "body_html": "<p>...</p>",
 *     "excerpt": "Résumé court",
 *     "published_at": "2026-03-30T10:00:00+00:00",
 *     "author": "Océane",
 *     "blog_handle": "news",
 *     "shopify_url": "https://www.atelierodesign.fr/blogs/news/mon-article"
 *   }
 * ]
 */

declare(strict_types=1);

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\filter\Entity\FilterFormat;
use Drupal\node\Entity\Node;
use Drupal\redirect\Entity\Redirect;

$entity_type_manager = \Drupal::entityTypeManager();
$alias_manager = \Drupal::service('path_alias.manager');
$logger = \Drupal::logger('odesign_blog_import');
$redirect_repository = \Drupal::service('redirect.repository');

// Fallback : blog-articles-real.json si blog-articles.json absent (nom canonique post-migration)
$source_path = getenv('BLOG_SOURCE_JSON')
  ?: (is_file(__DIR__ . '/blog-articles.json')
      ? __DIR__ . '/blog-articles.json'
      : __DIR__ . '/blog-articles-real.json');
$dry_run = getenv('DRY_RUN') === '1';
$strict_shopify_url = getenv('STRICT_SHOPIFY_URL') !== '0';
$requested_body_format = getenv('BLOG_BODY_FORMAT') ?: 'basic_html';
$body_format = $requested_body_format;

/**
 * Lit une valeur non vide parmi plusieurs clés possibles.
 */
$pick = static function (array $item, array $keys, $default = '') {
  foreach ($keys as $key) {
    if (array_key_exists($key, $item) && $item[$key] !== NULL && $item[$key] !== '') {
      return $item[$key];
    }
  }
  return $default;
};

/**
 * Extrait un ID numérique depuis un GID Shopify ou retourne la chaîne telle quelle.
 */
$normalizeShopifyId = static function (string $raw): string {
  $raw = trim($raw);
  if ($raw === '') {
    return '';
  }
  if (preg_match('#/([0-9]+)$#', $raw, $m) === 1) {
    return $m[1];
  }
  return $raw;
};

/**
 * Slugification de secours quand le handle n'est pas fourni.
 */
$slugify = static function (string $title): string {
  $value = mb_strtolower(trim($title));
  $value = preg_replace('/[\x{2019}\x{2018}\x{00B4}\']+/u', '-', $value) ?? $value;
  $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? $value;
  $value = preg_replace('/-+/', '-', $value) ?? $value;
  return trim($value, '-') ?: 'article-sans-titre';
};

/**
 * Déduit l'alias cible Drupal depuis l'URL Shopify source.
 * Objectif: conserver strictement le même path pour éviter toute correction
 * manuelle post-import.
 */
$aliasFromShopifyUrl = static function (string $shopify_url, string $fallback_alias): string {
  $shopify_url = trim($shopify_url);
  if ($shopify_url === '') {
    return $fallback_alias;
  }

  $path = (string) parse_url($shopify_url, PHP_URL_PATH);
  $path = trim($path);
  if ($path === '' || $path === '/') {
    return $fallback_alias;
  }

  // Normalise le slash final pour éviter les doublons d'alias.
  $path = rtrim($path, '/');
  return $path === '' ? $fallback_alias : $path;
};

/**
 * Crée une redirection 301 de compatibilité si absente.
 */
$ensureRedirect = static function (string $source, string $destination) use ($redirect_repository): void {
  if (!class_exists(Redirect::class)) {
    return;
  }

  $source = trim($source);
  $destination = trim($destination);
  if ($source === '' || $destination === '' || $source === $destination) {
    return;
  }

  $existing = $redirect_repository->findMatchingRedirect(ltrim($source, '/'));
  if ($existing) {
    return;
  }

  $redirect = Redirect::create([
    'redirect_source' => ['path' => ltrim($source, '/'), 'query' => NULL],
    'redirect_redirect' => ['uri' => 'internal:' . $destination],
    'status_code' => 301,
    'langcode' => 'fr',
    'uid' => 1,
  ]);
  $redirect->save();
};

/**
 * Normalise le titre source (ex: suppression suffixe SEO du storefront).
 */
$normalizeTitle = static function (string $title): string {
  $title = trim($title);
  if ($title === '') {
    return '';
  }

  $title = preg_replace('/\s*-\s*Odesign création\s*$/iu', '', $title) ?? $title;
  $title = preg_replace('/\s*-\s*O[\'’]?Design création\s*$/iu', '', $title) ?? $title;

  return trim($title);
};

/**
 * Nettoie le HTML importé depuis Shopify (suppression des attributs techniques).
 */
$sanitizeHtml = static function (string $html): string {
  $html = trim($html);
  if ($html === '') {
    return '';
  }

  // Supprime les scripts/styles potentiellement injectés.
  $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;

  // Supprime les attributs data-* (ex: data-start/data-end) issus d'outils de génération.
  $html = preg_replace('/\sdata-[a-zA-Z0-9_:-]+=("[^"]*"|\'[^\']*\')/u', '', $html) ?? $html;

  // Nettoie les classes potentiellement parasites d'éditeurs externes.
  $html = preg_replace('/\sclass=("[^"]*"|\'[^\']*\')/u', '', $html) ?? $html;

  // Normalise les espaces insécables.
  $html = str_replace(["\xC2\xA0", '&nbsp;'], ' ', $html);

  // Supprime les retours vides superflus.
  $html = preg_replace('/\n{3,}/', "\n\n", $html) ?? $html;

  return trim($html);
};

/**
 * Extrait la première URL d'image depuis le HTML.
 */
$extractFirstImageFromHtml = static function (string $html): string {
  $html = trim($html);
  if ($html === '') {
    return '';
  }
  if (preg_match('/<img\b[^>]*\bsrc=(["\'])(.*?)\1/i', $html, $m) === 1) {
    return trim((string) $m[2]);
  }
  return '';
};

/**
 * Extrait l'image OpenGraph d'une page source.
 */
$extractOgImageFromPage = static function (string $url): string {
  $url = trim($url);
  if ($url === '') {
    return '';
  }
  try {
    $response = \Drupal::httpClient()->get($url, [
      'timeout' => 15,
      'connect_timeout' => 8,
      'headers' => ['User-Agent' => 'odesign-blog-import/1.0'],
    ]);
    $html = (string) $response->getBody();
  }
  catch (\Throwable $e) {
    return '';
  }
  if ($html === '') {
    return '';
  }
  if (preg_match('/<meta[^>]*property=(["\'])og:image\1[^>]*content=(["\'])(.*?)\2/i', $html, $m) === 1) {
    return trim((string) $m[3]);
  }
  if (preg_match('/<meta[^>]*content=(["\'])(.*?)\1[^>]*property=(["\'])og:image\3/i', $html, $m) === 1) {
    return trim((string) $m[2]);
  }
  return '';
};

/**
 * Télécharge une image distante et la stocke dans public://shopify-blog.
 */
$downloadImageFile = static function (string $image_url): ?\Drupal\file\FileInterface {
  $image_url = trim($image_url);
  if ($image_url === '') {
    return NULL;
  }

  $path = (string) parse_url($image_url, PHP_URL_PATH);
  $basename = basename($path);
  if ($basename === '' || $basename === '/') {
    $basename = 'shopify-cover.jpg';
  }
  $basename = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $basename) ?? $basename;
  if (!preg_match('/\.(jpe?g|png|webp|gif)$/i', $basename)) {
    $basename .= '.jpg';
  }

  $directory = 'public://shopify-blog';
  \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
  $destination = $directory . '/' . $basename;

  try {
    $response = \Drupal::httpClient()->get($image_url, [
      'timeout' => 20,
      'connect_timeout' => 8,
      'headers' => ['User-Agent' => 'odesign-blog-import/1.0'],
    ]);
    $data = (string) $response->getBody();
    if ($data === '') {
      return NULL;
    }
    $file = \Drupal::service('file.repository')->writeData($data, $destination, FileExists::Replace);
    if (!$file) {
      return NULL;
    }
    $file->setPermanent();
    $file->save();
    return $file;
  }
  catch (\Throwable $e) {
    return NULL;
  }
};

/**
 * Tronque proprement un résumé.
 */
$buildSummary = static function (string $summary, string $body, int $max = 260): string {
  $summary = trim(strip_tags($summary));
  if ($summary === '') {
    $summary = trim(strip_tags($body));
  }
  $summary = preg_replace('/\s+/u', ' ', $summary) ?? $summary;
  if (mb_strlen($summary) <= $max) {
    return $summary;
  }
  return rtrim(mb_substr($summary, 0, $max - 1)) . '…';
};

/**
 * Crée les champs de traçabilité Shopify si absents.
 */
$ensureShopifyFields = static function () use ($entity_type_manager): void {
  if (!FieldStorageConfig::loadByName('node', 'field_shopify_article_id')) {
    FieldStorageConfig::create([
      'field_name' => 'field_shopify_article_id',
      'entity_type' => 'node',
      'type' => 'string',
      'cardinality' => 1,
    ])->save();
  }

  if (!FieldConfig::loadByName('node', 'article', 'field_shopify_article_id')) {
    FieldConfig::create([
      'field_name' => 'field_shopify_article_id',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Shopify Article ID',
      'required' => FALSE,
    ])->save();
  }

  if (!FieldStorageConfig::loadByName('node', 'field_shopify_article_url')) {
    FieldStorageConfig::create([
      'field_name' => 'field_shopify_article_url',
      'entity_type' => 'node',
      'type' => 'string',
      'cardinality' => 1,
    ])->save();
  }

  if (!FieldConfig::loadByName('node', 'article', 'field_shopify_article_url')) {
    FieldConfig::create([
      'field_name' => 'field_shopify_article_url',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Shopify Article URL',
      'required' => FALSE,
    ])->save();
  }
};

if (!is_file($source_path)) {
  echo "ERREUR: source JSON introuvable: {$source_path}\n";
  echo "Attendu: BLOG_SOURCE_JSON=/chemin/articles.json drush php:script scripts/create-blog-articles.php\n";
  return;
}

$raw = file_get_contents($source_path);
if ($raw === FALSE) {
  echo "ERREUR: impossible de lire {$source_path}\n";
  return;
}

$data = json_decode($raw, TRUE);
if (!is_array($data)) {
  echo "ERREUR: JSON invalide dans {$source_path}\n";
  return;
}

$articles = array_values(array_filter($data, static fn($row) => is_array($row)));
if ($articles === []) {
  echo "Aucun article exploitable dans {$source_path}\n";
  return;
}

if (!$dry_run) {
  $ensureShopifyFields();
}

$node_storage = $entity_type_manager->getStorage('node');
$has_shopify_id_field = (bool) FieldConfig::loadByName('node', 'article', 'field_shopify_article_id');
$body_field_config = FieldConfig::loadByName('node', 'article', 'body');
$allowed_body_formats = [];
if ($body_field_config instanceof FieldConfig) {
  $allowed_body_formats = (array) ($body_field_config->getSetting('allowed_formats') ?? []);
}

if ($allowed_body_formats !== []) {
  if (!in_array($requested_body_format, $allowed_body_formats, TRUE)) {
    $body_format = (string) reset($allowed_body_formats);
  }
}
elseif (!FilterFormat::load($requested_body_format)) {
  $body_format = 'full_html';
}

$stats = [
  'read' => count($articles),
  'created' => 0,
  'updated' => 0,
  'skipped' => 0,
  'errors' => 0,
];

echo "=== Import blog Shopify -> Drupal ===\n";
echo "Source   : {$source_path}\n";
echo "Dry-run  : " . ($dry_run ? 'oui' : 'non') . "\n";
echo "Strict URL Shopify : " . ($strict_shopify_url ? 'oui' : 'non') . "\n";
echo "Body format : {$body_format}\n";
echo "Articles : {$stats['read']}\n\n";

foreach ($articles as $idx => $item) {
  $title = $normalizeTitle(trim((string) $pick($item, ['title', 'name'])));
  $raw_handle = trim((string) $pick($item, ['handle', 'slug']));
  $shopify_url = trim((string) $pick($item, ['shopify_url', 'url', 'source_url']));
  $handle = $raw_handle !== '' ? $raw_handle : $slugify($title !== '' ? $title : ('article-' . ($idx + 1)));
  $fallback_alias = '/blog/' . $handle;
  $alias = $aliasFromShopifyUrl($shopify_url, $fallback_alias);

  if ($strict_shopify_url && $alias === $fallback_alias) {
    $stats['errors']++;
    echo "[ERR] #" . ($idx + 1) . " URL Shopify absente/invalide (alias strict non déterminable)\n";
    continue;
  }

  $raw_body = (string) $pick($item, ['body_html', 'body', 'content_html', 'html']);
  $body = $sanitizeHtml($raw_body);
  $summary = $buildSummary((string) $pick($item, ['excerpt', 'summary', 'excerpt_html']), $body);
  $cover_image_url = trim((string) $pick($item, [
    'image_url',
    'featured_image_url',
    'cover_image_url',
    'image',
    'featured_image',
    'cover_image',
  ]));
  if ($cover_image_url === '' && is_array($item['image'] ?? NULL)) {
    $cover_image_url = trim((string) ($item['image']['src'] ?? $item['image']['url'] ?? ''));
  }
  if ($cover_image_url === '') {
    $cover_image_url = $extractFirstImageFromHtml($raw_body);
  }
  if ($cover_image_url === '' && $shopify_url !== '') {
    $cover_image_url = $extractOgImageFromPage($shopify_url);
  }

  $shopify_id = $normalizeShopifyId((string) $pick($item, ['shopify_article_id', 'shopify_id', 'id']));
  $author = trim((string) $pick($item, ['author', 'author_name']));
  $published_at = trim((string) $pick($item, ['published_at', 'publishedAt', 'published']));

  if ($title === '' || $body === '') {
    $stats['skipped']++;
    echo "[SKIP] #" . ($idx + 1) . " titre ou body vide\n";
    continue;
  }

  try {
    $node = NULL;

    // 1) Idempotence par Shopify ID si disponible.
    if ($shopify_id !== '' && $has_shopify_id_field) {
      $existing = $node_storage->loadByProperties([
        'type' => 'article',
        'field_shopify_article_id' => $shopify_id,
      ]);
      if (!empty($existing)) {
        $node = reset($existing);
      }
    }

    // 2) Idempotence par alias /blog/{handle}.
    if (!$node instanceof Node) {
      $internal = $alias_manager->getPathByAlias($alias, 'fr');
      if (preg_match('#^/node/(\d+)$#', $internal, $m) === 1) {
        $candidate = Node::load((int) $m[1]);
        if ($candidate instanceof Node && $candidate->bundle() === 'article') {
          $node = $candidate;
        }
      }
    }

    // 3) Fallback par titre.
    if (!$node instanceof Node) {
      $existing = $node_storage->loadByProperties([
        'type' => 'article',
        'title' => $title,
      ]);
      if (!empty($existing)) {
        $node = reset($existing);
      }
    }

    $is_update = $node instanceof Node;
    if (!$is_update) {
      $node = Node::create([
        'type' => 'article',
        'langcode' => 'fr',
        'uid' => 1,
      ]);
    }

    $node->set('title', $title);
    $node->set('status', 1);
    $node->set('body', [
      'value' => $body,
      'summary' => $summary,
      'format' => $body_format,
    ]);
    $node->set('path', [
      'alias' => $alias,
      'pathauto' => 0,
    ]);

    if ($shopify_id !== '' && $node->hasField('field_shopify_article_id')) {
      $node->set('field_shopify_article_id', $shopify_id);
    }

    if ($shopify_url !== '' && $node->hasField('field_shopify_article_url')) {
      $node->set('field_shopify_article_url', $shopify_url);
    }

    if ($cover_image_url !== '' && $node->hasField('field_image')) {
      try {
        $file = $downloadImageFile($cover_image_url);
        if ($file) {
          $node->set('field_image', [
            'target_id' => $file->id(),
            'alt' => $title,
            'title' => $title,
          ]);
        }
      }
      catch (\Throwable $e) {
        echo "[WARN] image couverture non importée pour {$alias} : " . $e->getMessage() . "\n";
      }
    }

    if ($published_at !== '' && $node->hasField('created')) {
      try {
        $date = new DrupalDateTime($published_at);
        if (!$date->hasErrors()) {
          $ts = $date->getTimestamp();
          $node->setCreatedTime($ts);
          $node->setChangedTime($ts);
        }
      }
      catch (\Throwable $e) {
        // Non bloquant.
      }
    }

    if ($author !== '' && $node->hasField('revision_log')) {
      $node->setRevisionLogMessage('Import Shopify — auteur source: ' . $author);
    }

    if ($dry_run) {
      $action = $is_update ? 'UPDATE' : 'CREATE';
      echo "[DRY] {$action} {$alias} | {$title}\n";
      continue;
    }

    $node->setNewRevision(FALSE);
    $node->save();

    // Nettoyage post-import: conserver uniquement l'alias cible pour /node/{nid}.
    try {
      $path_alias_storage = $entity_type_manager->getStorage('path_alias');
      $node_path = '/node/' . $node->id();
      $aliases = $path_alias_storage->loadByProperties([
        'path' => $node_path,
        'langcode' => 'fr',
      ]);
      foreach ($aliases as $path_alias) {
        if ((string) $path_alias->getAlias() !== $alias) {
          $path_alias->delete();
        }
      }
    }
    catch (\Throwable $e) {
      echo "[WARN] nettoyage alias impossible pour nid={$node->id()} : " . $e->getMessage() . "\n";
    }

    // Compatibilité: maintenir l'ancien pattern /blog/{handle} en 301.
    try {
      if ($alias !== $fallback_alias) {
        $ensureRedirect($fallback_alias, $alias);
      }
    }
    catch (\Throwable $e) {
      echo "[WARN] redirection compat impossible pour nid={$node->id()} : " . $e->getMessage() . "\n";
    }

    // Post-traitement: forcer la publication quand Content Moderation est actif.
    if ($node->hasField('moderation_state')) {
      try {
        $moderation_state = (string) $node->get('moderation_state')->value;
        if ($moderation_state !== 'published') {
          $node->set('moderation_state', 'published');
          $node->set('status', 1);
          $node->setNewRevision(TRUE);
          $node->save();
        }
      }
      catch (\Throwable $e) {
        echo "[WARN] publication moderation impossible pour nid={$node->id()} : " . $e->getMessage() . "\n";
      }
    }

    if ($is_update) {
      $stats['updated']++;
      echo "[OK] UPDATE nid={$node->id()} {$alias}\n";
    }
    else {
      $stats['created']++;
      echo "[OK] CREATE nid={$node->id()} {$alias}\n";
    }
  }
  catch (\Throwable $e) {
    $stats['errors']++;
    $logger->error('Erreur import article #{idx} ({title}) : {msg}', [
      'idx' => $idx + 1,
      'title' => $title,
      'msg' => $e->getMessage(),
    ]);
    echo "[ERR] #" . ($idx + 1) . " {$title} : " . $e->getMessage() . "\n";
  }
}

echo "\n=== Résumé ===\n";
echo "Lus      : {$stats['read']}\n";
echo "Créés    : {$stats['created']}\n";
echo "MàJ      : {$stats['updated']}\n";
echo "Ignorés  : {$stats['skipped']}\n";
echo "Erreurs  : {$stats['errors']}\n";

if ($dry_run) {
  echo "\nDRY_RUN=1 : aucune écriture en base n'a été effectuée.\n";
}
