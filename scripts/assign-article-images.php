<?php
/**
 * Assigne des images de couverture aux articles de démo.
 * Utilise les images produit déjà présentes dans file_managed.
 * Idempotent : ne réassigne pas si déjà une image.
 */

use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;

$assignments = [
  'Comment personnaliser une carte de naissance en bois' => 'carte-naissance-ovale-bois-1.webp',
  'Idées cadeaux de naissance originaux en bois' => 'cadre-jardin-fleuri-personnalise-bois-1.webp',
  'Nos techniques de gravure laser sur bois' => 'cadre-photo-bois-personnalise-1.webp',
];

foreach ($assignments as $title => $filename) {
  $nodes = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->loadByProperties([
      'type' => 'article',
      'title' => $title,
    ]);

  $node = $nodes ? reset($nodes) : NULL;
  if (!$node) {
    echo "⚠️  Article introuvable : $title\n";
    continue;
  }

  if (!$node->get('field_image')->isEmpty()) {
    $existing = $node->get('field_image')->target_id;
    echo "ℹ️  Article \"" . $node->getTitle() . "\" déjà une image (fid=$existing) — ignoré\n";
    continue;
  }

  $files = \Drupal::entityTypeManager()
    ->getStorage('file')
    ->loadByProperties(['filename' => $filename]);

  $file = NULL;
  foreach ($files as $candidate) {
    if ($candidate instanceof File && str_contains($candidate->getFileUri(), $filename)) {
      $file = $candidate;
      break;
    }
  }

  if (!$file && $files) {
    $file = reset($files);
  }

  if (!$file) {
    echo "⚠️  Fichier image introuvable : $filename pour \"" . $node->getTitle() . "\"\n";
    continue;
  }

  $node->set('field_image', ['target_id' => $file->id(), 'alt' => $node->getTitle()]);
  $node->setNewRevision(FALSE);
  $node->save();
  echo "✅ Article \"" . $node->getTitle() . "\" → image fid=" . $file->id() . " assignée\n";
}

echo "Terminé.\n";
