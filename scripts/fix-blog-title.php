<?php
$view = \Drupal\views\Entity\View::load('blog');
if ($view) {
  $display = &$view->getDisplay('page_blog');
  if ($display) {
    $display['display_options']['title'] = 'Blog';
    $view->save();
    echo "Vue blog : titre → 'Blog' ✓\n";
  } else {
    echo "Display page_blog non trouvé\n";
    print_r(array_keys($view->get('display')));
  }
} else {
  echo "Vue 'blog' non trouvée\n";
}
