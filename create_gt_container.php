<?php
use Drupal\Core\Serialization\Yaml;
$yaml_content = file_get_contents('/var/www/html/config/sync/google_tag.container.default.yml');
if (!$yaml_content) {
  echo "YAML file not found\n";
  return;
}
$config_data = Yaml::decode($yaml_content);
$storage = \Drupal::entityTypeManager()->getStorage('google_tag_container');
$existing = $storage->load('default');
if ($existing) {
  echo "Container already exists with id: " . $existing->id() . "\n";
} else {
  $entity = $storage->create($config_data);
  $entity->save();
  echo "Container created: " . $entity->id() . "\n";
}
