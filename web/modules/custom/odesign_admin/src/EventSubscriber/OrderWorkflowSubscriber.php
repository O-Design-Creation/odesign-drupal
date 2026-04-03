<?php

namespace Drupal\odesign_admin\EventSubscriber;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Envoie des notifications email lors des transitions de statut de commande.
 *
 * Les transitions surveillées sont celles du workflow 'odesign_order'.
 * Chaque transition déclenche un email client ET un email interne atelier.
 */
class OrderWorkflowSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly MailManagerInterface $mailManager,
    private readonly LanguageManagerInterface $languageManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

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
   * Mapping transition -> valeur du champ "Suivi interne atelier".
   */
  private const ATELIER_STATUS_BY_TRANSITION = [
    'place' => 'a_verifier',
    'confirm_payment' => 'en_production',
    'quality_check' => 'controle_qualite',
    'pack' => 'emballee_prete',
    'ship' => 'expediee',
    'deliver' => 'termine',
    'cancel' => 'annulee',
    'refund' => 'remboursee',
  ];

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
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
    $this->syncAtelierStatus($order, $transition_id);

    if (in_array($transition_id, self::CLIENT_TRANSITIONS, TRUE)) {
      $this->sendClientNotification($order, $transition_id, $label);
    }

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
    $this->syncAtelierStatus($order, 'place');
    $this->sendAtelierNotification($order, 'place', 'Nouvelle commande reçue');
  }

  /**
   * Met à jour le champ interne atelier selon la transition.
   */
  private function syncAtelierStatus(OrderInterface $order, string $transition_id): void {
    $value = self::ATELIER_STATUS_BY_TRANSITION[$transition_id] ?? NULL;
    if (!$value || !$order->hasField('field_atelier_status')) {
      return;
    }

    if (!$this->isAllowedAtelierStatus($value)) {
      return;
    }

    try {
      $order->set('field_atelier_status', $value);
      $order->save();
    }
    catch (\Throwable $e) {
      // Ne pas bloquer une transition de workflow pour un échec de méta-suivi.
    }
  }

  /**
   * Vérifie qu'une valeur de suivi atelier est autorisée.
   */
  private function isAllowedAtelierStatus(string $value): bool {
    $storage = $this->entityTypeManager
      ->getStorage('field_storage_config')
      ->load('commerce_order.field_atelier_status');
    if (!$storage) {
      return FALSE;
    }

    $allowed = $storage->getSetting('allowed_values') ?? [];
    if (isset($allowed[$value])) {
      return TRUE;
    }

    foreach ($allowed as $item) {
      if (is_array($item) && ($item['value'] ?? NULL) === $value) {
        return TRUE;
      }
    }
    return FALSE;
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

    $this->mailManager->mail(
      'odesign_admin',
      'order_status_' . $transition_id,
      $customer_email,
      $this->languageManager->getDefaultLanguage()->getId(),
      $params,
      $store->getEmail()
    );
  }

  /**
   * Envoie une notification interne à l'équipe atelier.
   */
  private function sendAtelierNotification(OrderInterface $order, string $transition_id, string $label): void {
    $atelier_email = $this->configFactory->get('system.site')->get('mail');
    if (!$atelier_email) {
      return;
    }

    $params = [
      'order'         => $order,
      'transition_id' => $transition_id,
      'label'         => $label,
      'subject'       => '[Atelier] ' . $label . ' — #' . $order->getOrderNumber(),
    ];

    $this->mailManager->mail(
      'odesign_admin',
      'order_atelier_' . $transition_id,
      $atelier_email,
      $this->languageManager->getDefaultLanguage()->getId(),
      $params
    );
  }

}
