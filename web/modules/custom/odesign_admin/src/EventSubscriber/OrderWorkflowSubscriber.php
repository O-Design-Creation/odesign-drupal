<?php

namespace Drupal\odesign_admin\EventSubscriber;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Envoie des notifications email lors des transitions de statut de commande.
 *
 * Les transitions surveillées sont celles du workflow 'odesign_order'.
 * Chaque transition déclenche un email client ET un email interne atelier.
 */
class OrderWorkflowSubscriber implements EventSubscriberInterface {

  /**
   * Labels FR des transitions pour les emails.
   */
  private const TRANSITION_LABELS = [
    'confirm_payment' => 'Votre commande est en fabrication',
    'quality_check'   => 'Votre commande est en contrôle qualité',
    'pack'            => 'Votre commande est prête à être expédiée',
    'ship'            => 'Votre commande a été expédiée',
    'deliver'         => 'Votre commande a été livrée',
    'cancel'          => 'Votre commande a été annulée',
    'refund'          => 'Votre commande a été remboursée',
  ];

  /**
   * Transitions qui génèrent un email CLIENT.
   */
  private const CLIENT_TRANSITIONS = ['confirm_payment', 'ship', 'deliver', 'cancel', 'refund'];

  /**
   * Transitions qui génèrent un email interne ATELIER.
   */
  private const ATELIER_TRANSITIONS = ['place', 'confirm_payment', 'quality_check', 'pack', 'cancel', 'refund'];

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Format : commerce_order.TRANSITION_ID.post_transition
      'commerce_order.confirm_payment.post_transition' => 'onTransition',
      'commerce_order.quality_check.post_transition'   => 'onTransition',
      'commerce_order.pack.post_transition'            => 'onTransition',
      'commerce_order.ship.post_transition'            => 'onTransition',
      'commerce_order.deliver.post_transition'         => 'onTransition',
      'commerce_order.cancel.post_transition'          => 'onTransition',
      'commerce_order.refund.post_transition'          => 'onTransition',
      'commerce_order.place.post_transition'           => 'onNewOrder',
    ];
  }

  /**
   * Réagit aux transitions du workflow commande.
   */
  public function onTransition(WorkflowTransitionEvent $event): void {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    if (!$order instanceof OrderInterface) {
      return;
    }

    $transition_id = $event->getTransition()->getId();
    $label = self::TRANSITION_LABELS[$transition_id] ?? $transition_id;

    // Email client
    if (in_array($transition_id, self::CLIENT_TRANSITIONS, TRUE)) {
      $this->sendClientNotification($order, $transition_id, $label);
    }

    // Email interne atelier
    if (in_array($transition_id, self::ATELIER_TRANSITIONS, TRUE)) {
      $this->sendAtelierNotification($order, $transition_id, $label);
    }
  }

  /**
   * Réagit à la prise de commande (nouvelle commande).
   */
  public function onNewOrder(WorkflowTransitionEvent $event): void {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    if (!$order instanceof OrderInterface) {
      return;
    }
    // Email de confirmation commande (receipt) déjà géré par Commerce.
    // On envoie uniquement la notif interne atelier.
    $this->sendAtelierNotification($order, 'place', 'Nouvelle commande reçue');
  }

  /**
   * Envoie un email de notification au client.
   */
  private function sendClientNotification(OrderInterface $order, string $transition_id, string $label): void {
    $customer_email = $order->getEmail();
    if (!$customer_email) {
      return;
    }

    $store = $order->getStore();
    $params = [
      'order'         => $order,
      'transition_id' => $transition_id,
      'label'         => $label,
      'subject'       => $label . ' — Commande #' . $order->getOrderNumber(),
    ];

    \Drupal::service('plugin.manager.mail')->mail(
      'odesign_admin',
      'order_status_' . $transition_id,
      $customer_email,
      \Drupal::languageManager()->getDefaultLanguage()->getId(),
      $params,
      $store->getEmail()
    );
  }

  /**
   * Envoie une notification interne à l'équipe atelier.
   */
  private function sendAtelierNotification(OrderInterface $order, string $transition_id, string $label): void {
    $atelier_email = \Drupal::config('system.site')->get('mail');
    if (!$atelier_email) {
      return;
    }

    $params = [
      'order'         => $order,
      'transition_id' => $transition_id,
      'label'         => $label,
      'subject'       => '[Atelier] ' . $label . ' — #' . $order->getOrderNumber(),
    ];

    \Drupal::service('plugin.manager.mail')->mail(
      'odesign_admin',
      'order_atelier_' . $transition_id,
      $atelier_email,
      \Drupal::languageManager()->getDefaultLanguage()->getId(),
      $params
    );
  }

}
