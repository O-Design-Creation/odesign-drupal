<?php
// Ajoute un résumé (summary) aux articles de blog pour alimenter og:description
$storage = \Drupal::entityTypeManager()->getStorage('node');
$articles = $storage->loadByProperties(['type' => 'article']);

$summaries = [
  'Comment personnaliser une carte de naissance en bois' =>
    'Découvrez nos conseils pour personnaliser une carte de naissance en bois gravée au laser : prénom, date, poids, taille et motif au choix. Livraison rapide, fabriqué en France.',
  'Idées cadeaux de naissance originaux en bois' =>
    'Sélection de cadeaux de naissance originaux en bois personnalisés : cartes de naissance, supports de bracelet, magnets. Des souvenirs uniques pour accueillir bébé.',
  'Nos techniques de gravure laser sur bois' =>
    'Tout savoir sur la gravure laser sur bois chez O'Design Création : types de bois, profondeur de gravure, finitions et personnalisation. Artisanat français de qualité.',
];

foreach ($articles as $article) {
  $title = $article->getTitle();
  $summary = null;
  foreach ($summaries as $key => $text) {
    similar_text(strtolower($title), strtolower($key), $pct);
    if ($pct > 70) { $summary = $text; break; }
  }
  if ($summary) {
    $body = $article->get('body');
    $article->set('body', [
      'value'   => $body->value ?: $summary,
      'summary' => $summary,
      'format'  => $body->format ?: 'basic_html',
    ]);
    $article->save();
    echo "  ✅ [" . $article->id() . "] Résumé ajouté : " . substr($title, 0, 50) . "\n";
  }
}
echo "\n✅ Résumés mis à jour.\n";
