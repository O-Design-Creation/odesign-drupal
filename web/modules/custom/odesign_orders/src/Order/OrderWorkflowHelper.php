<?php

namespace Drupal\odesign_orders\Order;

/**
 * Centralise les états métier du workflow commandes.
 */
class OrderWorkflowHelper {

  /**
   * États considérés comme "à traiter" côté atelier.
   */
  public function getActionableStates(): array {
    return [
      'pending_payment',
      'processing',
      'quality_check',
      'packaged',
    ];
  }

}
