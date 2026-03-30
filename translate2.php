<?php
// Utilisation de l'API locale correcte pour Drupal 11
$strings_to_translate = [
  'Per customer limit' => 'Limite par client',
  'Current currency'   => 'Devise actuelle',
];

$langcode = 'fr';
$locale_storage = \Drupal::service('locale.storage');

foreach ($strings_to_translate as $source_text => $translation_text) {
  // Chercher ou créer la source
  $source = $locale_storage->findString(['source' => $source_text]);
  if (!$source) {
    // La source n'existe pas encore en base — la créer
    $source = $locale_storage->createString(['source' => $source_text]);
    $source->save();
    echo "Source créée : $source_text (lid: {$source->lid})\n";
  } else {
    echo "Source trouvée : $source_text (lid: {$source->lid})\n";
  }

  // Chercher une traduction existante
  $existing = $locale_storage->findTranslation([
    'lid'      => $source->lid,
    'language' => $langcode,
  ]);

  if ($existing) {
    $existing->setString($translation_text);
    $existing->setCustomized(LOCALE_CUSTOMIZED);
    $existing->save();
    echo "  Traduction mise à jour : $translation_text\n";
  } else {
    $translation = $locale_storage->createTranslation([
      'lid'         => $source->lid,
      'language'    => $langcode,
      'translation' => $translation_text,
      'customized'  => LOCALE_CUSTOMIZED,
    ]);
    $translation->save();
    echo "  Traduction créée : $translation_text\n";
  }
}

// Invalider les caches de traduction
\Drupal::service('string_translator.locale.lookup')->reset();
_locale_invalidate_js($langcode);
locale_js_translate();
