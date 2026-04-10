<?php

namespace Drupal\odesign_personalization\EventSubscriber;

use Drupal\commerce_cart\Event\CartEvents;
use Drupal\commerce_cart\Event\CartOrderItemAddEvent;
use Drupal\odesign_personalization\PersonalizationValues;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Persists personalization payload when a cart order item is added.
 */
final class CartOrderItemAddSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      CartEvents::CART_ORDER_ITEM_ADD => ['onCartOrderItemAdd', -50],
    ];
  }

  /**
   * Saves personalization values on the created/combined cart order item.
   */
  public function onCartOrderItemAdd(CartOrderItemAddEvent $event): void {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request) {
      return;
    }

    $sources = [
      (array) $request->request->all(),
      (array) $request->request->get('personalization', []),
    ];
    $values = [];
    foreach ($sources as $source) {
      $values += PersonalizationValues::collectFromInput($source);
    }
    if (!$values) {
      return;
    }

    $orderItem = $event->getOrderItem();
    PersonalizationValues::set($orderItem, $values);
    $orderItem->save();
  }

}
