<?php

namespace Drupal\odesign_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Liste des clients ayant passé commande.
 */
class ClientsController extends ControllerBase {

  public function __construct(
    protected Connection $database,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
    );
  }

  /**
   * Page clients — liste des acheteurs avec résumé commandes.
   */
  public function page(): array {
    $clients = $this->loadClients();

    return [
      '#theme'   => 'odesign_admin_clients',
      '#clients' => $clients,
      '#cache'   => ['max-age' => 0],
    ];
  }

  /**
   * Charge la liste des clients avec leurs statistiques commandes.
   */
  private function loadClients(): array {
    try {
      $rows = $this->database->query(
        'SELECT
           co.uid,
           co.mail,
           COUNT(co.order_id)              AS total_orders,
           SUM(co.total_price__number)     AS total_spent,
           co.total_price__currency_code   AS currency,
           MAX(co.placed)                  AS last_order_date
         FROM {commerce_order} co
         WHERE co.cart = 0
           AND co.state != :draft
         GROUP BY co.uid, co.mail, co.total_price__currency_code
         ORDER BY last_order_date DESC',
        [':draft' => 'draft']
      )->fetchAll();
    }
    catch (\Exception) {
      return [];
    }

    $clients = [];
    foreach ($rows as $row) {
      $orders_url = Url::fromRoute('entity.commerce_order.collection', [], [
        'query' => ['mail' => $row->mail],
      ])->toString();

      // Lien vers le profil utilisateur si compte enregistré.
      $profile_url = NULL;
      if (!empty($row->uid)) {
        $profile_url = Url::fromRoute('entity.user.edit_form', ['user' => $row->uid])->toString();
      }

      $clients[] = [
        'mail'            => $row->mail,
        'uid'             => $row->uid,
        'total_orders'    => (int) $row->total_orders,
        'total_spent'     => number_format((float) $row->total_spent, 2, ',', ' ') . ' ' . ($row->currency ?? 'EUR'),
        'last_order_date' => $row->last_order_date
          ? \Drupal::service('date.formatter')->format((int) $row->last_order_date, 'medium')
          : '—',
        'orders_url'      => $orders_url,
        'profile_url'     => $profile_url,
      ];
    }

    return $clients;
  }

}
