<?php
/**
 * @file
 * Seed des redirections 301 Shopify → Drupal.
 *
 * Analyse : le module Redirect est déjà activé (8.x-1.12).
 *
 * Situation redirections produits (url-matrix-handles.csv, 84 produits) :
 *   Shopify : /products/{handle}
 *   Drupal  : /products/{handle}
 *   → URLs IDENTIQUES → aucune redirection 301 nécessaire pour les produits.
 *
 * Redirections à créer (patterns Shopify non-exportés) :
 *   /collections/* → à définir selon la future taxonomie Drupal
 *   /blogs/news/*  → /blog/* (une fois le blog actif)
 *   /pages/about   → /a-propos (si page créée)
 *
 * Usage une fois les données disponibles :
 *   ddev drush php-script scripts/seed-redirections.php
 *   terminus drush atelierodesign.dev -- php-script scripts/seed-redirections.php
 */

use Drupal\redirect\Entity\Redirect;

$lang = \Drupal::languageManager()->getDefaultLanguage()->getId();
$storage = \Drupal::entityTypeManager()->getStorage('redirect');

// ---------------------------------------------------------------------------
// Redirections connues (patterns Shopify spécifiques)
// À compléter au fur et à mesure de l'avancement du projet
// ---------------------------------------------------------------------------
$redirections = [
  // Format : [source_path, destination_path, status_code]
  // Blog Shopify → Drupal (si contenu blog migré)
  // ['/blogs/news', '/blog', 301],

  // Collections Shopify → Drupal produits (à définir)
  // ['/collections/all', '/produits', 301],
  // ['/collections/personnalisation', '/produits', 301],

  // Panier Shopify (si visité depuis liens externes)
  // ['/cart', '/panier', 301],

  // Pages Shopify spécifiques (à confirmer avec client)
  // ['/pages/about', '/a-propos', 301],

  // Placeholder vide — décommenter et compléter selon les données Shopify
];

if (empty($redirections)) {
  echo "INFO : Aucune redirection configurée dans ce script.\n";
  echo "Les redirections produits /products/* sont inutiles (URLs identiques Shopify/Drupal).\n";
  echo "Compléter la liste \$redirections quand les URLs Shopify non-produits seront connues.\n";
  return;
}

$count_created = 0;
$count_skipped = 0;

foreach ($redirections as [$source, $destination, $code]) {
  // Vérifier si la redirection existe déjà.
  $existing = $storage->loadByProperties(['redirect_source__path' => ltrim($source, '/')]);
  if (!empty($existing)) {
    echo "SKIP : {$source} existe déjà\n";
    $count_skipped++;
    continue;
  }

  $redirect = Redirect::create([
    'redirect_source' => ['path' => ltrim($source, '/'), 'query' => []],
    'redirect_redirect' => ['uri' => 'internal:' . $destination],
    'language'  => $lang,
    'status_code' => $code,
    'uid' => 1,
  ]);
  $redirect->save();
  echo "CRÉÉ : {$source} → {$destination} ({$code})\n";
  $count_created++;
}

echo "Créées: {$count_created}, Skippées: {$count_skipped}\n";
