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

  foreach ($assignments as $handle => $template_id) {
    odesign_personalization_assign_template_by_handle($handle, $template_id, 'deploy_001');
  }
}

/**
 * Rétablit la personnalisation simple "Prénom" sur la carte ovale.
 *
 * Déclencheur : `TCK-140` vérifié sur preprod après correction de `TCK-132`.
 */
function odesign_personalization_deploy_002(): void {
  odesign_personalization_assign_template_by_handle(
    'carte-naissance-ovale-bois',
    'import_d82d2bec',
    'deploy_002'
  );
}

/**
 * Corrige la description mojibake de la plaque de chaise.
 *
 * Déclencheur : `TCK-142` vérifié sur preprod et source catalogue locale.
 */
function odesign_personalization_deploy_003(): void {
  odesign_personalization_replace_body_text_by_handle(
    'plaque-chaise-mariage-bois',
    'Cette d��coration peut être personnalisée avec le texte de votre choix, par exemple :',
    'Cette décoration peut être personnalisée avec le texte de votre choix, par exemple :',
    'deploy_003'
  );
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

/**
 * Affecte un modèle à un produit résolu par handle Shopify.
 */
function odesign_personalization_assign_template_by_handle(string $handle, string $template_id, string $context): void {
  $template_storage = \Drupal::entityTypeManager()->getStorage('personalization_template');
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
    return;
  }

  $product->set('field_personalization_template', ['target_id' => $template_id]);
  $product->save();

  \Drupal::logger('odesign_personalization')->notice(
    '@context : modèle @template affecté au produit @product via @handle (avant : @before).',
    [
      '@context' => $context,
      '@template' => $template_id,
      '@product' => (string) $product->label(),
      '@handle' => $handle,
      '@before' => $current_template_id ?: 'none',
    ]
  );
}

/**
 * Remplace un extrait exact dans le body d'un produit résolu par handle Shopify.
 */
function odesign_personalization_replace_body_text_by_handle(string $handle, string $search, string $replace, string $context): void {
  $product = odesign_personalization_load_product_by_handle($handle);
  if (!$product) {
    throw new \RuntimeException(sprintf('Produit introuvable via le handle Shopify: %s', $handle));
  }

  if (!$product->hasField('body')) {
    throw new \RuntimeException(sprintf('Le produit %s (%s) ne porte pas le champ body.', (string) $product->label(), $handle));
  }

  $body = $product->get('body')->first();
  $current_value = (string) ($body?->value ?? '');
  if (!str_contains($current_value, $search)) {
    return;
  }

  $product->set('body', [
    'value' => str_replace($search, $replace, $current_value),
    'summary' => (string) ($body?->summary ?? ''),
    'format' => (string) ($body?->format ?: 'full_html'),
  ]);
  $product->save();

  \Drupal::logger('odesign_personalization')->notice(
    '@context : body corrigé pour le produit @product via @handle.',
    [
      '@context' => $context,
      '@product' => (string) $product->label(),
      '@handle' => $handle,
    ]
  );
}
