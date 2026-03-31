<?php
$storage = \Drupal::entityTypeManager()->getStorage('node');
$nodes = $storage->loadByProperties(['type' => 'article']);
foreach ($nodes as $n) {
  $b = $n->get('body')->first();
  $sum = $b ? $b->get('summary')->getString() : '';
  $val = $b ? substr($b->get('value')->getString(), 0, 60) : '';
  $token = \Drupal::service('token')->replace('[node:summary]', ['node' => $n]);
  echo $n->label() . "\n";
  echo "  summary len=" . strlen($sum) . " : " . substr($sum, 0, 80) . "\n";
  echo "  body val: " . $val . "\n";
  echo "  token[node:summary]: " . substr($token, 0, 100) . "\n\n";
}
