<?php

namespace Drupal\odesign_orders\EventSubscriber;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Event\PaymentEvent;
use Drupal\commerce_payment\Event\PaymentEvents;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Automatise la transition confirm_payment quand le paiement Stripe est reçu.
 *
 * Deux chemins possibles selon le timing Commerce Stripe :
 * 1. Payment INSERT/UPDATE avec order déjà en pending_payment → confirm_payment
 * 2. Order passe en pending_payment (place.post_transition) avec un paiement
 *    déjà completed → confirm_payment (cas Commerce Stripe redirect)
 *
 * Idempotent : guards sur state order + disponibilité transition.
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
      // Cas Commerce Stripe : le paiement est completed avant que l'ordre soit
      // placé. On rattrape ici quand l'ordre entre en pending_payment.
      'commerce_order.place.post_transition' => 'onOrderPlaced',
    ];
  }

  /**
   * Déclenche confirm_payment quand le paiement est complété.
   *
   * Cas 1 : order déjà en pending_payment au moment du save payment.
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

    $this->maybeConfirmPayment($order);
  }

  /**
   * Déclenche confirm_payment si un paiement completed existe déjà.
   *
   * Cas 2 : Commerce Stripe redirect — payment completed avant place.
   */
  public function onOrderPlaced(WorkflowTransitionEvent $event): void {
    if (self::$processing) {
      return;
    }

    $order = $event->getEntity();
    if (!$order instanceof OrderInterface) {
      return;
    }

    // Vérifier qu'il existe un paiement completed sur cette commande.
    $payments = \Drupal::entityTypeManager()
      ->getStorage('commerce_payment')
      ->loadByProperties(['order_id' => $order->id()]);

    foreach ($payments as $payment) {
      if ($payment->getState()->getId() === 'completed') {
        $this->maybeConfirmPayment($order);
        return;
      }
    }
  }

  /**
   * Applique confirm_payment si l'ordre est en pending_payment.
   */
  private function maybeConfirmPayment(OrderInterface $order): void {
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

      $this->loggerFactory->get('odesign')->info(
        'Transition confirm_payment appliquée automatiquement sur commande @id.',
        ['@id' => $order->id()]
      );
    }
    catch (\Throwable $e) {
      $this->loggerFactory->get('odesign')->error(
        'Échec confirm_payment automatique commande @id : @msg',
        ['@id' => $order->id(), '@msg' => $e->getMessage()]
      );
    }
    finally {
      self::$processing = FALSE;
    }
  }

}
