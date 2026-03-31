<?php
/**
 * Migre le format body de full_html vers basic_html sur les articles.
 * Crée le format full_html via une config propre si absent.
 */
$filterStorage = \Drupal::entityTypeManager()->getStorage('filter_format');

// Créer full_html si absent
$full = $filterStorage->load('full_html');
if (!$full) {
  $full = \Drupal\filter\Entity\FilterFormat::create([
    'format'  => 'full_html',
    'name'    => 'Full HTML',
    'weight'  => 1,
    'filters' => [
      'filter_html_escape' => ['status' => 0],
      'filter_autop'       => ['status' => 1, 'weight' => 0],
      'filter_url'         => ['status' => 1, 'weight' => 0, 'settings' => ['filter_url_length' => 72]],
      'filter_htmlcorrector' => ['status' => 1, 'weight' => 10],
    ],
  ]);
  $full->save();
  echo "Format full_html créé ✅\n";
} else {
  echo "Format full_html existe déjà ✅\n";
}

\Drupal::service('cache.config')->invalidateAll();
\Drupal::service('router.builder')->rebuild();

// Vérification du token après fix
$nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
$nodes = $nodeStorage->loadByProperties(['type' => 'article']);
foreach ($nodes as $node) {
  $token = \Drupal::service('token')->replace('[node:summary]', ['node' => $node]);
  echo "  [node:summary] pour «" . $node->label() . "» : " . substr($token, 0, 100) . "\n";
}
echo "\n✅ Terminé\n";
