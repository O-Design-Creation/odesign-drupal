<?php
/**
 * @file
 * Seed des redirections 301 Shopify → Drupal (TCK-019).
 *
 * Idempotent : vérifie l'existence avant création. Peut être rejoué sans doublon.
 * Usage : ddev exec drush scr scripts/seed-redirections.php
 *         terminus drush atelierodesign.dev -- scr scripts/seed-redirections.php
 *
 * ---------------------------------------------------------------------------
 * Redirections implémentées (vérifié 2026-04-08) :
 *
 * Produits (/products/*) :
 *   Shopify /products/{handle} = Drupal /products/{handle} → aucune redirection nécessaire.
 *
 * Collections → Occasions (taxonomie life_occasion) :
 *   /collections/mariage                         → /occasions/mariage
 *   /collections/naissance-bebe                  → /occasions/naissance-bebe
 *   /collections/cadeaux-personnalises-famille   → /occasions/cadeaux-personnalises-famille
 *   /collections/all                             → / (fallback : /catalogue non implémenté)
 *   /collections                                 → / (fallback : /occasions non implémenté)
 *
 * Blog :
 *   /blogs/news   → /blog
 *   /blogs/news/  → /blog
 *
 * Pages légales et institutionnelles :
 *   /pages/cgv                      → /cgv
 *   /pages/mentions-legales         → /mentions-legales
 *   /pages/politique-confidentialite → /politique-confidentialite
 *   /pages/contact                  → /contact
 *   /pages/a-propos                 → /atelier
 *   /pages/about                    → /atelier
 *   /pages/faq                      → /contact (fallback : /faq non implémenté)
 *
 * Compte client :
 *   /account          → /user/login
 *   /account/login    → /user/login
 *   /account/register → /user/register
 *
 * Non créées (cible non existante) :
 *   /search → /recherche  (AVERTISSEMENT : /recherche non implémenté à cette date)
 * ---------------------------------------------------------------------------
 */

use Drupal\redirect\Entity\Redirect;

$pathValidator = \Drupal::service('path.validator');
$aliasStorage = \Drupal::entityTypeManager()->getStorage('path_alias');
$redirectRepository = \Drupal::service('redirect.repository');
$count_created = 0;
$count_skipped = 0;
$count_warned = 0;

/**
 * Vérifie qu'une destination est valide : route Drupal directe OU alias FR.
 * En contexte CLI, Drupal utilise la langue 'en' par défaut — path.validator
 * ne résout pas les aliases stockés en 'fr'. On interroge path_alias storage
 * directement pour gérer ce cas.
 */
$isValidDestination = static function (string $path) use ($pathValidator, $aliasStorage): bool {
  // Route ou chemin système directement valide (ex: /, /blog, /user/login).
  if ($pathValidator->isValid($path)) {
    return TRUE;
  }
  // Alias FR : cherche dans path_alias (ex: /cgv, /occasions/mariage).
  return !empty($aliasStorage->loadByProperties(['alias' => $path]));
};

/**
 * Closure : crée une redirection 301 si elle n'existe pas encore.
 * Vérifie que la destination est valide avant création.
 * Si $skip_validation = TRUE, la validation de la cible est bypassée
 * (utile pour les routes système Drupal comme /user/login).
 *
 * Note : closure utilisée (vs function) pour capturer les services depuis le
 * contexte du script drush (include() depuis une méthode — global n'y fonctionne pas).
 * Note : utilise redirect.repository->findMatchingRedirect() (lookup par hash)
 * pour une idempotence correcte, y compris pour les variantes avec slash final.
 */
$ensure_redirect = function (string $source, string $destination, bool $skip_validation = FALSE)
  use ($isValidDestination, $redirectRepository, &$count_created, &$count_skipped, &$count_warned): string {

  // Vérifier si la cible existe (sauf routes système bypassées).
  if (!$skip_validation && !$isValidDestination($destination)) {
    echo "AVERTISSEMENT : destination introuvable, redirection non créée : $source → $destination\n";
    $count_warned++;
    return 'warned';
  }

  // Idempotence : lookup par hash (gère les variantes trailing-slash).
  $existing = $redirectRepository->findMatchingRedirect(ltrim($source, '/'));
  if ($existing) {
    echo "SKIP (existe déjà) : $source\n";
    $count_skipped++;
    return 'skipped';
  }

  $redirect = Redirect::create([
    'redirect_source' => ['path' => ltrim($source, '/'), 'query' => NULL],
    'redirect_redirect' => ['uri' => 'internal:' . $destination],
    'status_code' => 301,
    'langcode' => 'fr',
    'uid' => 1,
  ]);
  $redirect->save();
  echo "CRÉÉ : $source → $destination\n";
  $count_created++;
  return 'created';
};

// ---------------------------------------------------------------------------
// Collections Shopify → pages Occasions (taxonomie life_occasion vérifiée)
// ---------------------------------------------------------------------------
$ensure_redirect('/collections/mariage', '/occasions/mariage');
$ensure_redirect('/collections/naissance-bebe', '/occasions/naissance-bebe');
$ensure_redirect('/collections/cadeaux-personnalises-famille', '/occasions/cadeaux-personnalises-famille');
// /catalogue non implémenté → fallback homepage.
$ensure_redirect('/collections/all', '/');
// /occasions (page liste) non implémenté → fallback homepage.
$ensure_redirect('/collections', '/');

// ---------------------------------------------------------------------------
// Blog Shopify → Blog Drupal
// ---------------------------------------------------------------------------
$ensure_redirect('/blogs/news', '/blog');
$ensure_redirect('/blogs/news/', '/blog');

// ---------------------------------------------------------------------------
// Pages légales et institutionnelles
// ---------------------------------------------------------------------------
$ensure_redirect('/pages/cgv', '/cgv');
$ensure_redirect('/pages/mentions-legales', '/mentions-legales');
$ensure_redirect('/pages/politique-confidentialite', '/politique-confidentialite');
$ensure_redirect('/pages/contact', '/contact');
$ensure_redirect('/pages/a-propos', '/atelier');
$ensure_redirect('/pages/about', '/atelier');
// /faq non implémenté → fallback /contact.
$ensure_redirect('/pages/faq', '/contact');

// ---------------------------------------------------------------------------
// Compte client (/user/login et /user/register sont des routes système Drupal)
// ---------------------------------------------------------------------------
$ensure_redirect('/account', '/user/login', TRUE);
$ensure_redirect('/account/login', '/user/login', TRUE);
$ensure_redirect('/account/register', '/user/register', TRUE);

// ---------------------------------------------------------------------------
// Recherche : /recherche non implémenté → avertissement, pas de redirection.
// ---------------------------------------------------------------------------
$ensure_redirect('/search', '/recherche');

// ---------------------------------------------------------------------------
// Résumé
// ---------------------------------------------------------------------------
echo PHP_EOL;
echo "=== Résumé TCK-019 ===" . PHP_EOL;
echo "Créées   : $count_created" . PHP_EOL;
echo "Skippées : $count_skipped" . PHP_EOL;
echo "Avertissements (cible manquante) : $count_warned" . PHP_EOL;
