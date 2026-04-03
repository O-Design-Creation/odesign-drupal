<?php

namespace Drupal\odesign_orders\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\odesign_orders\Order\OrderWorkflowHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Page "Commandes à traiter" — liste des commandes dans les états actionnables.
 *
 * Remplace l'ancienne redirection vers la vue Commerce par une vraie page
 * gestionnaire avec liens directs vers les pages de détail.
 */
class ActionableOrdersController extends ControllerBase {

  public function __construct(
    protected OrderWorkflowHelper $orderWorkflowHelper,
    protected Connection $database,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('odesign_orders.order_workflow_helper'),
      $container->get('database'),
    );
  }

  /**
   * Affiche la liste des commandes à traiter.
   */
  public function page(): array {
    $orders = $this->loadActionableOrders();

    return [
      '#theme'    => 'odesign_orders_actionable',
      '#orders'   => $orders,
      '#all_orders_url' => Url::fromRoute('entity.commerce_order.collection')->toString(),
      '#cache'    => ['max-age' => 0],
      '#attached' => ['library' => ['odesign_orders/orders']],
    ];
  }

  /**
   * Charge les commandes dans les états actionnables.
   *
   * @return array<int, array{
   *   id: int,
   *   number: string,
   *   customer: string,
   *   email: string,
   *   state_id: string,
   *   state_label: string,
   *   total: string,
   *   currency: string,
   *   date: string,
   *   detail_url: string,
   *   edit_url: string,
   * }>
   */
  private function loadActionableOrders(): array {
    $state_labels = [
      'pending_payment' => 'En attente de paiement',
      'processing'      => 'En fabrication',
      'quality_check'   => 'Contrôle qualité',
      'packaged'        => 'Emballée / prête à expédier',
    ];

    try {
      $result = $this->database->select('commerce_order', 'o')
        ->fields('o', ['order_id', 'order_number', 'state', 'placed', 'total_price__number', 'total_price__currency_code'])
        ->condition('o.state', $this->orderWorkflowHelper->getActionableStates(), 'IN')
        ->orderBy('o.placed', 'ASC')
        ->execute()
        ->fetchAll();
    }
    catch (\Exception) {
      return [];
    }

    // Charger les noms clients en batch.
    $order_ids = array_column((array) $result, 'order_id');
    $customer_names = $this->loadCustomerNames($order_ids);

    $orders = [];
    foreach ($result as $row) {
      $orders[] = [
        'id'          => (int) $row->order_id,
        'number'      => $row->order_number ?: ('#' . $row->order_id),
        'customer'    => $customer_names[$row->order_id]['name'] ?? '—',
        'email'       => $customer_names[$row->order_id]['email'] ?? '—',
        'state_id'    => $row->state,
        'state_label' => $state_labels[$row->state] ?? $row->state,
        'total'       => $row->total_price__number
          ? number_format((float) $row->total_price__number, 2, ',', ' ')
          : '—',
        'currency'    => $row->total_price__currency_code ?? 'EUR',
        'date'        => $row->placed ? date('d/m/Y', $row->placed) : '—',
        'detail_url'  => Url::fromRoute('odesign_orders.order_detail', ['commerce_order' => $row->order_id])->toString(),
        'edit_url'    => Url::fromRoute('entity.commerce_order.edit_form', ['commerce_order' => $row->order_id])->toString(),
      ];
    }
    return $orders;
  }

  /**
   * @param int[] $order_ids
   * @return array<int, array{name: string, email: string}>
   */
  private function loadCustomerNames(array $order_ids): array {
    if (!$order_ids) {
      return [];
    }
    $names = [];
    try {
      $rows = $this->database->select('commerce_order', 'o')
        ->fields('o', ['order_id', 'uid', 'mail'])
        ->condition('o.order_id', $order_ids, 'IN')
        ->execute()
        ->fetchAllAssoc('order_id');

      $uids = array_filter(array_column((array) $rows, 'uid'));
      $users = \Drupal::entityTypeManager()->getStorage('user')->loadMultiple($uids);

      foreach ($rows as $order_id => $row) {
        $user = $users[$row->uid] ?? NULL;
        $names[(int) $order_id] = [
          'name'  => $user ? $user->getDisplayName() : ($row->mail ?? '—'),
          'email' => $row->mail ?? ($user ? $user->getEmail() : '—'),
        ];
      }
    }
    catch (\Exception) {}
    return $names;
  }

}
