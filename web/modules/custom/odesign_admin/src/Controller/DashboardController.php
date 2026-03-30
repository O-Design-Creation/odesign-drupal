<?php

namespace Drupal\odesign_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tableau de bord du gestionnaire boutique.
 */
class DashboardController extends ControllerBase {

  protected Connection $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'));
  }

  /**
   * Page principale du tableau de bord.
   */
  public function page(): array {
    $build = [];

    // Statistiques rapides.
    $stats = $this->getStats();

    // Vue des commandes récentes.
    $orders_view = NULL;
    $view = Views::getView('commerce_orders');
    if ($view && $view->access('default')) {
      $view->setDisplay('default');
      $view->setItemsPerPage(10);
      $view->execute();
      $orders_view = $view->buildRenderable('default', [], FALSE);
    }

    $build['dashboard'] = [
      '#theme' => 'odesign_admin_dashboard',
      '#stats' => $stats,
      '#orders_view' => $orders_view,
      '#quick_links' => $this->getQuickLinks(),
      '#cache' => ['max-age' => 0],
      '#attached' => [
        'html_head' => [
          [
            [
              '#type' => 'html_tag',
              '#tag' => 'link',
              '#attributes' => [
                'rel' => 'stylesheet',
                'href' => '/modules/custom/odesign_admin/css/dashboard.css',
                'media' => 'all',
              ],
            ],
            'odesign_admin_dashboard_css',
          ],
        ],
      ],
    ];

    return $build;
  }

  /**
   * Statistiques clés pour les compteurs du dashboard.
   */
  protected function getStats(): array {
    $stats = [];

    // Commandes en attente de traitement.
    try {
      $pending = $this->database->select('commerce_order', 'o')
        ->condition('o.state', ['pending', 'processing'], 'IN')
        ->countQuery()->execute()->fetchField();
      $stats['pending_orders'] = (int) $pending;
    }
    catch (\Exception $e) {
      $stats['pending_orders'] = 0;
    }

    // Total commandes.
    try {
      $total_orders = $this->database->select('commerce_order', 'o')
        ->condition('o.state', 'draft', '<>')
        ->countQuery()->execute()->fetchField();
      $stats['total_orders'] = (int) $total_orders;
    }
    catch (\Exception $e) {
      $stats['total_orders'] = 0;
    }

    // Produits publiés.
    try {
      $products = $this->database->select('commerce_product_field_data', 'p')
        ->condition('p.status', 1)
        ->countQuery()->execute()->fetchField();
      $stats['total_products'] = (int) $products;
    }
    catch (\Exception $e) {
      $stats['total_products'] = 0;
    }

    return $stats;
  }

  /**
   * Raccourcis vers les actions fréquentes.
   */
  protected function getQuickLinks(): array {
    $links = [
      [
        'title'       => 'Créer un produit',
        'route_name'  => 'entity.commerce_product.add_form',
        'route_params' => ['commerce_product_type' => 'default'],
        'description' => 'Ajouter un nouveau produit à votre boutique',
        'icon'        => 'add_circle',
      ],
      [
        'title'       => 'Toutes les commandes',
        'route_name'  => 'entity.commerce_order.collection',
        'description' => 'Gérer et suivre vos commandes',
        'icon'        => 'receipt_long',
      ],
      [
        'title'       => 'Expéditions',
        'route_name'  => 'entity.commerce_shipping_method.collection',
        'description' => 'Gérer les modes de livraison',
        'icon'        => 'local_shipping',
      ],
      [
        'title'       => 'Factures',
        'route_name'  => 'view.commerce_invoices.page_1',
        'description' => 'Consulter et télécharger les factures',
        'icon'        => 'description',
      ],
      [
        'title'       => 'Mes clients',
        'route_name'  => 'entity.user.collection',
        'description' => 'Consulter les comptes clients',
        'icon'        => 'group',
      ],
      [
        'title'       => 'Mes produits',
        'route_name'  => 'entity.commerce_product.collection',
        'description' => 'Modifier votre catalogue',
        'icon'        => 'inventory_2',
      ],
      [
        'title'       => 'Promotions',
        'route_name'  => 'entity.commerce_promotion.collection',
        'description' => 'Créer et gérer vos codes promo',
        'icon'        => 'local_offer',
      ],
      [
        'title'       => 'Ajouter une image',
        'route_name'  => 'entity.media.add_form',
        'route_params' => ['media_type' => 'product_image'],
        'description' => 'Télécharger une photo produit',
        'icon'        => 'add_photo_alternate',
      ],
      [
        'title'       => 'Configuration boutique',
        'route_name'  => 'odesign_admin.config',
        'description' => 'Mailgun, Stripe, informations boutique',
        'icon'        => 'settings',
      ],
    ];

    $account = $this->currentUser();
    $access_manager = \Drupal::service('access_manager');
    $quick_links = [];

    foreach ($links as $link) {
      $route_name = $link['route_name'];
      $route_params = $link['route_params'] ?? [];
      $access = $access_manager->checkNamedRoute($route_name, $route_params, $account, TRUE);
      if (!$access->isAllowed()) {
        continue;
      }

      $link['url'] = Url::fromRoute($route_name, $route_params)->toString();
      unset($link['route_name'], $link['route_params']);
      $quick_links[] = $link;
    }

    return $quick_links;
  }

}
