<?php
/**
 * Fixes:
 * 1. Article blog : renseigne body.summary pour les 3 articles de démo
 * 2. CGV, Mentions légales : ajoute meta description via champ body.summary
 */

// --- Articles blog ---
$articleSummaries = [
  'comment-personnaliser-une-carte-de-naissance-en-bois' => 'Découvrez nos conseils pour personnaliser votre carte de naissance en bois : choix du prénom, des couleurs et des motifs pour un souvenir unique et mémorable.',
  'idees-cadeaux-naissance-originaux' => 'Explorez notre sélection de cadeaux de naissance originaux et personnalisés : faire-part en bois, décorations et souvenirs artisanaux fabriqués en France.',
  'decoration-mariage-naturelle-bois' => 'Découvrez comment créer une décoration de mariage naturelle et élégante avec des éléments en bois personnalisés, pour un mariage inoubliable et authentique.',
];

$aliasManager = \Drupal::service('path_alias.manager');
$nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
$nodes = $nodeStorage->loadByProperties(['type' => 'article']);

foreach ($nodes as $node) {
  $alias = $aliasManager->getAliasByPath('/node/' . $node->id());
  $slug = $alias ? basename($alias) : null;
  if (isset($articleSummaries[$slug])) {
    $body = $node->get('body');
    if (empty(trim($body->summary ?? ''))) {
      $node->set('body', [
        'value'   => $body->value ?: $articleSummaries[$slug],
        'summary' => $articleSummaries[$slug],
        'format'  => $body->format ?: 'basic_html',
      ]);
      $node->save();
      echo "Article '$slug' : summary ajouté ✅\n";
    } else {
      echo "Article '$slug' : summary déjà présent\n";
    }
  }
}

// --- Pages légales (body.summary) ---
$legalDescriptions = [
  'cgv' => 'Consultez les conditions générales de vente d\'Atelier O Design : modalités de commande, livraison, retours et paiement pour nos produits artisanaux personnalisés.',
  'mentions-legales' => 'Mentions légales d\'Atelier O Design : informations sur l\'éditeur du site, l\'hébergeur, et les données personnelles conformément à la réglementation française.',
];

$pageNodes = $nodeStorage->loadByProperties(['type' => 'page']);
foreach ($pageNodes as $node) {
  $alias = $aliasManager->getAliasByPath('/node/' . $node->id());
  $slug = $alias ? basename($alias) : null;
  if (isset($legalDescriptions[$slug])) {
    $body = $node->get('body');
    if (empty(trim($body->summary ?? ''))) {
      $node->set('body', [
        'value'   => $body->value ?: $legalDescriptions[$slug],
        'summary' => $legalDescriptions[$slug],
        'format'  => $body->format ?: 'basic_html',
      ]);
      $node->save();
      echo "Page '$slug' : summary ajouté ✅\n";
    } else {
      echo "Page '$slug' : summary déjà présent\n";
    }
  }
}

echo "\n✅ Terminé\n";
