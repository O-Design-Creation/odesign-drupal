<?php

declare(strict_types=1);

use Drupal\commerce_product\Entity\Product;

/**
 * Assigne les modèles de personnalisation manquants sur 4 produits importés.
 *
 * Déclencheur : écarts vérifiés entre Shopify live et preprod lors de la
 * recette du 2026-04-13.
 */
function odesign_personalization_deploy_001(): void {
  $assignments = [
    'support-cartes-jeux-bois-personnalise' => 'import_d82d2bec',
    'puzzle-prenom-en-bois' => 'atelier_puzzle_prenom_motif',
    'support-bracelet-de-naissance-en-bois' => 'atelier_support_naissance_complet',
    'support-bracelet-naissance-nuage-bois' => 'atelier_support_naissance_complet',
  ];

  $template_storage = \Drupal::entityTypeManager()->getStorage('personalization_template');

  foreach ($assignments as $handle => $template_id) {
    $template = $template_storage->load($template_id);
    if (!$template) {
      throw new \RuntimeException(sprintf('Template de personnalisation introuvable: %s', $template_id));
    }

    $product = odesign_personalization_load_product_by_handle($handle);
    if (!$product) {
      throw new \RuntimeException(sprintf('Produit introuvable via le handle Shopify: %s', $handle));
    }

    if (!$product->hasField('field_personalization_template')) {
      throw new \RuntimeException(sprintf('Le produit %s (%s) ne porte pas field_personalization_template.', (string) $product->label(), $handle));
    }

    $current_template_id = $product->get('field_personalization_template')->target_id;
    if ($current_template_id === $template_id) {
      continue;
    }

    $product->set('field_personalization_template', ['target_id' => $template_id]);
    $product->save();

    \Drupal::logger('odesign_personalization')->notice(
      'deploy_001 : modèle @template affecté au produit @product via @handle (avant : @before).',
      [
        '@template' => $template_id,
        '@product' => (string) $product->label(),
        '@handle' => $handle,
        '@before' => $current_template_id ?: 'none',
      ]
    );
  }
}

/**
 * Résout un produit commerce à partir de son alias Shopify /products/{handle}.
 */
function odesign_personalization_load_product_by_handle(string $handle): ?Product {
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
