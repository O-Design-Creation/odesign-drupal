<?php
// Vérifier si le module menu_link_content est installé
$moduleHandler = \Drupal::moduleHandler();
if (!$moduleHandler->moduleExists('menu_link_content')) {
  echo "Module menu_link_content non installé\n";
}

// Chercher dans les config des menu links (views crée des plugin-based links)
$menu_tree = \Drupal::menuTree();
$params = new \Drupal\Core\Menu\MenuTreeParameters();
$tree = $menu_tree->load('main', $params);
foreach ($tree as $element) {
  $link = $element->link;
  echo "Link: " . $link->getTitle() . " | " . $link->getUrlObject()->toString() . "\n";
}

// Corriger le titre de la vue blog directement dans la config
$config = \Drupal::service('config.factory')->getEditable('views.view.blog');
$display = $config->get('display.page_blog.display_options.title');
echo "Titre actuel vue blog: $display\n";
$config->set('display.page_blog.display_options.title', 'Blog');
$config->save();
echo "Titre corrigé → Blog ✓\n";
