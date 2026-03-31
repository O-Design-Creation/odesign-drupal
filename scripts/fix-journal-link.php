<?php
// Les liens de menu config-based sont dans menu_link.static (plugin system)
// Chercher dans toutes les config commençant par menu_link_content ou views
// Les liens de vues sont généralement des menu_link plugins définis dans views
// Le lien "Journal" vient probablement de la config du menu principal

// Chercher dans la table config
$db = \Drupal::database();

// Les config-based menu links (non-content) sont stockés dans la table 'config'
$result = $db->query("SELECT name, data FROM {config} WHERE name LIKE 'system.menu%' OR name LIKE 'menu_link_content%'")->fetchAll();
foreach ($result as $row) {
  echo $row->name . "\n";
}

// Chercher dans les menu_tree
$result2 = $db->query("SELECT mlid, id, title, menu_name, route_name, url FROM {menu_tree} WHERE (title LIKE '%ournal%' OR title LIKE '%log%') AND menu_name = 'main'")->fetchAll();
foreach ($result2 as $row) {
  echo "Found: {$row->id} | {$row->title} | {$row->url}\n";
  // Mettre à jour
  $db->update('menu_tree')
    ->fields(['title' => serialize('Blog')])
    ->condition('mlid', $row->mlid)
    ->execute();
  echo "Updated to 'Blog' ✓\n";
}

echo "Done.\n";
