<?php

/**
 * @file
 * Seed des méthodes d'expédition Atelier O Design.
 *
 * ShippingMethod est un ContentEntityType (non versionnable en config/sync).
 * Ce script doit être exécuté sur chaque environnement fresh.
 *
 * Usage :
 *   ddev drush php-script scripts/seed-shipping-methods.php
 *   terminus drush atelierodesign.dev -- php-script /code/scripts/seed-shipping-methods.php
 */

use Drupal\commerce_shipping\Entity\ShippingMethod;
use Drupal\commerce_store\Entity\Store;

// Récupérer ou créer le store Atelier O Design.
$store_storage = \Drupal::entityTypeManager()->getStorage('commerce_store');
$stores = $store_storage->loadMultiple();

if (empty($stores)) {
  echo "Aucun store trouvé. Création du store Atelier O Design…\n";
  $store = Store::create([
    'type'             => 'online',
    'name'             => 'Atelier O Design',
    'mail'             => 'odesigncrea@gmail.com',
    'default_currency' => 'EUR',
    'timezone'         => 'Europe/Paris',
    'address'          => ['country_code' => 'FR'],
    'is_default'       => TRUE,
    'status'           => TRUE,
  ]);
  $store->save();
  echo "Store créé : " . $store->getName() . " (id=" . $store->id() . ")\n";
}
else {
  $store = reset($stores);
}
$store_id = $store->id();
echo "Store trouvé : " . $store->getName() . " (id={$store_id})\n";

$methods_config = [
  [
    'name' => 'Livraison standard',
    'weight' => 0,
    'plugin_id' => 'flat_rate',
    'plugin_config' => [
      'label' => 'Livraison standard',
      'description' => 'Livraison en France métropolitaine sous 3 à 5 jours ouvrés.',
      'rate_label' => 'Livraison standard',
      'rate_description' => 'Expédition sous 24h, livraison 3-5 jours ouvrés.',
      'rate_amount' => ['number' => '4.90', 'currency_code' => 'EUR'],
    ],
    'conditions' => [
      [
        'target_plugin_id' => 'shipment_address',
        'target_plugin_configuration' => [
          'zone' => ['territories' => [['country_code' => 'FR']]],
        ],
      ],
    ],
  ],
  [
    'name' => 'Livraison gratuite',
    'weight' => 1,
    'plugin_id' => 'flat_rate',
    'plugin_config' => [
      'label' => 'Livraison gratuite',
      'description' => 'Offerte à partir de 50 € d\'achat.',
      'rate_label' => 'Livraison gratuite',
      'rate_description' => 'Frais de port offerts pour toute commande ≥ 50 €.',
      'rate_amount' => ['number' => '0.00', 'currency_code' => 'EUR'],
    ],
    'conditions' => [
      [
        'target_plugin_id' => 'shipment_address',
        'target_plugin_configuration' => [
          'zone' => ['territories' => [['country_code' => 'FR']]],
        ],
      ],
      [
        'target_plugin_id' => 'order_total_price',
        'target_plugin_configuration' => [
          'operator' => '>=',
          'amount' => ['number' => '50.00', 'currency_code' => 'EUR'],
        ],
      ],
    ],
  ],
];

$storage = \Drupal::entityTypeManager()->getStorage('commerce_shipping_method');

foreach ($methods_config as $cfg) {
  // Vérifier si une méthode avec ce nom existe déjà.
  $existing = $storage->loadByProperties(['name' => $cfg['name']]);
  if (!empty($existing)) {
    echo "SKIP : '" . $cfg['name'] . "' existe déjà (id=" . key($existing) . ")\n";
    continue;
  }

  $method = ShippingMethod::create([
    'stores' => [$store_id],
    'name' => $cfg['name'],
    'plugin' => [
      'target_plugin_id' => $cfg['plugin_id'],
      'target_plugin_configuration' => $cfg['plugin_config'],
    ],
    'conditions' => $cfg['conditions'],
    'condition_operator' => 'AND',
    'weight' => $cfg['weight'],
    'status' => TRUE,
  ]);

  $method->save();
  echo "CRÉÉ : '" . $cfg['name'] . "' (id=" . $method->id() . ")\n";
}

echo "Seed terminé.\n";
