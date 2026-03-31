<?php
/**
 * Remplit le champ body.summary de tous les produits
 * depuis products_enrichment.json (SEO Description Shopify).
 */
$enrichmentFile = __DIR__ . '/products_enrichment.json';
$enrichments = json_decode(file_get_contents($enrichmentFile), TRUE);
$byHandle = [];
foreach ($enrichments as $e) {
  $byHandle[$e['handle']] = $e;
}

$aliasManager = \Drupal::service('path_alias.manager');
$storage = \Drupal::entityTypeManager()->getStorage('commerce_product');
$products = $storage->loadMultiple();

$stats = ['set' => 0, 'already' => 0, 'no_match' => 0];

foreach ($products as $product) {
  $alias = $aliasManager->getAliasByPath('/product/' . $product->id());
  $handle = $alias ? basename($alias) : NULL;

  // Fallback similar_text
  $data = $byHandle[$handle] ?? NULL;
  if (!$data) {
    foreach ($byHandle as $csvHandle => $csvData) {
      similar_text((string)$handle, $csvHandle, $pct);
      if ($pct > 85) { $data = $csvData; break; }
    }
  }

  if (!$data || empty($data['seo_desc'])) {
    $stats['no_match']++;
    continue;
  }

  $body = $product->get('body');
  $currentSummary = trim($body->summary ?? '');

  if (!empty($currentSummary)) {
    $stats['already']++;
    continue;
  }

  // Tronquer la description à 160 caractères pour le summary SEO
  $summary = mb_substr($data['seo_desc'], 0, 160);
  if (mb_strlen($data['seo_desc']) > 160) $summary .= '…';

  $product->set('body', [
    'value'   => $body->value ?: $data['seo_desc'],
    'summary' => $summary,
    'format'  => $body->format ?: 'basic_html',
  ]);
  $product->save();
  $stats['set']++;
}

echo "Summary rempli  : {$stats['set']} produits\n";
echo "Déjà rempli     : {$stats['already']} produits\n";
echo "Sans match CSV  : {$stats['no_match']} produits\n";
echo "\n✅ Terminé\n";
