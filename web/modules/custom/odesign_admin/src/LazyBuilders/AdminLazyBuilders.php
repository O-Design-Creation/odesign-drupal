<?php

namespace Drupal\odesign_admin\LazyBuilders;

use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Render\FormattableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lazy builders du module odesign_admin.
 */
class AdminLazyBuilders implements TrustedCallbackInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Rendu du bouton "Commandes" dans la barre toolbar Gin.
   *
   * Remplace le bouton "Boîte de réception Commerce" (messages marketing)
   * par un lien direct vers la liste des commandes avec badge du nombre
   * de commandes en attente de traitement.
   */
  public function renderOrdersInbox(): array {
    $actionable_states = ['pending_payment', 'processing', 'quality_check', 'packaged'];

    // Compter les commandes à traiter selon le workflow réel du projet.
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
      '#type'  => 'link',
      '#title' => $title,
      '#url'   => Url::fromRoute('entity.commerce_order.collection', [], [
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
        'tags'     => ['commerce_order_list'],
        'max-age'  => 60,
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
