<?php

namespace Drupal\odesign_orders\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Surcharge l'accès checkout pour l'étape de confirmation manager.
 */
final class CheckoutRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    if ($route = $collection->get('commerce_checkout.form')) {
      $route->setRequirement('_custom_access', '\Drupal\odesign_orders\Access\CheckoutAccess::checkAccess');
    }
  }

}
