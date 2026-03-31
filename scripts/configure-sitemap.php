<?php
// Configure simple_sitemap for key entity types
$generator = \Drupal::service('simple_sitemap.generator');

// Enable sitemap for node content types
$bundles = [
  'node' => ['article', 'page', 'product'],
  'commerce_product' => ['default'],
];

foreach ($bundles as $entityType => $bundleList) {
  foreach ($bundleList as $bundle) {
    try {
      $generator->setVariants(['default']);
      $generator->entityManager()->setBundleSettings($entityType, $bundle, [
        'index' => TRUE,
        'priority' => ($entityType === 'commerce_product') ? '0.9' : '0.7',
        'changefreq' => ($entityType === 'commerce_product') ? 'weekly' : 'monthly',
        'include_images' => TRUE,
      ]);
      echo "Sitemap configuré pour $entityType/$bundle\n";
    } catch (\Exception $e) {
      echo "Erreur pour $entityType/$bundle: " . $e->getMessage() . "\n";
    }
  }
}

// Generate sitemap
$generator->generate('backend');
echo "Sitemap généré\n";
