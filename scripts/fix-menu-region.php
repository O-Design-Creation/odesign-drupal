<?php
// Déplacer le menu principal de 'header' vers 'primary_menu'
$block = \Drupal\block\Entity\Block::load('odesign_base_main_menu');
if ($block) {
  $block->setRegion('primary_menu');
  $block->save();
  echo "Menu déplacé vers primary_menu ✓\n";
} else {
  echo "Block odesign_base_main_menu non trouvé\n";
}
