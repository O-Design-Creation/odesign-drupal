<?php

declare(strict_types=1);

use Drupal\commerce_product\Entity\Product;

/**
 * Corrige l'assignation du template Arbre de vie sans dépendre d'IDs hardcodés.
 *
 * Cible les produits par alias Shopify (stable entre environnements) :
 * - /products/arbre-de-vie-personnalise (doit porter le template)
 * - /products/carte-naissance-ovale-bois (peut servir de source de template
 *   mais peut aussi porter légitimement son propre template simple)
 */

$target_handle = getenv('TARGET_HANDLE') ?: 'arbre-de-vie-personnalise';
$legacy_handle = getenv('LEGACY_HANDLE') ?: 'carte-naissance-ovale-bois';
$fallback_template_id = getenv('TREE_TEMPLATE_ID') ?: 'import_9b69e1cb';

/**
 * Résout un produit commerce via son alias /products/{handle}.
 */
function load_product_by_handle(string $handle): ?Product {
  $alias = '/products/' . trim($handle);

  $paths = \Drupal::database()->select('path_alias', 'pa')
    ->fields('pa', ['path'])
    ->condition('alias', $alias)
    ->execute()
    ->fetchCol();

  foreach ($paths as $path) {
    if (preg_match('#^/product/(\d+)$#', (string) $path, $matches)) {
      $product = Product::load((int) $matches[1]);
      if ($product) {
        return $product;
      }
    }
  }

  return NULL;
}

$target = load_product_by_handle($target_handle);
if (!$target) {
  throw new \RuntimeException("Produit cible introuvable via handle: {$target_handle}");
}
if (!$target->hasField('field_personalization_template')) {
  throw new \RuntimeException("Le produit cible {$target->id()} n'a pas field_personalization_template.");
}

$legacy = load_product_by_handle($legacy_handle);
if ($legacy && !$legacy->hasField('field_personalization_template')) {
  throw new \RuntimeException("Le produit legacy {$legacy->id()} n'a pas field_personalization_template.");
}

// Détermine le template à appliquer au produit cible.
$template = NULL;
if (!$target->get('field_personalization_template')->isEmpty()) {
  $template = $target->get('field_personalization_template')->entity;
}
if (!$template && $legacy && !$legacy->get('field_personalization_template')->isEmpty()) {
  $template = $legacy->get('field_personalization_template')->entity;
}
if (!$template) {
  $template = \Drupal::entityTypeManager()->getStorage('personalization_template')->load($fallback_template_id);
}
if (!$template) {
  throw new \RuntimeException("Aucun template trouvé (legacy vide, target vide, fallback introuvable: {$fallback_template_id}).");
}

$target_before = $target->get('field_personalization_template')->target_id ?? 'none';
$legacy_before = $legacy ? ($legacy->get('field_personalization_template')->target_id ?? 'none') : 'n/a';

$target->set('field_personalization_template', ['target_id' => $template->id()]);
$target->save();

$target_after = $target->get('field_personalization_template')->target_id ?? 'none';
$legacy_after = $legacy ? ($legacy->get('field_personalization_template')->target_id ?? 'none') : 'n/a';

print "OK: target handle={$target_handle} id={$target->id()} template {$target_before} -> {$target_after}\n";
if ($legacy) {
  print "OK: legacy handle={$legacy_handle} id={$legacy->id()} template {$legacy_before} -> {$legacy_after}\n";
}
print "Template retenu: {$template->id()} ({$template->label()})\n";
