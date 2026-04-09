<?php

namespace Drupal\odesign_reviews\EventSubscriber;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\odesign_reviews\Service\ReviewManager;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sends review request after delivery transition.
 */
class ReviewRequestSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly ReviewManager $reviewManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'commerce_order.deliver.post_transition' => 'onDelivered',
    ];
  }

  /**
   * Sends one review request mail once the order is delivered.
   */
  public function onDelivered(WorkflowTransitionEvent $event): void {
    $order = $event->getEntity();
    if (!$order instanceof OrderInterface) {
      return;
    }

    $email = (string) $order->getEmail();
    if ($email === '') {
      return;
    }

    $this->reviewManager->createAndSendRequest(
      (int) $order->id(),
      (string) $order->getOrderNumber(),
      $email
    );
  }

}

