<?php

/**
 * @file
 * Paramètres spécifiques à l'environnement Pantheon.
 *
 * Ce fichier est inclus depuis settings.php uniquement quand
 * la variable d'environnement PANTHEON_ENVIRONMENT est présente.
 *
 * Ne pas committer de secrets ici — les credentials sont injectés
 * par Pantheon via les variables d'environnement $_ENV.
 */

// ── Base de données ─────────────────────────────────────────────────────────
// Pantheon injecte les credentials DB via les variables d'environnement.
$databases['default']['default'] = [
  'database'  => $_ENV['DB_NAME'],
  'username'  => $_ENV['DB_USER'],
  'password'  => $_ENV['DB_PASSWORD'],
  'host'      => $_ENV['DB_HOST'],
  'port'      => $_ENV['DB_PORT'],
  'driver'    => 'mysql',
  'prefix'    => '',
  'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'autoload'  => 'core/modules/mysql/src/Driver/Database/mysql/',
];

// ── Hash salt ────────────────────────────────────────────────────────────────
// Pantheon fournit DRUPAL_HASH_SALT depuis le tableau de bord.
// Si non défini, on conserve celui de settings.php.
if (!empty($_ENV['DRUPAL_HASH_SALT'])) {
  $settings['hash_salt'] = $_ENV['DRUPAL_HASH_SALT'];
}

// ── Trusted host patterns ────────────────────────────────────────────────────
$settings['trusted_host_patterns'] = [
  '^.+\.pantheonsite\.io$',    // Tous les sous-domaines Pantheon (sandbox, dev, test, live)
  '^atelierodesign\.fr$',      // Domaine production
  '^www\.atelierodesign\.fr$', // www
  '^.+\.atelierodesign\.fr$',  // Sous-domaines (staging, etc.)
];

// ── Répertoire de configuration ──────────────────────────────────────────────
// Sur Pantheon, le webroot est /code/web — ../config/sync pointe vers /code/config/sync.
$settings['config_sync_directory'] = '../config/sync';

// ── Proxy inverse — Pantheon CDN / load balancer ─────────────────────────────
if (!empty($_SERVER['REMOTE_ADDR'])) {
  $settings['reverse_proxy'] = TRUE;
  $settings['reverse_proxy_addresses'] = [$_SERVER['REMOTE_ADDR']];
}
$settings['reverse_proxy_header'] = 'HTTP_X_FORWARDED_FOR';

// ── Chemins fichiers ─────────────────────────────────────────────────────────
$settings['file_public_path']  = 'sites/default/files';
$settings['file_private_path'] = $_ENV['HOME'] . '/files/private';
$settings['file_temp_path']    = $_ENV['HOME'] . '/tmp';

// ── Désactiver settings.local.php sur Pantheon ───────────────────────────────
// settings.local.php ne doit pas exister sur Pantheon.
// Ce flag est informatif — la vérification d'existence dans settings.php suffit.

// ── SMTP Brevo — secrets injectés par variables d'environnement Pantheon ─────
// Configurer dans Pantheon Dashboard > Site > Settings > Environment Variables :
//   BREVO_SMTP_USERNAME  →  login SMTP Brevo (votre adresse email de compte Brevo)
//   BREVO_SMTP_PASSWORD  →  clé SMTP Brevo (SMTP & API > SMTP Keys)
// Ces overrides ne s'appliquent que si les deux variables sont définies.
if (!empty($_ENV['BREVO_SMTP_USERNAME']) && !empty($_ENV['BREVO_SMTP_PASSWORD'])) {
  $config['smtp.settings']['smtp_host'] = 'smtp-relay.brevo.com';
  $config['smtp.settings']['smtp_username'] = $_ENV['BREVO_SMTP_USERNAME'];
  $config['smtp.settings']['smtp_password'] = $_ENV['BREVO_SMTP_PASSWORD'];
}

// ── Performance (désactivé en sandbox, activer en staging/prod) ──────────────
// Décommenter pour activer l'agrégation CSS/JS en staging et production.
// if (in_array($_ENV['PANTHEON_ENVIRONMENT'], ['test', 'live'], TRUE)) {
//   $config['system.performance']['css']['preprocess'] = TRUE;
//   $config['system.performance']['js']['preprocess'] = TRUE;
// }

// ── Logging — verbeux en dev/sandbox, minimal en prod ────────────────────────
if (in_array($_ENV['PANTHEON_ENVIRONMENT'] ?? '', ['dev', 'sandbox'], TRUE)) {
  $config['system.logging']['error_level'] = 'verbose';
}
else {
  $config['system.logging']['error_level'] = 'hide';
}
