<?php

/**
 * Migration commandes Shopify → Drupal Commerce.
 *
 * Usage : ddev exec drush scr scripts/migrate-orders.php
 * CSV   : ../odesign-docs/collecte/shopify/exports/commandes/orders_export.csv
 * Idempotent : chaque commande est identifiée par field_shopify_order_id.
 *
 * Ce script :
 *  1. Crée le champ field_shopify_order_id s'il n'existe pas.
 *  2. Groupe les lignes CSV par numéro Shopify (ex: #1002).
 *  3. Pour chaque commande absente, crée order + order_items + billing_profile.
 *  4. Positionne l'état à « completed » (Livrée) sans déclencher d'emails.
 */

declare(strict_types=1);

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\profile\Entity\Profile;

// ---------------------------------------------------------------------------
// 0. Créer field_shopify_order_id si absent
// ---------------------------------------------------------------------------

const SHOPIFY_FIELD = 'field_shopify_order_id';

if (!FieldStorageConfig::loadByName('commerce_order', SHOPIFY_FIELD)) {
  FieldStorageConfig::create([
    'field_name'  => SHOPIFY_FIELD,
    'entity_type' => 'commerce_order',
    'type'        => 'string',
    'cardinality' => 1,
    'settings'    => ['max_length' => 64],
  ])->save();
  echo "Champ storage " . SHOPIFY_FIELD . " créé.\n";
}

if (!FieldConfig::loadByName('commerce_order', 'default', SHOPIFY_FIELD)) {
  FieldConfig::create([
    'field_name'  => SHOPIFY_FIELD,
    'entity_type' => 'commerce_order',
    'bundle'      => 'default',
    'label'       => 'Numéro Shopify',
    'required'    => FALSE,
  ])->save();
  echo "Champ instance " . SHOPIFY_FIELD . " créé sur commerce_order.default.\n";
}

// ---------------------------------------------------------------------------
// 1. Lire et grouper le CSV
// ---------------------------------------------------------------------------

$csv_path = __DIR__ . '/../../odesign-docs/collecte/shopify/exports/commandes/orders_export.csv';

if (!file_exists($csv_path)) {
  echo "ERREUR : fichier CSV introuvable à : $csv_path\n";
  exit(1);
}

$handle = fopen($csv_path, 'r');
if (!$handle) {
  echo "ERREUR : impossible d'ouvrir le CSV.\n";
  exit(1);
}

$header = fgetcsv($handle);
if (!$header) {
  echo "ERREUR : CSV vide ou mal formé.\n";
  fclose($handle);
  exit(1);
}
$header = array_map('trim', $header);

// Grouper toutes les lignes par numéro de commande Shopify.
// La première ligne non-vide d'un groupe porte les données globales ;
// les lignes suivantes ne contiennent que les colonnes Lineitem*.
$groups = [];

while (($row = fgetcsv($handle)) !== FALSE) {
  if (count($row) < 2) {
    continue;
  }
  // Compléter les colonnes manquantes
  while (count($row) < count($header)) {
    $row[] = '';
  }
  $data    = array_combine($header, $row);
  $name    = trim($data['Name'] ?? '');

  if (empty($name)) {
    continue;
  }

  if (!isset($groups[$name])) {
    $groups[$name] = [
      'meta'  => $data,   // données globales (email, adresses, etc.)
      'items' => [],
    ];
  }

  $item_name = trim($data['Lineitem name'] ?? '');
  $item_qty  = (int) ($data['Lineitem quantity'] ?? 1);
  $item_price = (float) str_replace(',', '.', $data['Lineitem price'] ?? '0');

  if ($item_name !== '') {
    $groups[$name]['items'][] = [
      'title'    => $item_name,
      'quantity' => max(1, $item_qty),
      'price'    => $item_price,
    ];
  }
}
fclose($handle);

echo "Démarrage de la migration commandes Shopify → Drupal Commerce…\n";
echo str_repeat('-', 60) . "\n";
echo "Commandes lues dans le CSV : " . count($groups) . "\n\n";

// ---------------------------------------------------------------------------
// 2. Traiter chaque commande
// ---------------------------------------------------------------------------

$entity_type_manager = \Drupal::entityTypeManager();
$created  = 0;
$skipped  = 0;
$errors   = 0;

foreach ($groups as $shopify_number => $group) {
  $meta = $group['meta'];

  // Idempotence : chercher une commande existante avec ce numéro Shopify.
  $existing = $entity_type_manager->getStorage('commerce_order')
    ->loadByProperties([SHOPIFY_FIELD => $shopify_number]);

  if (!empty($existing)) {
    echo "  EXISTANTE : $shopify_number — ignorée.\n";
    $skipped++;
    continue;
  }

  $email = trim($meta['Email'] ?? '');

  // Résoudre l'uid client (0 = anonyme si inconnu)
  $uid = 0;
  if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $account = user_load_by_mail($email);
    if ($account) {
      $uid = (int) $account->id();
    }
  }

  // Date de commande
  $placed_str = trim($meta['Created at'] ?? '');
  $placed     = $placed_str ? strtotime($placed_str) : \Drupal::time()->getRequestTime();
  if (!$placed) {
    $placed = \Drupal::time()->getRequestTime();
  }

  // ---------------------------------------------------------------------------
  // 2a. Créer les order items
  // ---------------------------------------------------------------------------
  $order_items = [];

  foreach ($group['items'] as $line) {
    try {
      $item_title = $line['title'];
      $unit_price = new Price((string) number_format($line['price'], 2, '.', ''), 'EUR');

      // Tentative de liaison au produit Drupal par titre exact ou approché
      $purchased_entity = NULL;
      $variation_ids = $entity_type_manager->getStorage('commerce_product_variation')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('title', $item_title)
        ->execute();

      if (empty($variation_ids)) {
        // Recherche insensible à la casse / partielle
        $variation_ids = $entity_type_manager->getStorage('commerce_product_variation')
          ->getQuery()
          ->accessCheck(FALSE)
          ->condition('title', '%' . $item_title . '%', 'LIKE')
          ->range(0, 1)
          ->execute();
      }

      if (!empty($variation_ids)) {
        $purchased_entity = $entity_type_manager
          ->getStorage('commerce_product_variation')
          ->load(reset($variation_ids));
      }
      else {
        \Drupal::logger('migrate_orders')->warning(
          "Produit non trouvé pour la commande @order : « @title »",
          ['@order' => $shopify_number, '@title' => $item_title]
        );
        echo "    AVERTISSEMENT : produit non trouvé « $item_title » — order_item créé sans entité liée.\n";
      }

      /** @var \Drupal\commerce_order\Entity\OrderItem $order_item */
      $order_item = OrderItem::create([
        'type'       => 'default',
        'title'      => $item_title,
        'quantity'   => (string) $line['quantity'],
        'unit_price' => $unit_price,
      ]);

      if ($purchased_entity) {
        $order_item->set('purchased_entity', $purchased_entity);
      }

      $order_item->save();
      $order_items[] = $order_item;
    }
    catch (\Exception $e) {
      echo "    ERREUR order_item ($shopify_number / {$line['title']}) : " . $e->getMessage() . "\n";
      $errors++;
    }
  }

  if (empty($order_items)) {
    echo "  IGNORÉE : $shopify_number — aucun order_item valide.\n";
    $errors++;
    continue;
  }

  // ---------------------------------------------------------------------------
  // 2b. Créer le billing profile
  // ---------------------------------------------------------------------------
  $billing_profile = NULL;
  $billing_name    = trim($meta['Billing Name'] ?? '');
  $billing_addr1   = trim($meta['Billing Address1'] ?? '');
  $billing_city    = trim($meta['Billing City'] ?? '');
  $billing_zip     = ltrim(trim($meta['Billing Zip'] ?? ''), "'");
  $billing_country = trim($meta['Billing Country'] ?? 'FR');
  $billing_phone   = trim($meta['Billing Phone'] ?? '');

  if ($billing_name || $billing_addr1) {
    // Décomposer le nom complet en prénom / nom
    $name_parts  = explode(' ', $billing_name, 2);
    $given_name  = $name_parts[0] ?? '';
    $family_name = $name_parts[1] ?? '';

    try {
      $billing_profile = Profile::create([
        'type'      => 'customer',
        'uid'       => $uid ?: 0,
        'status'    => TRUE,
        'is_default' => FALSE,
      ]);

      $billing_profile->set('address', [
        'country_code'  => strtoupper($billing_country) ?: 'FR',
        'given_name'    => $given_name,
        'family_name'   => $family_name,
        'address_line1' => $billing_addr1,
        'locality'      => $billing_city,
        'postal_code'   => $billing_zip,
      ]);

      $billing_profile->save();
    }
    catch (\Exception $e) {
      echo "    AVERTISSEMENT : profil facturation non créé ($shopify_number) : " . $e->getMessage() . "\n";
      $billing_profile = NULL;
    }
  }

  // ---------------------------------------------------------------------------
  // 2c. Créer la commande Commerce
  // ---------------------------------------------------------------------------
  $note_atelier = 'Commande migrée depuis Shopify le ' . date('Y-m-d')
    . ' (origine : ' . $shopify_number . ', Shopify Payments).';

  $shipping_total = (float) str_replace(',', '.', $meta['Shipping'] ?? '0');

  try {
    /** @var \Drupal\commerce_order\Entity\Order $order */
    $order = Order::create([
      'type'        => 'default',
      'store_id'    => 1,
      'uid'         => $uid,
      'mail'        => $email ?: NULL,
      'state'       => 'completed',
      'placed'      => $placed,
      'created'     => $placed,
      'changed'     => $placed,
      'order_items' => $order_items,
    ]);

    // Numéro Shopify (idempotence + traçabilité)
    $order->set(SHOPIFY_FIELD, $shopify_number);

    // Note atelier
    if ($order->hasField('field_notes_atelier')) {
      $order->set('field_notes_atelier', $note_atelier);
    }

    // Profil de facturation
    if ($billing_profile) {
      $order->set('billing_profile', $billing_profile);
    }

    // Ajustement frais de port (si non nul)
    if ($shipping_total > 0) {
      $adjustments = [
        new \Drupal\commerce_order\Adjustment([
          'type'               => 'shipping',
          'label'              => 'Livraison',
          'amount'             => new Price((string) number_format($shipping_total, 2, '.', ''), 'EUR'),
          'source_id'          => 'shopify_migration',
          'included'           => FALSE,
          'locked'             => TRUE,
        ]),
      ];
      $order->setAdjustments($adjustments);
    }

    // Bloquer la commande pour éviter tout recalcul automatique
    $order->set('locked', TRUE);

    $order->save();

    echo "  CRÉÉE : $shopify_number (order_id={$order->id()}, uid=$uid, "
      . count($order_items) . " article(s))\n";
    $created++;
  }
  catch (\Exception $e) {
    echo "  ERREUR commande $shopify_number : " . $e->getMessage() . "\n";
    \Drupal::logger('migrate_orders')->error(
      "Échec migration commande @order : @msg",
      ['@order' => $shopify_number, '@msg' => $e->getMessage()]
    );
    $errors++;
  }
}

// ---------------------------------------------------------------------------
// 3. Résumé
// ---------------------------------------------------------------------------
echo str_repeat('-', 60) . "\n";
echo "Migration commandes terminée.\n";
echo "  Créées  : $created\n";
echo "  Ignorées (existantes) : $skipped\n";
echo "  Erreurs : $errors\n";
