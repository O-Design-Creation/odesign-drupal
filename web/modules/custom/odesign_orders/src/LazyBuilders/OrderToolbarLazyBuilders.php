<?php

namespace Drupal\odesign_orders\LazyBuilders;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Url;
use Drupal\odesign_orders\Order\OrderWorkflowHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lazy builders liés au périmètre commandes.
 */
class OrderToolbarLazyBuilders implements TrustedCallbackInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected OrderWorkflowHelper $orderWorkflowHelper,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('odesign_orders.order_workflow_helper'),
    );
  }

  /**
   * Rendu du bouton "Commandes" dans la toolbar.
   */
  public function renderOrdersInbox(): array {
    $actionable_states = $this->orderWorkflowHelper->getActionableStates();

    $pending_count = $this->entityTypeManager
      ->getStorage('commerce_order')
      ->getQuery()
      ->condition('state', $actionable_states, 'IN')
      ->accessCheck(FALSE)
      ->count()
      ->execute();

    $title = $pending_count
      ? new FormattableMarkup('Commandes <span class="odesign-toolbar-badge">@count</span>', ['@count' => $pending_count])
      : t('Commandes');

    return [
      '#type' => 'link',
      '#title' => $title,
      '#url' => Url::fromRoute('entity.commerce_order.collection', [], [
        'query' => ['state' => $actionable_states],
      ]),
      '#attributes' => [
        'title' => t('Voir les commandes en attente de traitement.'),
        'class' => [
          'toolbar-icon',
          'toolbar-item',
          'toolbar-icon-commerce-inbox',
          'odesign-toolbar-orders',
        ],
      ],
      '#cache' => [
        'contexts' => ['user.permissions', 'user.roles'],
        'tags' => ['commerce_order_list'],
        'max-age' => 60,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['renderOrdersInbox'];
  }

}
