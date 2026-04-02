<?php

declare(strict_types=1);

use Drupal\Core\Database\Database;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\taxonomy\Entity\Term;

/**
 * Script idempotent de fiabilisation des univers life_occasion.
 *
 * Objectifs:
 * - garantir 1 terme canonique par alias univers
 * - fusionner les doublons de noms (ex: deux "Mariage")
 * - réaffecter les références produits vers le terme canonique
 * - nettoyer les alias parasites
 *
 * Usage:
 *   ddev drush scr scripts/fix-life-occasion-duplicates.php
 *   terminus drush atelierodesign.dev -- scr scripts/fix-life-occasion-duplicates.php
 */

$definitions = [
  '/occasions/mariage' => 'Mariage',
  '/occasions/naissance-bebe' => 'Naissance & bébé',
  '/occasions/cadeaux-personnalises-famille' => 'Cadeaux personnalisés / famille',
  '/occasions/pancarte-evenementielles' => 'Pancarte événementielles',
];

$term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
$alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
$database = Database::getConnection();
$schema = $database->schema();

/**
 * Normalisation robuste pour comparer les noms indépendamment des accents/casse.
 */
function normalize_life_name(string $value): string {
  $value = mb_strtolower(trim($value));
  $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
  $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
  return trim((string) $value);
}

foreach ($definitions as $alias => $expected_name) {
  $canonical = NULL;

  // 1) Si l'alias existe déjà, son terme devient canonique.
  $alias_ids = $alias_storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('alias', $alias)
    ->sort('id', 'DESC')
    ->range(0, 1)
    ->execute();
  if ($alias_ids) {
    $alias_entity = $alias_storage->load(reset($alias_ids));
    $internal_path = (string) $alias_entity?->getPath();
    if (preg_match('#^/taxonomy/term/(\d+)$#', $internal_path, $m)) {
      $candidate = $term_storage->load((int) $m[1]);
      if ($candidate instanceof Term && $candidate->bundle() === 'life_occasion') {
        $canonical = $candidate;
      }
    }
  }

  // 2) Sinon, reprendre un terme du même nom.
  if (!$canonical) {
    $ids_by_name = $term_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'life_occasion')
      ->condition('name', $expected_name)
      ->sort('tid')
      ->range(0, 1)
      ->execute();
    if ($ids_by_name) {
      $canonical = $term_storage->load(reset($ids_by_name));
    }
  }

  // 3) Sinon, créer le terme manquant.
  if (!$canonical) {
    $canonical = Term::create([
      'vid' => 'life_occasion',
      'name' => $expected_name,
      'status' => 1,
    ]);
    $canonical->save();
    print sprintf("[CREATE] %s (#%d)\n", $expected_name, $canonical->id());
  }

  // Harmonise le libellé canonique (corrige les variantes d'encodage).
  if ($canonical->label() !== $expected_name) {
    $canonical->setName($expected_name);
    $canonical->save();
    print sprintf("[RENAME] #%d -> %s\n", $canonical->id(), $expected_name);
  }

  $canonical_id = (int) $canonical->id();
  $normalized = normalize_life_name($expected_name);

  // Cherche tous les doublons portant le même nom logique.
  $all_ids = $term_storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('vid', 'life_occasion')
    ->execute();
  $duplicates = [];
  foreach ($term_storage->loadMultiple($all_ids) as $term) {
    $term_id = (int) $term->id();
    if ($term_id === $canonical_id) {
      continue;
    }
    if (normalize_life_name((string) $term->label()) === $normalized) {
      $duplicates[] = $term_id;
    }
  }

  // Réaffecte les produits qui pointent encore vers les doublons.
  if ($duplicates && $schema->tableExists('commerce_product__field_life_occasion')) {
    $database->update('commerce_product__field_life_occasion')
      ->fields(['field_life_occasion_target_id' => $canonical_id])
      ->condition('field_life_occasion_target_id', $duplicates, 'IN')
      ->execute();
  }

  // Supprime les alias non canoniques liés aux doublons.
  foreach ($duplicates as $duplicate_id) {
    $dup_path = '/taxonomy/term/' . $duplicate_id;
    $dup_alias_ids = $alias_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('path', $dup_path)
      ->execute();
    foreach ($alias_storage->loadMultiple($dup_alias_ids) as $dup_alias) {
      $dup_alias->delete();
    }
  }

  // Pose l'alias canonique unique sur le terme canonique.
  $canonical_path = '/taxonomy/term/' . $canonical_id;
  $alias_conflicts = $alias_storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('alias', $alias)
    ->execute();
  foreach ($alias_storage->loadMultiple($alias_conflicts) as $existing_alias) {
    $existing_alias->delete();
  }

  PathAlias::create([
    'path' => $canonical_path,
    'alias' => $alias,
    'langcode' => 'fr',
  ])->save();

  // Supprime les termes doublons après migration des références.
  foreach ($duplicates as $duplicate_id) {
    $duplicate_term = $term_storage->load($duplicate_id);
    if ($duplicate_term) {
      $duplicate_term->delete();
      print sprintf("[MERGE] %s #%d -> #%d\n", $expected_name, $duplicate_id, $canonical_id);
    }
  }

  print sprintf("[OK] %s => %s (tid=%d)\n", $alias, $expected_name, $canonical_id);
}

if ($schema->tableExists('commerce_product__field_life_occasion')) {
  $rows = $database->query('SELECT field_life_occasion_target_id, COUNT(*) AS c FROM {commerce_product__field_life_occasion} GROUP BY field_life_occasion_target_id ORDER BY field_life_occasion_target_id')->fetchAllKeyed(0, 1);
  print "[MAP] field_life_occasion_target_id => count\n";
  foreach ($rows as $tid => $count) {
    print sprintf("  - %d => %d\n", (int) $tid, (int) $count);
  }
}
