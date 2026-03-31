<?php
$config = \Drupal::service('config.factory')->getEditable('eu_cookie_compliance.settings');
$config->set('popup_info', [
  'value' => '<h2>Nous utilisons des cookies</h2><p>En cliquant sur Accepter, vous nous autorisez à utiliser des cookies pour améliorer votre expérience de navigation.</p>',
  'format' => 'basic_html',
]);
$config->set('popup_info_title', 'Consentement aux cookies');
$config->set('popup_agree_button_message', 'Accepter');
$config->set('popup_disagree_button_message', 'Refuser');
$config->set('popup_find_more_button_message', 'Gérer mes préférences');
$config->save();
echo "Cookie banner fixed.\n";
