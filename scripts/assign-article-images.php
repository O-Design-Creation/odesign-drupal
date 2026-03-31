<?php
/**
 * Assigne des images de couverture aux articles de démo.
 * Utilise les images produit déjà présentes dans file_managed.
 * Idempotent : ne réassigne pas si déjà une image.
 */

use Drupal\node\Entity\Node;

$assignments = [
  // nid => fid  (utilise les images produit pertinentes)
  4 => 1,   // "Personnaliser une carte de naissance" → carte-naissance-ovale-bois-1.webp
  5 => 6,   // "Idées cadeaux de naissance"          → cadre-jardin-fleuri-personnalise-bois-1.webp
  6 => 11,  // "Gravure laser sur bois"               → cadre-photo-bois-personnalise-1.webp
];

foreach ($assignments as $nid => $fid) {
  $node = Node::load($nid);
  if (!$node) {
    echo "⚠️  Nœud $nid introuvable\n";
    continue;
  }

  // Ne pas écraser si déjà une image
  if (!$node->get('field_image')->isEmpty()) {
    $existing = $node->get('field_image')->target_id;
    echo "ℹ️  Article $nid déjà une image (fid=$existing) — ignoré\n";
    continue;
  }

  $node->set('field_image', ['target_id' => $fid, 'alt' => $node->getTitle()]);
  $node->setNewRevision(FALSE);
  $node->save();
  echo "✅ Article $nid → image fid=$fid assignée\n";
}

echo "Terminé.\n";
