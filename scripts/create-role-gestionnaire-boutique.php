<?php
/**
 * Script drush scr : crée le rôle gestionnaire_boutique.
 *
 * Usage: ddev drush scr scripts/create-role-gestionnaire-boutique.php
 *
 * Périmètre du rôle :
 * - Commandes : voir, modifier, ajouter des commentaires admin
 * - Produits : créer, modifier, voir (pas supprimer)
 * - Clients : voir les profils et comptes
 * - Paiements : voir (pas configurer les gateways)
 * - Contenu éditorial : créer/modifier articles et pages basiques
 * - Pas d'accès : modules, permissions, config système, gateways paiement
 */

use Drupal\user\Entity\Role;

$role_id = 'gestionnaire_boutique';
$role_label = 'Gestionnaire boutique';

// Créer ou recharger le rôle.
$role = Role::load($role_id);
if (!$role) {
  $role = Role::create([
    'id' => $role_id,
    'label' => $role_label,
    'weight' => 3,
  ]);
  $role->save();
  echo "Role '$role_id' created.\n";
}
else {
  echo "Role '$role_id' already exists — updating permissions.\n";
}

// Permissions accordées.
$permissions = [
  // Interface admin Commerce.
  'access commerce administration pages',
  'access checkout',

  // Commandes.
  'access commerce_order overview',
  'view commerce_order',
  'view default commerce_order',
  'update default commerce_order',
  'add commerce_log commerce_order admin comment',
  'unlock orders',
  'manage default commerce_order_item',

  // Produits.
  'access commerce_product overview',
  'create default commerce_product',
  'update any default commerce_product',
  'update own default commerce_product',
  'view default commerce_product',
  'view own unpublished commerce_product',
  'view commerce_product',
  'manage default commerce_product_variation',
  'access commerce_product_attribute overview',
  'view commerce_product_attribute',

  // Médias (images produit).
  'access media overview',
  'create media',
  'update any media',
  'update own media',
  'view media',
  'delete own media',

  // Paiements — lecture seule.
  'view any commerce_payment_method',

  // Clients — lecture profils.
  'access user profiles',

  // Contenu éditorial basique.
  'access content overview',
  'create article content',
  'edit any article content',
  'create basic_page content',
  'edit any basic_page content',

  // Navigation admin et barre d'outils.
  'access toolbar',
  'access administration pages',
  'use admin toolbar search',

  // Aide contextuelle.
  'access contextual links',
  'access in-place editing',
];

foreach ($permissions as $perm) {
  try {
    $role->grantPermission($perm);
  }
  catch (\Exception $e) {
    // Ignorer les permissions inexistantes silencieusement.
  }
}
$role->save();

echo "Permissions assigned to '$role_id'.\n";
echo "Done. Next: assign this role to the client's Drupal account.\n";
