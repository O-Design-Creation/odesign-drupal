<?php

namespace Drupal\odesign_orders\Controller;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\odesign_orders\Order\OrderWorkflowHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Page de détail commande gestionnaire boutique.
 *
 * Vue simplifiée d'une commande : informations client, articles,
 * totaux et boutons de transition workflow en langage métier.
 */
class OrderDetailController extends ControllerBase {

  public function __construct(
    protected OrderWorkflowHelper $orderWorkflowHelper,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('odesign_orders.order_workflow_helper'),
    );
  }

  /**
   * Affiche la page de détail d'une commande.
   */
  public function page(OrderInterface $commerce_order): array {
    $order    = $commerce_order;
    $state_id = $order->getState()->getId();

    // Transitions disponibles depuis l'état courant.
    $transitions = $this->getAvailableTransitions($order);

    // Informations client.
    $customer = $order->getCustomer();
    $billing  = $order->getBillingProfile();
    $shipping = $this->getShippingProfile($order);

    // Articles commandés.
    $items = [];
    foreach ($order->getItems() as $item) {
      $items[] = [
        'title'     => $item->getTitle(),
        'qty'       => (int) $item->getQuantity(),
        'unit_price' => $item->getUnitPrice() ? $item->getUnitPrice()->getNumber() : '0.00',
        'subtotal'  => $item->getTotalPrice() ? $item->getTotalPrice()->getNumber() : '0.00',
        'currency'  => $item->getTotalPrice() ? $item->getTotalPrice()->getCurrencyCode() : 'EUR',
      ];
    }

    $total_price = $order->getTotalPrice();

    return [
      '#theme'      => 'odesign_orders_order_detail',
      '#order_id'   => $order->id(),
      '#order_number' => $order->getOrderNumber() ?: ('#' . $order->id()),
      '#order_date' => $order->getPlacedTime() ? date('d/m/Y à H\hi', $order->getPlacedTime()) : '—',
      '#state_id'   => $state_id,
      '#state_label' => $this->getStateLabel($state_id),
      '#customer_name'  => $customer ? $customer->getDisplayName() : '—',
      '#customer_email' => $customer ? $customer->getEmail() : '—',
      '#billing_address'  => $billing ? $this->formatAddress($billing) : NULL,
      '#shipping_address' => $shipping ? $this->formatAddress($shipping) : NULL,
      '#items'      => $items,
      '#total'      => $total_price ? number_format((float) $total_price->getNumber(), 2, ',', ' ') : '—',
      '#currency'   => $total_price ? $total_price->getCurrencyCode() : 'EUR',
      '#transitions' => $transitions,
      '#tracking_number' => $order->hasField('field_tracking_number') ? ($order->get('field_tracking_number')->value ?? '') : '',
      '#tracking_url'    => Url::fromRoute('odesign_orders.order_tracking', ['commerce_order' => $order->id()])->toString(),
      '#tracking_token'  => \Drupal::csrfToken()->get('tracking_' . $order->id()),
      '#shipping_method_label' => $this->getShippingMethodLabel($order),
      '#tracking_link'   => odesign_orders_build_tracking_link(
        $this->getShippingMethodLabel($order),
        $order->hasField('field_tracking_number') ? ($order->get('field_tracking_number')->value ?? '') : ''
      ),
      '#edit_url'   => Url::fromRoute('entity.commerce_order.edit_form', ['commerce_order' => $order->id()])->toString(),
      '#back_url'   => Url::fromRoute('odesign_orders.actionable_orders')->toString(),
      '#cache'      => ['max-age' => 0],
      '#attached'   => ['library' => ['odesign_orders/orders']],
    ];
  }

  /**
   * Applique une transition workflow à une commande.
   *
   * Route : POST /admin/boutique/commandes/{commerce_order}/transition/{transition}
   */
  public function applyTransition(Request $request, OrderInterface $commerce_order, string $transition): RedirectResponse {
    // Vérification CSRF.
    $token = $request->request->get('token');
    if (!\Drupal::csrfToken()->validate($token, 'transition_' . $commerce_order->id() . '_' . $transition)) {
      $this->messenger()->addError('Action non autorisée (token invalide).');
      return new RedirectResponse(Url::fromRoute('odesign_orders.order_detail', ['commerce_order' => $commerce_order->id()])->toString());
    }

    $available = $this->getAvailableTransitions($commerce_order);
    $found = NULL;
    foreach ($available as $t) {
      if ($t['id'] === $transition) {
        $found = $t;
        break;
      }
    }

    if (!$found) {
      $this->messenger()->addError('Transition non disponible pour cet état.');
      return new RedirectResponse(Url::fromRoute('odesign_orders.order_detail', ['commerce_order' => $commerce_order->id()])->toString());
    }

    try {
      $commerce_order->getState()->applyTransitionById($transition);
      $commerce_order->save();
      $this->messenger()->addStatus('Commande ' . ($commerce_order->getOrderNumber() ?: '#' . $commerce_order->id()) . ' mise à jour : ' . $found['label']);
    }
    catch (\Exception $e) {
      $this->messenger()->addError('Erreur lors de la mise à jour : ' . $e->getMessage());
    }

    return new RedirectResponse(Url::fromRoute('odesign_orders.order_detail', ['commerce_order' => $commerce_order->id()])->toString());
  }

  /**
   * Enregistre le numéro de suivi transporteur.
   *
   * Route : POST /admin/boutique/commandes/{commerce_order}/tracking
   */
  public function saveTracking(Request $request, OrderInterface $commerce_order): RedirectResponse {
    $token = $request->request->get('token');
    if (!\Drupal::csrfToken()->validate($token, 'tracking_' . $commerce_order->id())) {
      $this->messenger()->addError('Action non autorisée.');
      return new RedirectResponse(Url::fromRoute('odesign_orders.order_detail', ['commerce_order' => $commerce_order->id()])->toString());
    }

    $tracking = trim($request->request->get('tracking_number', ''));
    if ($commerce_order->hasField('field_tracking_number')) {
      $commerce_order->set('field_tracking_number', $tracking ?: NULL);
      $commerce_order->save();
      $this->messenger()->addStatus($tracking
        ? 'Numéro de suivi enregistré : ' . $tracking
        : 'Numéro de suivi supprimé.'
      );
    }

    return new RedirectResponse(Url::fromRoute('odesign_orders.order_detail', ['commerce_order' => $commerce_order->id()])->toString());
  }

  // ─── Helpers ──────────────────────────────────────────────────────────────

  /**
   * Retourne les transitions disponibles depuis l'état courant, avec token CSRF.
   */
  private function getAvailableTransitions(OrderInterface $order): array {
    $transitions = [];
    try {
      $available = $order->getState()->getTransitions();
      foreach ($available as $id => $transition) {
        $transitions[] = [
          'id'    => $id,
          'label' => $transition->getLabel(),
          'url'   => Url::fromRoute('odesign_orders.order_transition', [
            'commerce_order' => $order->id(),
            'transition'     => $id,
          ])->toString(),
          'token' => \Drupal::csrfToken()->get('transition_' . $order->id() . '_' . $id),
        ];
      }
    }
    catch (\Exception) {
      // État sans transitions (ex : completed).
    }
    return $transitions;
  }

  private function getStateLabel(string $state_id): string {
    $labels = [
      'draft'           => 'Brouillon',
      'pending_payment' => 'En attente de paiement',
      'processing'      => 'En fabrication',
      'quality_check'   => 'Contrôle qualité',
      'packaged'        => 'Emballée / prête à expédier',
      'shipped'         => 'Expédiée',
      'completed'       => 'Livrée',
      'canceled'        => 'Annulée',
      'refunded'        => 'Remboursée',
    ];
    return $labels[$state_id] ?? $state_id;
  }

  private function getShippingMethodLabel(OrderInterface $order): string {
    try {
      if ($order->hasField('shipments')) {
        foreach ($order->get('shipments')->referencedEntities() as $shipment) {
          if (method_exists($shipment, 'getShippingMethod')) {
            $method = $shipment->getShippingMethod();
            if ($method) {
              return $method->getName();
            }
          }
        }
      }
      // Fallback : chercher dans les ajustements de type shipping.
      foreach ($order->getAdjustments(['shipping']) as $adjustment) {
        if ($adjustment->getLabel()) {
          return $adjustment->getLabel();
        }
      }
    }
    catch (\Exception) {}
    return '';
  }

  private function getShippingProfile(OrderInterface $order): mixed {
    // Commerce Shipping stocke le profil dans le champ `shipments`.
    try {
      if ($order->hasField('shipments')) {
        foreach ($order->get('shipments')->referencedEntities() as $shipment) {
          if (method_exists($shipment, 'getShippingProfile') && $shipment->getShippingProfile()) {
            return $shipment->getShippingProfile();
          }
        }
      }
    }
    catch (\Exception) {}
    return NULL;
  }

  /**
   * Formate une adresse profile en tableau de lignes.
   *
   * @return array<string, string>
   */
  private function formatAddress(mixed $profile): array {
    $address = [];
    try {
      if ($profile && $profile->hasField('address')) {
        $field = $profile->get('address')->first();
        if ($field) {
          $address['name']        = trim(($field->getGivenName() ?? '') . ' ' . ($field->getFamilyName() ?? ''));
          $address['address_line1'] = $field->getAddressLine1() ?? '';
          $address['address_line2'] = $field->getAddressLine2() ?? '';
          $address['city']         = $field->getLocality() ?? '';
          $address['postal_code']  = $field->getPostalCode() ?? '';
          $address['country']      = $field->getCountryCode() ?? '';
          $address['phone']        = $profile->hasField('field_phone') ? ($profile->get('field_phone')->value ?? '') : '';
        }
      }
    }
    catch (\Exception) {}
    return array_filter($address);
  }

}
