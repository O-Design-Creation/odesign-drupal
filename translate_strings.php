<?php
$strings = [
  'Per customer limit' => 'Limite par client',
  'Compatibility'      => 'Compatibilité',
  'Coupons'            => 'Codes promo',
  'Current currency'   => 'Devise actuelle',
];

$langcode = 'fr';
$locale_storage = \Drupal::service('locale.storage');

foreach ($strings as $source => $translation) {
  try {
    $source_obj = $locale_storage->findString(['source' => $source]);
    if (!$source_obj) {
      $source_obj = $locale_storage->createString(['source' => $source])->save();
    }
    $trans_obj = $locale_storage->findTranslation([
      'lid'      => $source_obj->lid,
      'language' => $langcode,
    ]);
    if (!$trans_obj) {
      $locale_storage->createTranslation([
        'lid'         => $source_obj->lid,
        'language'    => $langcode,
        'translation' => $translation,
        'customized'  => LOCALE_CUSTOMIZED,
      ])->save();
    } else {
      $trans_obj->setString($translation)->setCustomized()->save();
    }
    echo "OK: $source → $translation\n";
  } catch (\Exception $e) {
    echo "ERREUR: $source: " . $e->getMessage() . "\n";
  }
}

// Rebuild locale cache
\Drupal::service('locale.lookup')->reset();
