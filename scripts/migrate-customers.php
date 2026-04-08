<?php

/**
 * Migration clients Shopify → Drupal.
 *
 * Usage : ddev exec drush scr scripts/migrate-customers.php
 * CSV    : ../odesign-docs/collecte/shopify/exports/clients/customers_export.csv
 * Idempotent : les comptes existants (même email) sont ignorés.
 */

declare(strict_types=1);

use Drupal\user\Entity\User;

$csv_path = __DIR__ . '/../../odesign-docs/collecte/shopify/exports/clients/customers_export.csv';

if (!file_exists($csv_path)) {
  echo "ERREUR : fichier CSV introuvable à : $csv_path\n";
  exit(1);
}

$handle = fopen($csv_path, 'r');
if (!$handle) {
  echo "ERREUR : impossible d'ouvrir le CSV.\n";
  exit(1);
}

// Lire l'en-tête
$header = fgetcsv($handle);
if (!$header) {
  echo "ERREUR : CSV vide ou mal formé.\n";
  fclose($handle);
  exit(1);
}

// Normaliser les noms de colonnes
$header = array_map('trim', $header);
$col = array_flip($header);

$created   = 0;
$skipped   = 0;
$errors    = 0;

echo "Démarrage de la migration clients Shopify → Drupal…\n";
echo str_repeat('-', 60) . "\n";

while (($row = fgetcsv($handle)) !== FALSE) {
  if (count($row) < count($header)) {
    continue;
  }
  $data = array_combine($header, $row);

  // Nettoyer l'apostrophe de protection Shopify sur les IDs numériques
  $email      = trim($data['Email'] ?? '');
  $first_name = trim($data['First Name'] ?? '');
  $last_name  = trim($data['Last Name'] ?? '');

  if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "  IGNORÉ : email invalide ou absent — ligne ignorée.\n";
    $errors++;
    continue;
  }

  // Idempotence : vérifier si un compte existe déjà
  $existing = user_load_by_mail($email);
  if ($existing) {
    echo "  EXISTANT : $email (uid={$existing->id()}) — ignoré.\n";
    $skipped++;
    continue;
  }

  // Construire le nom d'utilisateur
  if ($first_name || $last_name) {
    $username_base = trim("$first_name $last_name");
  }
  else {
    $username_base = $email;
  }

  // Assurer l'unicité du username
  $username = $username_base;
  $suffix   = 1;
  while (user_load_by_name($username)) {
    $username = $username_base . '_' . $suffix;
    $suffix++;
  }

  // Mot de passe aléatoire (non communiqué — le client fera "Mot de passe oublié")
  $password = \Drupal::service('password_generator')->generate();

  // Marquer la provenance Shopify dans user.data (via profil)
  // Note : setData() n'existe pas sur User — on utilise le service user.data.
  $user = User::create([
    'name'   => $username,
    'mail'   => $email,
    'pass'   => $password,
    'status' => 1,
    'langcode' => 'fr',
    'preferred_langcode' => 'fr',
  ]);

  try {
    $result = $user->save();

    // Marquer la provenance Shopify via le service user.data
    \Drupal::service('user.data')->set(
      'odesign_migration',
      $user->id(),
      'shopify_migrated',
      ['migrated' => TRUE, 'date' => date('Y-m-d')]
    );

    echo "  CRÉÉ : $email (uid={$user->id()}, username=\"$username\")\n";
    $created++;
  }
  catch (\Exception $e) {
    echo "  ERREUR création $email : " . $e->getMessage() . "\n";
    $errors++;
  }
}

fclose($handle);

echo str_repeat('-', 60) . "\n";
echo "Migration clients terminée.\n";
echo "  Créés   : $created\n";
echo "  Ignorés (existants) : $skipped\n";
echo "  Erreurs : $errors\n";
