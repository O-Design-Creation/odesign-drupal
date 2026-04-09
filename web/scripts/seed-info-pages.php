<?php

declare(strict_types=1);

use Drupal\node\Entity\Node;

/**
 * Crée ou met à jour les pages informationnelles de base.
 *
 * Usage:
 *   drush php:script web/scripts/seed-info-pages.php
 */

$entity_type_manager = \Drupal::entityTypeManager();

$loadNodeByAlias = static function (string $alias): ?Node {
  $internal = \Drupal::service('path_alias.manager')->getPathByAlias($alias, 'fr');
  if (preg_match('#^/node/(\d+)$#', $internal, $matches) === 1) {
    $node = Node::load((int) $matches[1]);
    return $node instanceof Node ? $node : NULL;
  }
  return NULL;
};

$upsertPage = static function (
  string $title,
  string $alias,
  string $body_html
) use ($entity_type_manager, $loadNodeByAlias): Node {
  $storage = $entity_type_manager->getStorage('node');
  $node = $loadNodeByAlias($alias);

  if (!$node) {
    $existing = $storage->loadByProperties([
      'type' => 'page',
      'title' => $title,
      'langcode' => 'fr',
    ]);
    $node = $existing ? reset($existing) : NULL;
  }

  if (!$node instanceof Node) {
    $node = Node::create([
      'type' => 'page',
      'langcode' => 'fr',
      'status' => 1,
      'title' => $title,
    ]);
  }

  $node->set('title', $title);
  $node->set('status', 1);
  $node->set('body', [
    'value' => $body_html,
    'format' => 'full_html',
  ]);
  $node->set('path', [
    'alias' => $alias,
    'pathauto' => 0,
  ]);
  $node->save();

  return $node;
};

$contact_body = <<<HTML
<h2>Contacter l'atelier</h2>
<p>Une question sur un produit, une personnalisation, un délai ou une commande ? Contactez l'atelier O Design.</p>
<ul>
  <li><strong>Email :</strong> odesigncrea@gmail.com</li>
  <li><strong>Téléphone :</strong> à compléter</li>
  <li><strong>Instagram :</strong> à compléter</li>
</ul>
<p>Nous vous répondons le plus rapidement possible.</p>
<p><em>Cette page est modifiable à tout moment depuis l'administration Drupal.</em></p>
HTML;

$atelier_body = <<<HTML
<h2>L'atelier d'Océane</h2>
<p>O'Design Création est un atelier physique de créations personnalisées en bois.</p>
<ul>
  <li><strong>Adresse :</strong> à compléter</li>
  <li><strong>Horaires :</strong> à compléter</li>
  <li><strong>Retrait / accueil :</strong> à compléter</li>
</ul>
<p>Chaque réalisation est préparée avec soin à l'atelier.</p>
<p><em>Cette page est modifiable à tout moment depuis l'administration Drupal.</em></p>
HTML;

$contact = $upsertPage('Contact', '/contact', $contact_body);
$atelier = $upsertPage('Atelier Océane', '/atelier', $atelier_body);

print sprintf(
  "Pages OK: Contact #%d (%s), Atelier #%d (%s)\n",
  (int) $contact->id(),
  '/contact',
  (int) $atelier->id(),
  '/atelier'
);

