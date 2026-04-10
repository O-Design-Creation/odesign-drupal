<?php
/**
 * @file
 * Active la langue française comme langue par défaut et supprime l'anglais.
 *
 * Appelé par deploy-first-install.sh (étape 5).
 *
 * Nécessaire car system.site est dans config_ignore → config:import ne le
 * modifie jamais. En particulier, site:install minimal :
 *   - installe Drupal en anglais (langue par défaut = EN)
 *   - pose page.front = /user/login (le route normalizer redirige alors
 *     /user/login → / en la détectant comme page d'accueil)
 *   - laisse language.negotiation sur fr:fr (préfixe /fr/ requis pour le FR)
 *
 * Ce script corrige les quatre éléments :
 *   1. system.site : langcode + default_langcode + mail + page.front
 *   2. language.negotiation : URL sans préfixe = FR (pas EN)
 *   3. Suppression de language.entity.en via la config factory (bypass du
 *      check entity-layer "cannot delete default language" qui lirait encore
 *      l'ancienne valeur du container).
 *
 * Usage : ddev exec drush php:script scripts/setup-language.php
 */

$configFactory = \Drupal::configFactory();

// 1. system.site : langue + mail + front page (ignoré par config_ignore)
// page.front doit être /node (valeur config/sync) et PAS /user/login.
// Si page.front = /user/login, le route normalizer de redirect module
// détecte /user/login comme page d'accueil et redirige vers / (301).
$siteMail = getenv('SITE_MAIL') ?: 'odesigncreation@gmail.com';
$configFactory->getEditable('system.site')
  ->set('langcode', 'fr')
  ->set('default_langcode', 'fr')
  ->set('mail', $siteMail)
  ->set('page.front', '/node')
  ->save();
echo "system.site : langcode=fr, default_langcode=fr, mail={$siteMail}, front=/node" . PHP_EOL;

// 2. language.negotiation : URL sans préfixe = FR (pas EN)
// site:install laisse fr:fr (préfixe /fr/ obligatoire) et en:'' — on corrige.
$configFactory->getEditable('language.negotiation')
  ->set('url.prefixes', ['fr' => ''])
  ->save();
echo "language.negotiation : url.prefixes corrigé (fr='')" . PHP_EOL;

// 3. Supprimer language.entity.en via config factory (bypass entity-layer).
// On ne passe pas par ConfigurableLanguage::delete() pour éviter l'exception
// "Cannot delete the default language" levée avant que le cache du container
// soit reregénéré avec la nouvelle valeur de default_langcode.
$enConfig = $configFactory->getEditable('language.entity.en');
if ($enConfig->isNew()) {
  echo "EN absent (déjà supprimé ou non installé)" . PHP_EOL;
} else {
  $enConfig->delete();
  echo "EN supprimé (language.entity.en)" . PHP_EOL;
}
