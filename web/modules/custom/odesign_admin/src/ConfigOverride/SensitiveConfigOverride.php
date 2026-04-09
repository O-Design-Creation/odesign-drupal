<?php

namespace Drupal\odesign_admin\ConfigOverride;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\State\StateInterface;

/**
 * Runtime overrides for sensitive values that should not live in config export.
 */
class SensitiveConfigOverride implements ConfigFactoryOverrideInterface {

  /**
   * State storage.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * Constructs the override service.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    $overrides = [];

    if (in_array('smtp.settings', $names, TRUE)) {
      $smtp_password = (string) $this->state->get('odesign_admin.smtp_password', '');
      if ($smtp_password !== '') {
        $overrides['smtp.settings']['smtp_password'] = $smtp_password;
      }
    }

    $stripe_config_name = 'commerce_payment.commerce_payment_gateway.stripe_sandbox';
    if (in_array($stripe_config_name, $names, TRUE)) {
      $secret_key = (string) $this->state->get('odesign_admin.stripe_sandbox.secret_key', '');
      $webhook_secret = (string) $this->state->get('odesign_admin.stripe_sandbox.webhook_secret', '');
      if ($secret_key !== '') {
        $overrides[$stripe_config_name]['configuration']['secret_key'] = $secret_key;
      }
      if ($webhook_secret !== '') {
        $overrides[$stripe_config_name]['configuration']['webhook_secret'] = $webhook_secret;
      }
    }

    return $overrides;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return 'OdesignAdminSensitiveConfigOverride';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name) {
    return new CacheableMetadata();
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
    return NULL;
  }

}
