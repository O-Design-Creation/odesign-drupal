<?php

namespace Drupal\odesign_admin\Access;

use Drupal\commerce_cart\CartSession;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Ajuste l'accès checkout pour l'étape de confirmation visible au manager.
 */
final class CheckoutAccess implements AccessInterface, ContainerInjectionInterface {

  /**
   * The cart session service.
   */
  protected CartSession $cartSession;

  /**
   * Constructs the access checker.
   */
  public function __construct(CartSession $cart_session) {
    $this->cartSession = $cart_session;
  }

  /**
   * Creates the access checker from the container.
   */
  public static function create(ContainerInterface $container): self {
    return new self($container->get('commerce_cart.cart_session'));
  }

  /**
   * Checks access for the checkout form.
   */
  public function checkAccess(RouteMatchInterface $route_match, AccountInterface $account): AccessResult {
    $order = $route_match->getParameter('commerce_order');
    if (!$order instanceof OrderInterface) {
      return AccessResult::neutral();
    }

    if ($order->getState()->getId() === 'canceled') {
      return AccessResult::forbidden()->addCacheableDependency($order);
    }

    $step_id = $route_match->getParameter('step');
    $can_view_order = $order->access('view', $account, TRUE);
    if ($step_id === 'complete' && $account->isAuthenticated() && $can_view_order->isAllowed()) {
      return AccessResult::allowed()
        ->andIf(AccessResult::allowedIfHasPermission($account, 'access checkout'))
        ->addCacheableDependency($order->getStore())
        ->addCacheableDependency($order);
    }

    if ($account->isAuthenticated()) {
      $customer_check = $account->id() == $order->getCustomerId();
    }
    else {
      $active_cart = $this->cartSession->hasCartId($order->id(), CartSession::ACTIVE);
      $completed_cart = $this->cartSession->hasCartId($order->id(), CartSession::COMPLETED);
      $customer_check = $active_cart || $completed_cart;
    }

    return AccessResult::allowedIf($customer_check)
      ->andIf(AccessResult::allowedIf($order->getStore()->isPublished()))
      ->andIf(AccessResult::allowedIf($order->hasItems()))
      ->andIf(AccessResult::allowedIfHasPermission($account, 'access checkout'))
      ->addCacheableDependency($order->getStore())
      ->addCacheableDependency($order);
  }

}
