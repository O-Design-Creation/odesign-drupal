<?php
use Drupal\block\Entity\Block;

// Place facet blocks in sidebar_first region on search page.
$blocks_to_place = [
  'facets_block_life_occasion_facet' => [
    'plugin' => 'facets_block:life_occasion_facet',
    'label' => 'Occasion',
    'region' => 'sidebar_first',
    'weight' => -10,
  ],
  'facets_block_category_recherche_facet' => [
    'plugin' => 'facets_block:category_recherche_facet',
    'label' => 'Catégorie',
    'region' => 'sidebar_first',
    'weight' => -9,
  ],
];

$theme = \Drupal::service('theme.manager')->getActiveTheme()->getName();

foreach ($blocks_to_place as $block_id => $config) {
  if (!Block::load($block_id)) {
    $block = Block::create([
      'id' => $block_id,
      'plugin' => $config['plugin'],
      'region' => $config['region'],
      'theme' => 'odesign_base',
      'weight' => $config['weight'],
      'settings' => [
        'label' => $config['label'],
        'label_display' => 'visible',
        'id' => $config['plugin'],
        'provider' => 'facets',
      ],
      'visibility' => [
        'request_path' => [
          'id' => 'request_path',
          'negate' => false,
          'pages' => '/recherche' . "\n" . '/recherche/*',
        ],
      ],
    ]);
    $block->save();
    echo "Placed block: $block_id\n";
  } else {
    echo "Block already exists: $block_id\n";
  }
}
