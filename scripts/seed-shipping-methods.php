<?php

/**
 * @file
 * Seed des méthodes d'expédition O'Design Création — TCK-067.
 *
 * 5 méthodes : Colissimo, DPD, Chronopost, Mondial Relay, Mondial Relay gratuit.
 * Script idempotent : supprime les anciennes méthodes génériques avant recréation.
 * ShippingMethod est un ContentEntityType (non exportable en config/sync).
 *
 * Usage :
 *   ddev drush php-script scripts/seed-shipping-methods.php
 *   terminus drush atelierodesign.dev -- php-script /code/scripts/seed-shipping-methods.php
 */

use Drupal\commerce_shipping\Entity\ShippingMethod;
use Drupal\commerce_store\Entity\Store;

// ─── Store ─────────────────────────────────────────────────────────────────

$store_storage = \Drupal::entityTypeManager()->getStorage('commerce_store');
$stores = $store_storage->loadMultiple();

if (empty($stores)) {
  echo "Aucun store trouvé. Création du store O'Design Création…\n";
  $store = Store::create([
    'type'             => 'online',
    'name'             => 'O'Design Création',
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

// ─── Suppression des anciennes méthodes génériques ─────────────────────────

$storage = \Drupal::entityTypeManager()->getStorage('commerce_shipping_method');
$obsolete_names = ['Livraison standard', 'Livraison gratuite'];

foreach ($obsolete_names as $obsolete_name) {
  $existing = $storage->loadByProperties(['name' => $obsolete_name]);
  foreach ($existing as $old_method) {
    $old_id = $old_method->id();
    $old_method->delete();
    echo "SUPPRIMÉ : '" . $obsolete_name . "' (id={$old_id})\n";
  }
}

// ─── Condition commune : France métropolitaine ─────────────────────────────

$condition_fr = [
  'target_plugin_id' => 'shipment_address',
  'target_plugin_configuration' => [
    'zone' => ['territories' => [['country_code' => 'FR']]],
  ],
];

$condition_min50 = [
  'target_plugin_id' => 'order_total_price',
  'target_plugin_configuration' => [
    'operator' => '>=',
    'amount' => ['number' => '50.00', 'currency_code' => 'EUR'],
  ],
];

// ─── Définition des 5 méthodes d'expédition ────────────────────────────────

$methods_config = [
  [
    'name' => 'Colissimo — Livraison à domicile',
    'weight' => 0,
    'plugin_id' => 'flat_rate',
    'plugin_config' => [
      'label' => 'Colissimo — Livraison à domicile',
      'description' => 'Livraison à domicile par Colissimo (La Poste), 3 à 5 jours ouvrés.',
      'rate_label' => 'Colissimo — Livraison à domicile',
      'rate_description' => 'Livraison en France métropolitaine sous 3 à 5 jours ouvrés.',
      'rate_amount' => ['number' => '5.90', 'currency_code' => 'EUR'],
    ],
    'conditions' => [$condition_fr],
  ],
  [
    'name' => 'DPD — Livraison à domicile',
    'weight' => 1,
    'plugin_id' => 'flat_rate',
    'plugin_config' => [
      'label' => 'DPD — Livraison à domicile',
      'description' => 'Livraison à domicile par DPD, 2 à 4 jours ouvrés.',
      'rate_label' => 'DPD — Livraison à domicile',
      'rate_description' => 'Livraison en France métropolitaine sous 2 à 4 jours ouvrés.',
      'rate_amount' => ['number' => '5.90', 'currency_code' => 'EUR'],
    ],
    'conditions' => [$condition_fr],
  ],
  [
    'name' => 'Chronopost — Livraison express',
    'weight' => 2,
    'plugin_id' => 'flat_rate',
    'plugin_config' => [
      'label' => 'Chronopost — Livraison express',
      'description' => 'Livraison express par Chronopost, le lendemain avant 13h.',
      'rate_label' => 'Chronopost — Livraison express',
      'rate_description' => 'Livraison express en France métropolitaine J+1 avant 13h.',
      'rate_amount' => ['number' => '7.90', 'currency_code' => 'EUR'],
    ],
    'conditions' => [$condition_fr],
  ],
  [
    'name' => 'Mondial Relay — Point relais',
    'weight' => 3,
    'plugin_id' => 'commerce_shipping_mondial_relay',
    'plugin_config' => [
      'rate_label' => 'Mondial Relay — Point relais',
      'rate_description' => 'Livraison en point relais en France métropolitaine.',
      'rate_amount' => ['number' => '3.90', 'currency_code' => 'EUR'],
      'widget' => [
        // BDTEST = Brand ID de sandbox public Mondial Relay.
        // À remplacer par le Brand ID réel depuis l'espace pro Mondial Relay
        // (connect.mondialrelay.com → Mes Paramètres de Connexion → Api settings).
        'brand' => 'BDTEST',
        'default_country' => 'FR',
        'enable_geolocalisated_search' => 1,
        'col_liv_mod' => '24R',
        'nb_results' => 7,
        'search_delay' => 0,
        'map_scroll_wheel' => FALSE,
        'map_street_view' => FALSE,
        'responsive' => TRUE,
        'show_results_on_map' => TRUE,
        'display_map_info' => TRUE,
        'theme' => 'mondialrelay',
      ],
      'services' => ['default'],
    ],
    'conditions' => [$condition_fr],
  ],
  [
    'name' => 'Mondial Relay gratuit (commande ≥ 50€)',
    'weight' => 4,
    'plugin_id' => 'commerce_shipping_mondial_relay',
    'plugin_config' => [
      'rate_label' => 'Mondial Relay — Point relais GRATUIT',
      'rate_description' => 'Frais de port offerts via Mondial Relay pour toute commande ≥ 50 €.',
      'rate_amount' => ['number' => '0.00', 'currency_code' => 'EUR'],
      'widget' => [
        'brand' => 'BDTEST',
        'default_country' => 'FR',
        'enable_geolocalisated_search' => 1,
        'col_liv_mod' => '24R',
        'nb_results' => 7,
        'search_delay' => 0,
        'map_scroll_wheel' => FALSE,
        'map_street_view' => FALSE,
        'responsive' => TRUE,
        'show_results_on_map' => TRUE,
        'display_map_info' => TRUE,
        'theme' => 'mondialrelay',
      ],
      'services' => ['default'],
    ],
    'conditions' => [$condition_fr, $condition_min50],
  ],
];

// ─── Création idempotente ──────────────────────────────────────────────────

foreach ($methods_config as $cfg) {
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

// ─── Désactivation de la promotion générique livraison gratuite ────────────
// (Option A TCK-067 Phase 2 : la méthode "Mondial Relay gratuit" remplace la promotion)

$promo_storage = \Drupal::entityTypeManager()->getStorage('commerce_promotion');
$promos = $promo_storage->loadByProperties(['name' => 'Livraison gratuite dès 50€']);
foreach ($promos as $promo) {
  if ($promo->isEnabled()) {
    $promo->setEnabled(FALSE);
    $promo->save();
    echo "DÉSACTIVÉE : promotion '" . $promo->getName() . "' (id=" . $promo->id() . ") — remplacée par méthode Mondial Relay gratuit.\n";
  }
  else {
    echo "SKIP promotion '" . $promo->getName() . "' (déjà désactivée)\n";
  }
}

echo "\nSeed transporteurs terminé.\n";
