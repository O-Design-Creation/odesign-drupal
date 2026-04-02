<?php

declare(strict_types=1);

use Drupal\path_alias\Entity\PathAlias;
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

foreach ($definitions as $machine_name => $definition) {
  $term_ids = $term_storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('vid', 'life_occasion')
    ->condition('name', $definition['name'])
    ->range(0, 1)
    ->execute();

  /** @var \Drupal\taxonomy\Entity\Term $term */
  $term = $term_ids ? $term_storage->load(reset($term_ids)) : Term::create([
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
  $alias_value = $definition['alias'];

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
