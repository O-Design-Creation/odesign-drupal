<?php
// Debug the html_head attachments to find the favicon key
$request = \Drupal::request();
$html_response = \Drupal::service('html_response.attachments_processor');

// Check what key is used for favicon in html_head
$manager = \Drupal::service('asset.resolver');

// Simple check - look for favicon in theme global config
$config = \Drupal::config('system.theme.global');
echo "favicon.use_default: " . ($config->get('favicon.use_default') ? 'true' : 'false') . "\n";
echo "favicon.path: " . $config->get('favicon.path') . "\n";
