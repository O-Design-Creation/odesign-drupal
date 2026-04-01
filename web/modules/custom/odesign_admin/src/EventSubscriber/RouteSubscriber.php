<?php

namespace Drupal\odesign_admin\EventSubscriber;

use Symfony\Component\Routing\RouteCollection;
use Drupal\Core\Routing\RouteSubscriberBase;

/**
 * Surcharge l'accès au checkout pour l'étape de confirmation manager.
 */
final class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    if ($route = $collection->get('commerce_checkout.form')) {
      $route->setRequirement('_custom_access', '\Drupal\odesign_admin\Access\CheckoutAccess::checkAccess');
    }
  }

}
