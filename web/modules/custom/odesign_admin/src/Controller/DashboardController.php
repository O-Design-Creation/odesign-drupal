<?php

namespace Drupal\odesign_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
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
    return [
      [
        'title'       => 'Créer un produit',
        'url'         => '/product/add/default',
        'description' => 'Ajouter un nouveau produit à votre boutique',
        'icon'        => 'add_circle',
      ],
      [
        'title'       => 'Toutes les commandes',
        'url'         => '/admin/commerce/orders',
        'description' => 'Gérer et suivre vos commandes',
        'icon'        => 'receipt_long',
      ],
      [
        'title'       => 'Expéditions',
        'url'         => '/admin/commerce/shipping-methods',
        'description' => 'Gérer les modes de livraison',
        'icon'        => 'local_shipping',
      ],
      [
        'title'       => 'Factures',
        'url'         => '/admin/commerce/invoices',
        'description' => 'Consulter et télécharger les factures',
        'icon'        => 'description',
      ],
      [
        'title'       => 'Mes clients',
        'url'         => '/admin/people',
        'description' => 'Consulter les comptes clients',
        'icon'        => 'group',
      ],
      [
        'title'       => 'Mes produits',
        'url'         => '/admin/commerce/products',
        'description' => 'Modifier votre catalogue',
        'icon'        => 'inventory_2',
      ],
      [
        'title'       => 'Promotions',
        'url'         => '/admin/commerce/promotions',
        'description' => 'Créer et gérer vos codes promo',
        'icon'        => 'local_offer',
      ],
      [
        'title'       => 'Ajouter une image',
        'url'         => '/media/add/product_image',
        'description' => 'Télécharger une photo produit',
        'icon'        => 'add_photo_alternate',
      ],
      [
        'title'       => 'Configuration boutique',
        'url'         => '/admin/boutique/configuration',
        'description' => 'Mailgun, Stripe, informations boutique',
        'icon'        => 'settings',
      ],
    ];
  }

}
