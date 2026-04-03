<?php

namespace Drupal\odesign_orders\Dashboard;

use Drupal\Core\Database\Connection;
use Drupal\odesign_orders\Order\OrderWorkflowHelper;

/**
 * Fournit les statistiques commandes pour le dashboard gestionnaire.
 */
class OrderStatsProvider {

  public function __construct(
    protected Connection $database,
    protected OrderWorkflowHelper $orderWorkflowHelper,
  ) {}

  /**
   * Retourne les compteurs commandes (à traiter + total).
   */
  public function getStats(): array {
    $stats = [
      'pending_orders' => 0,
      'total_orders' => 0,
    ];

    try {
      $pending = $this->database->select('commerce_order', 'o')
        ->condition('o.state', $this->orderWorkflowHelper->getActionableStates(), 'IN')
        ->countQuery()
        ->execute()
        ->fetchField();
      $stats['pending_orders'] = (int) $pending;
    }
    catch (\Exception $e) {
      $stats['pending_orders'] = 0;
    }

    try {
      $total_orders = $this->database->select('commerce_order', 'o')
        ->condition('o.state', 'draft', '<>')
        ->countQuery()
        ->execute()
        ->fetchField();
      $stats['total_orders'] = (int) $total_orders;
    }
    catch (\Exception $e) {
      $stats['total_orders'] = 0;
    }

    return $stats;
  }

}
