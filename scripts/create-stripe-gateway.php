<?php
/**
 * Script drush scr : crée la gateway Stripe Payment Element en mode test.
 *
 * Usage: ddev drush scr scripts/create-stripe-gateway.php
 *
 * Les clés API sont des placeholders.
 * Remplacer par les vraies clés via settings.php (voir commentaire en fin de fichier).
 */

use Drupal\commerce_payment\Entity\PaymentGateway;

$gateway_id = 'stripe_sandbox';

if (PaymentGateway::load($gateway_id)) {
  echo "Gateway '$gateway_id' already exists — skipping.\n";
  return;
}

$gateway = PaymentGateway::create([
  'id'     => $gateway_id,
  'label'  => 'Carte bancaire (Stripe)',
  'plugin' => 'stripe_payment_element',
  'configuration' => [
    'mode'              => 'test',
    'publishable_key'   => 'pk_test_PLACEHOLDER',
    'secret_key'        => 'sk_test_PLACEHOLDER',
    'webhook_secret'    => '',
    'payment_method_types' => ['card'],
  ],
  'conditions'          => [],
  'conditionOperator'   => 'AND',
  'status'              => TRUE,
  'weight'              => 0,
]);

$gateway->save();
echo "Gateway '$gateway_id' created in TEST mode.\n";
echo "Replace pk_test_PLACEHOLDER / sk_test_PLACEHOLDER in settings.php — see inline comment.\n";

/*
 * ──────────────────────────────────────────────────────────────────────────
 * PATTERN SETTINGS.PHP : surcharge de config pour les clés Stripe
 * ──────────────────────────────────────────────────────────────────────────
 *
 * Dans web/sites/default/settings.local.php (jamais committé) :
 *
 *   $config['commerce_payment.commerce_payment_gateway.stripe_sandbox']['configuration']['publishable_key']
 *     = getenv('STRIPE_PUBLISHABLE_KEY') ?: 'pk_test_XXX';
 *   $config['commerce_payment.commerce_payment_gateway.stripe_sandbox']['configuration']['secret_key']
 *     = getenv('STRIPE_SECRET_KEY') ?: 'sk_test_XXX';
 *   $config['commerce_payment.commerce_payment_gateway.stripe_sandbox']['configuration']['webhook_secret']
 *     = getenv('STRIPE_WEBHOOK_SECRET') ?: '';
 *
 * En production (VPS), définir STRIPE_PUBLISHABLE_KEY / STRIPE_SECRET_KEY
 * dans l'environnement systemd ou dans .env (lu par DDEV ou drush).
 * ──────────────────────────────────────────────────────────────────────────
 */
