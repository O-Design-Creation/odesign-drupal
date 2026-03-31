<?php
use Drupal\block\Entity\Block;

$theme = 'odesign_base';
$existing = \Drupal::entityTypeManager()->getStorage('block')->loadByProperties(['theme' => $theme]);
$existing_ids = array_map(fn($b) => $b->getPluginId(), $existing);

echo "Blocs existants pour $theme:\n";
foreach ($existing as $b) {
  echo "  - {$b->id()} ({$b->getPluginId()}) region={$b->getRegion()}\n";
}

// Placer system_main_block si manquant
if (!in_array('system_main_block', $existing_ids)) {
  Block::create([
    'id' => 'odesign_base_main',
    'plugin' => 'system_main_block',
    'region' => 'content',
    'theme' => $theme,
    'weight' => 0,
    'settings' => ['label_display' => FALSE, 'label' => 'Main page content'],
    'status' => TRUE,
    'visibility' => [],
  ])->save();
  echo "system_main_block placé dans 'content'\n";
} else {
  echo "system_main_block déjà présent\n";
}

// Placer page_title_block si manquant
if (!in_array('page_title_block', $existing_ids)) {
  Block::create([
    'id' => 'odesign_base_page_title',
    'plugin' => 'page_title_block',
    'region' => 'content',
    'theme' => $theme,
    'weight' => -10,
    'settings' => ['label_display' => FALSE, 'label' => 'Page title'],
    'status' => TRUE,
    'visibility' => [],
  ])->save();
  echo "page_title_block placé dans 'content'\n";
} else {
  echo "page_title_block déjà présent\n";
}

// Placer navigation principale si absente
if (!in_array('system_menu_block:main', $existing_ids)) {
  Block::create([
    'id' => 'odesign_base_main_menu',
    'plugin' => 'system_menu_block:main',
    'region' => 'header',
    'theme' => $theme,
    'weight' => 0,
    'settings' => ['label_display' => FALSE, 'label' => 'Navigation principale', 'level' => 1, 'depth' => 0],
    'status' => TRUE,
    'visibility' => [],
  ])->save();
  echo "Menu principal placé dans 'header'\n";
}

// Messages de statut (Drupal status messages)
if (!in_array('system_messages_block', $existing_ids)) {
  Block::create([
    'id' => 'odesign_base_messages',
    'plugin' => 'system_messages_block',
    'region' => 'highlighted',
    'theme' => $theme,
    'weight' => -10,
    'settings' => ['label_display' => FALSE, 'label' => 'Messages'],
    'status' => TRUE,
    'visibility' => [],
  ])->save();
  echo "Messages block placé dans 'highlighted'\n";
}

// Breadcrumb
if (!in_array('system_breadcrumb_block', $existing_ids)) {
  Block::create([
    'id' => 'odesign_base_breadcrumb',
    'plugin' => 'system_breadcrumb_block',
    'region' => 'breadcrumb',
    'theme' => $theme,
    'weight' => 0,
    'settings' => ['label_display' => FALSE, 'label' => 'Breadcrumb'],
    'status' => TRUE,
    'visibility' => [],
  ])->save();
  echo "Breadcrumb placé dans 'breadcrumb'\n";
}

echo "\nDone.\n";
