<?php

namespace Drupal\odesign_orders\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\odesign_orders\Order\OrderWorkflowHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Redirection vers la liste des commandes à traiter.
 */
class ActionableOrdersController implements ContainerInjectionInterface {

  public function __construct(
    protected OrderWorkflowHelper $orderWorkflowHelper,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('odesign_orders.order_workflow_helper'),
    );
  }

  /**
   * Redirige vers la vue commandes filtrée sur les états actionnables.
   */
  public function redirect(): RedirectResponse {
    $url = Url::fromRoute('entity.commerce_order.collection', [], [
      'query' => ['state' => $this->orderWorkflowHelper->getActionableStates()],
    ])->toString();

    return new RedirectResponse($url);
  }

}
