<?php

namespace Drupal\odesign_orders\EventSubscriber;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Event\PaymentEvent;
use Drupal\commerce_payment\Event\PaymentEvents;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Automatise la transition confirm_payment quand le paiement Stripe est reçu.
 *
 * Écoute PAYMENT_INSERT et PAYMENT_UPDATE. Dès qu'un paiement passe à l'état
 * "completed" sur une commande en "pending_payment", applique la transition
 * confirm_payment → processing sans intervention manuelle du gestionnaire.
 *
 * Idempotent : si la commande n'est plus en pending_payment, aucune action.
 */
class PaymentConfirmSubscriber implements EventSubscriberInterface {

  /**
   * Flag anti-réentrance pour éviter les récursions order→payment→order.
   */
  private static bool $processing = FALSE;

  public function __construct(
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PaymentEvents::PAYMENT_INSERT => 'onPaymentSaved',
      PaymentEvents::PAYMENT_UPDATE => 'onPaymentSaved',
    ];
  }

  /**
   * Déclenche confirm_payment quand le paiement est complété.
   */
  public function onPaymentSaved(PaymentEvent $event): void {
    if (self::$processing) {
      return;
    }

    $payment = $event->getPayment();

    if ($payment->getState()->getId() !== 'completed') {
      return;
    }

    $order = $payment->getOrder();
    if (!$order instanceof OrderInterface) {
      return;
    }

    if ($order->getState()->getId() !== 'pending_payment') {
      return;
    }

    $transitions = $order->getState()->getTransitions();
    if (!isset($transitions['confirm_payment'])) {
      return;
    }

    self::$processing = TRUE;
    try {
      $order->getState()->applyTransitionById('confirm_payment');
      $order->save();

      $this->loggerFactory->get('odesign_orders')->info(
        'Transition confirm_payment appliquée automatiquement sur commande @id (paiement @payment_id).',
        ['@id' => $order->id(), '@payment_id' => $payment->id()]
      );
    }
    catch (\Throwable $e) {
      $this->loggerFactory->get('odesign_orders')->error(
        'Échec confirm_payment automatique commande @id : @msg',
        ['@id' => $order->id(), '@msg' => $e->getMessage()]
      );
    }
    finally {
      self::$processing = FALSE;
    }
  }

}
