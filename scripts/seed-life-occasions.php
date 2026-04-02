<?php

declare(strict_types=1);

use Drupal\path_alias\Entity\PathAlias;
use Drupal\Core\Database\Database;
use Drupal\taxonomy\Entity\Term;

$definitions = [
  'mariage' => [
    'name' => 'Mariage',
    'weight' => 0,
    'description' => "Univers principal pour les décorations, cadeaux et détails personnalisés autour du mariage.",
    'intro' => "Une entrée claire pour retrouver rapidement les créations personnalisées liées au mariage : décorations, cadeaux, signalétique et petites attentions en bois.",
    'reassurance' => "Atelier réel et fabrication soignée\nPersonnalisation lisible pour les prénoms, dates et messages\nBase idéale pour préparer ensuite des mises en avant saisonnières",
    'alias' => '/occasions/mariage',
  ],
  'naissance-bebe' => [
    'name' => 'Naissance & bébé',
    'weight' => 5,
    'description' => "Univers pour les créations personnalisées autour de la naissance, du bébé et des premiers souvenirs.",
    'intro' => "Une page pensée pour les cadeaux de naissance, les souvenirs de bébé et les créations personnalisées à offrir ou à garder.",
    'reassurance' => "Idées cadeaux faciles à comprendre\nPersonnalisations douces et affectives\nProduits adaptés à une communication rassurante",
    'alias' => '/occasions/naissance-bebe',
  ],
  'cadeaux-personnalises-famille' => [
    'name' => 'Cadeaux personnalisés / famille',
    'weight' => 10,
    'description' => "Univers pour les cadeaux personnalisés du quotidien, la famille et les objets à offrir.",
    'intro' => "Une entrée simple pour les idées cadeaux personnalisées, les objets de famille et les attentions en bois à offrir toute l’année.",
    'reassurance' => "Univers facile à enrichir avec les temps forts\nBase utile pour les fêtes et cadeaux du quotidien\nNavigation plus simple qu’un catalogue purement technique",
    'alias' => '/occasions/cadeaux-personnalises-famille',
  ],
];

$term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
$logger = \Drupal::logger('odesign');
$database = Database::getConnection();
$schema = $database->schema();

/**
 * Normalise un libellé pour comparaison robuste (accents, casse, espaces).
 */
function normalize_life_occasion_name(string $value): string {
  $value = mb_strtolower(trim($value));
  $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
  $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
  return trim((string) $value);
}

foreach ($definitions as $machine_name => $definition) {
  $alias_value = $definition['alias'];
  $canonical_term = NULL;

  // 1) Priorité au terme déjà lié à l'alias canonique (source de vérité URL).
  $alias_ids = $alias_storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('alias', $alias_value)
    ->range(0, 1)
    ->execute();
  if ($alias_ids) {
    /** @var \Drupal\path_alias\PathAliasInterface $alias_entity */
    $alias_entity = $alias_storage->load(reset($alias_ids));
    $alias_path = (string) $alias_entity->getPath();
    if (preg_match('#^/taxonomy/term/(\d+)$#', $alias_path, $matches)) {
      $candidate = $term_storage->load((int) $matches[1]);
      if ($candidate instanceof Term && $candidate->bundle() === 'life_occasion') {
        $canonical_term = $candidate;
      }
    }
  }

  // 2) Fallback par nom exact si aucun alias canonique existant.
  if (!$canonical_term) {
    $term_ids = $term_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'life_occasion')
      ->condition('name', $definition['name'])
      ->range(0, 1)
      ->execute();
    if ($term_ids) {
      $canonical_term = $term_storage->load(reset($term_ids));
    }
  }

  /** @var \Drupal\taxonomy\Entity\Term $term */
  $term = $canonical_term ?: Term::create([
    'vid' => 'life_occasion',
    'name' => $definition['name'],
    'status' => 1,
  ]);

  $term->setDescription($definition['description']);
  $term->set('weight', $definition['weight']);

  if ($term->hasField('field_intro')) {
    $term->set('field_intro', [
      'value' => $definition['intro'],
      'format' => 'plain_text',
    ]);
  }

  if ($term->hasField('field_reassurance')) {
    $term->set('field_reassurance', [
      'value' => $definition['reassurance'],
      'format' => 'plain_text',
    ]);
  }

  $term->save();

  $internal_path = '/taxonomy/term/' . $term->id();

  // Fusionne les doublons de termes (même nom normalisé) vers le terme canonique.
  $all_ids = $term_storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('vid', 'life_occasion')
    ->execute();
  $all_terms = $term_storage->loadMultiple($all_ids);
  $canonical_name = normalize_life_occasion_name($definition['name']);
  $duplicate_ids = [];
  foreach ($all_terms as $candidate_term) {
    if ((int) $candidate_term->id() === (int) $term->id()) {
      continue;
    }
    if (normalize_life_occasion_name((string) $candidate_term->label()) === $canonical_name) {
      $duplicate_ids[] = (int) $candidate_term->id();
    }
  }

  if ($duplicate_ids && $schema->tableExists('commerce_product__field_life_occasion')) {
    $database->update('commerce_product__field_life_occasion')
      ->fields(['field_life_occasion_target_id' => (int) $term->id()])
      ->condition('field_life_occasion_target_id', $duplicate_ids, 'IN')
      ->execute();
  }

  foreach ($duplicate_ids as $duplicate_id) {
    $duplicate_path = '/taxonomy/term/' . $duplicate_id;
    $duplicate_alias_ids = $alias_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('path', $duplicate_path)
      ->execute();
    foreach ($alias_storage->loadMultiple($duplicate_alias_ids) as $duplicate_alias) {
      $duplicate_alias->delete();
    }

    $duplicate_term = $term_storage->load($duplicate_id);
    if ($duplicate_term) {
      $duplicate_term->delete();
      print sprintf("[MERGE] Doublon '%s' #%d -> #%d\n", $definition['name'], $duplicate_id, $term->id());
    }
  }

  $existing_alias_ids = $alias_storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('path', $internal_path)
    ->execute();

  foreach ($alias_storage->loadMultiple($existing_alias_ids) as $existing_alias) {
    $existing_alias->delete();
  }

  PathAlias::create([
    'path' => $internal_path,
    'alias' => $alias_value,
    'langcode' => 'fr',
  ])->save();

  print sprintf("[OK] %s -> %s\n", $definition['name'], $alias_value);
}

$logger->notice('Seed des univers occasions de vie exécuté.');
