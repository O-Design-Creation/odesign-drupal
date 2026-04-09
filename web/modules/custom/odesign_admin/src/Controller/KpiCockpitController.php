<?php

namespace Drupal\odesign_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cockpit KPI B2C pour pilotage hebdomadaire/mensuel.
 */
class KpiCockpitController extends ControllerBase {

  public function __construct(
    protected Connection $database,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'));
  }

  /**
   * Page cockpit KPI.
   */
  public function page(): array {
    $now = \Drupal::time()->getRequestTime();
    $start_7d = $now - (7 * 86400);
    $start_30d = $now - (30 * 86400);
    $start_prev_7d = $now - (14 * 86400);

    $metrics_7d = $this->collectPeriodMetrics($start_7d, $now);
    $metrics_30d = $this->collectPeriodMetrics($start_30d, $now);
    $metrics_prev_7d = $this->collectPeriodMetrics($start_prev_7d, $start_7d);

    return [
      '#theme' => 'odesign_admin_kpi_cockpit',
      '#kpi_7d' => $this->buildDisplayKpis($metrics_7d),
      '#kpi_30d' => $this->buildDisplayKpis($metrics_30d),
      '#alerts' => $this->buildAlerts($metrics_7d, $metrics_prev_7d),
      '#top_products' => $this->getTopProducts($start_30d, $now),
      '#top_categories' => $this->getTopCategories($start_30d, $now),
      '#top_search_misses' => $this->getTopSearchMisses($start_30d),
      '#export_url' => Url::fromRoute('odesign_admin.analytics_export')->toString(),
      '#cache' => ['max-age' => 0],
      '#attached' => [
        'html_head' => [[
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
        ]],
      ],
    ];
  }

  /**
   * Exporte un CSV mensuel minimal (mois en cours).
   */
  public function exportMonthlyCsv(): Response {
    $now = \Drupal::time()->getRequestTime();
    $month_start = strtotime(date('Y-m-01 00:00:00', $now));
    $next_month_start = strtotime(date('Y-m-01 00:00:00', strtotime('+1 month', $month_start)));
    $end = min($now, $next_month_start);

    $stream = fopen('php://temp', 'r+');
    fputcsv($stream, [
      'date',
      'orders_completed',
      'revenue_eur',
      'aov_eur',
      'orders_with_promo',
      'search_no_result_queries',
    ], ';');

    for ($day_start = $month_start; $day_start < $end; $day_start += 86400) {
      $day_end = min($day_start + 86400, $end);

      $orders = (int) $this->database->query(
        'SELECT COUNT(*) FROM {commerce_order}
         WHERE state = :state AND placed >= :start AND placed < :end',
        [
          ':state' => 'completed',
          ':start' => $day_start,
          ':end' => $day_end,
        ]
      )->fetchField();

      $revenue = (float) $this->database->query(
        'SELECT COALESCE(SUM(total_price__number), 0) FROM {commerce_order}
         WHERE state = :state AND placed >= :start AND placed < :end',
        [
          ':state' => 'completed',
          ':start' => $day_start,
          ':end' => $day_end,
        ]
      )->fetchField();

      $promo_orders = (int) $this->database->query(
        "SELECT COUNT(DISTINCT oa.entity_id)
         FROM {commerce_order__adjustments} oa
         INNER JOIN {commerce_order} co ON co.order_id = oa.entity_id
         WHERE co.state = :state
           AND co.placed >= :start
           AND co.placed < :end
           AND CAST(oa.adjustments_value AS CHAR) LIKE :promotion",
        [
          ':state' => 'completed',
          ':start' => $day_start,
          ':end' => $day_end,
          ':promotion' => '%promotion%',
        ]
      )->fetchField();

      $search_misses = 0;
      if ($this->database->schema()->tableExists('odesign_search_no_result_log')) {
        $search_misses = (int) $this->database->query(
          'SELECT COUNT(*) FROM {odesign_search_no_result_log}
           WHERE last_seen >= :start AND last_seen < :end',
          [
            ':start' => $day_start,
            ':end' => $day_end,
          ]
        )->fetchField();
      }

      $aov = $orders > 0 ? ($revenue / $orders) : 0;
      fputcsv($stream, [
        date('Y-m-d', $day_start),
        $orders,
        number_format($revenue, 2, '.', ''),
        number_format($aov, 2, '.', ''),
        $promo_orders,
        $search_misses,
      ], ';');
    }

    rewind($stream);
    $csv = (string) stream_get_contents($stream);
    fclose($stream);

    $filename = 'cockpit-kpi-' . date('Y-m', $now) . '.csv';
    return new Response(
      $csv,
      200,
      [
        'Content-Type' => 'text/csv; charset=UTF-8',
        'Content-Disposition' => 'attachment; filename="' . $filename . '"',
      ]
    );
  }

  /**
   * Construit les KPI affichables pour le template.
   */
  private function buildDisplayKpis(array $metrics): array {
    $promo_rate = $metrics['orders_completed'] > 0
      ? ($metrics['orders_with_promo'] / $metrics['orders_completed']) * 100
      : 0;

    return [
      ['label' => 'Commandes finalisées', 'value' => (string) $metrics['orders_completed']],
      ['label' => 'CA', 'value' => $this->formatCurrency($metrics['revenue'])],
      ['label' => 'Panier moyen', 'value' => $this->formatCurrency($metrics['average_order_value'])],
      ['label' => 'Articles vendus', 'value' => (string) $metrics['items_sold']],
      ['label' => 'Commandes avec promo', 'value' => (string) $metrics['orders_with_promo']],
      ['label' => 'Taux promo', 'value' => number_format($promo_rate, 1, ',', ' ') . ' %'],
      ['label' => 'Relances panier envoyées', 'value' => (string) $metrics['cart_reminders_sent']],
      ['label' => 'Demandes SAV créées', 'value' => (string) $metrics['sav_requests']],
      ['label' => 'Recherches sans résultat', 'value' => (string) $metrics['search_no_result_queries']],
      ['label' => 'Abonnés newsletter (stock)', 'value' => (string) $metrics['newsletter_confirmed']],
    ];
  }

  /**
   * Collecte les métriques business sur une période.
   */
  private function collectPeriodMetrics(int $start, int $end): array {
    $metrics = [
      'orders_completed' => 0,
      'revenue' => 0.0,
      'average_order_value' => 0.0,
      'items_sold' => 0,
      'orders_with_promo' => 0,
      'cart_reminders_sent' => 0,
      'sav_requests' => 0,
      'search_no_result_queries' => 0,
      'newsletter_confirmed' => 0,
      'draft_carts_older_2h' => 0,
    ];

    $row = $this->database->query(
      'SELECT COUNT(*) AS c, COALESCE(SUM(total_price__number), 0) AS amount
       FROM {commerce_order}
       WHERE state = :state AND placed >= :start AND placed < :end',
      [
        ':state' => 'completed',
        ':start' => $start,
        ':end' => $end,
      ]
    )->fetchAssoc() ?: [];
    $metrics['orders_completed'] = (int) ($row['c'] ?? 0);
    $metrics['revenue'] = (float) ($row['amount'] ?? 0);
    $metrics['average_order_value'] = $metrics['orders_completed'] > 0
      ? $metrics['revenue'] / $metrics['orders_completed']
      : 0.0;

    $metrics['items_sold'] = (int) $this->database->query(
      'SELECT COALESCE(SUM(oi.quantity), 0)
       FROM {commerce_order_item} oi
       INNER JOIN {commerce_order} co ON co.order_id = oi.order_id
       WHERE co.state = :state AND co.placed >= :start AND co.placed < :end',
      [
        ':state' => 'completed',
        ':start' => $start,
        ':end' => $end,
      ]
    )->fetchField();

    $metrics['orders_with_promo'] = (int) $this->database->query(
      "SELECT COUNT(DISTINCT oa.entity_id)
       FROM {commerce_order__adjustments} oa
       INNER JOIN {commerce_order} co ON co.order_id = oa.entity_id
       WHERE co.state = :state
         AND co.placed >= :start
         AND co.placed < :end
         AND CAST(oa.adjustments_value AS CHAR) LIKE :promotion",
      [
        ':state' => 'completed',
        ':start' => $start,
        ':end' => $end,
        ':promotion' => '%promotion%',
      ]
    )->fetchField();

    if ($this->database->schema()->tableExists('odesign_cart_recovery_log')) {
      $metrics['cart_reminders_sent'] = (int) $this->database->query(
        'SELECT COUNT(*) FROM {odesign_cart_recovery_log}
         WHERE sent >= :start AND sent < :end',
        [
          ':start' => $start,
          ':end' => $end,
        ]
      )->fetchField();
    }

    if ($this->database->schema()->tableExists('odesign_order_support_request')) {
      $metrics['sav_requests'] = (int) $this->database->query(
        'SELECT COUNT(*) FROM {odesign_order_support_request}
         WHERE created >= :start AND created < :end',
        [
          ':start' => $start,
          ':end' => $end,
        ]
      )->fetchField();
    }

    if ($this->database->schema()->tableExists('odesign_search_no_result_log')) {
      $metrics['search_no_result_queries'] = (int) $this->database->query(
        'SELECT COUNT(*) FROM {odesign_search_no_result_log}
         WHERE last_seen >= :start AND last_seen < :end',
        [
          ':start' => $start,
          ':end' => $end,
        ]
      )->fetchField();
    }

    if ($this->database->schema()->tableExists('simplenews_subscriber')) {
      $metrics['newsletter_confirmed'] = (int) $this->database->query(
        'SELECT COUNT(*) FROM {simplenews_subscriber} WHERE status = 1'
      )->fetchField();
    }

    $metrics['draft_carts_older_2h'] = (int) $this->database->query(
      'SELECT COUNT(*) FROM {commerce_order}
       WHERE state = :state
         AND cart = 1
         AND changed >= :start
         AND changed < :end
         AND changed <= :stale_before',
      [
        ':state' => 'draft',
        ':start' => $start,
        ':end' => $end,
        ':stale_before' => \Drupal::time()->getRequestTime() - 7200,
      ]
    )->fetchField();

    return $metrics;
  }

  /**
   * Génère des alertes opérationnelles simples.
   */
  private function buildAlerts(array $kpi_7d, array $kpi_prev_7d): array {
    $alerts = [];

    $prev_orders = (int) ($kpi_prev_7d['orders_completed'] ?? 0);
    $current_orders = (int) ($kpi_7d['orders_completed'] ?? 0);
    if ($prev_orders >= 5 && $current_orders <= (int) floor($prev_orders * 0.8)) {
      $alerts[] = [
        'type' => 'warning',
        'message' => $this->t('Baisse des commandes sur 7 jours (@current vs @previous la semaine précédente).', [
          '@current' => $current_orders,
          '@previous' => $prev_orders,
        ]),
      ];
    }

    if (($kpi_7d['draft_carts_older_2h'] ?? 0) >= 5) {
      $alerts[] = [
        'type' => 'warning',
        'message' => $this->t('Hausse des paniers inactifs: @count paniers "draft" dépassent 2h.', [
          '@count' => (int) $kpi_7d['draft_carts_older_2h'],
        ]),
      ];
    }

    if (($kpi_7d['search_no_result_queries'] ?? 0) >= 8) {
      $alerts[] = [
        'type' => 'info',
        'message' => $this->t('Plusieurs recherches sans résultat cette semaine: pensez à enrichir synonymes/catégories.'),
      ];
    }

    return $alerts;
  }

  /**
   * Top produits vendus sur période.
   */
  private function getTopProducts(int $start, int $end): array {
    $rows = $this->database->query(
      'SELECT oi.title AS title, SUM(oi.quantity) AS qty
       FROM {commerce_order_item} oi
       INNER JOIN {commerce_order} co ON co.order_id = oi.order_id
       WHERE co.state = :state
         AND co.placed >= :start
         AND co.placed < :end
       GROUP BY oi.title
       ORDER BY qty DESC
       LIMIT 5',
      [
        ':state' => 'completed',
        ':start' => $start,
        ':end' => $end,
      ]
    )->fetchAll();

    $items = [];
    foreach ($rows as $row) {
      $items[] = [
        'label' => (string) $row->title,
        'value' => (int) $row->qty,
      ];
    }
    return $items;
  }

  /**
   * Top catégories vendues sur période.
   */
  private function getTopCategories(int $start, int $end): array {
    $rows = $this->database->query(
      "SELECT tfd.name AS name, SUM(oi.quantity) AS qty
       FROM {commerce_order_item} oi
       INNER JOIN {commerce_order} co ON co.order_id = oi.order_id
       INNER JOIN {commerce_product__variations} pv ON pv.variations_target_id = oi.purchased_entity
       INNER JOIN {commerce_product__field_category} pc ON pc.entity_id = pv.entity_id AND pc.deleted = 0
       INNER JOIN {taxonomy_term_field_data} tfd ON tfd.tid = pc.field_category_target_id
       WHERE co.state = :state
         AND co.placed >= :start
         AND co.placed < :end
         AND tfd.langcode = :langcode
       GROUP BY tfd.tid, tfd.name
       ORDER BY qty DESC
       LIMIT 5",
      [
        ':state' => 'completed',
        ':start' => $start,
        ':end' => $end,
        ':langcode' => 'fr',
      ]
    )->fetchAll();

    $items = [];
    foreach ($rows as $row) {
      $items[] = [
        'label' => (string) $row->name,
        'value' => (int) $row->qty,
      ];
    }
    return $items;
  }

  /**
   * Top recherches sans résultat observées sur période.
   */
  private function getTopSearchMisses(int $start): array {
    if (!$this->database->schema()->tableExists('odesign_search_no_result_log')) {
      return [];
    }

    $rows = $this->database->query(
      'SELECT query_raw, hit_count, last_seen
       FROM {odesign_search_no_result_log}
       WHERE last_seen >= :start
       ORDER BY hit_count DESC, last_seen DESC
       LIMIT 5',
      [':start' => $start]
    )->fetchAll();

    $items = [];
    foreach ($rows as $row) {
      $items[] = [
        'query' => (string) $row->query_raw,
        'hits' => (int) $row->hit_count,
        'last_seen' => date('d/m/Y', (int) $row->last_seen),
      ];
    }
    return $items;
  }

  /**
   * Format monétaire simple.
   */
  private function formatCurrency(float $value): string {
    return number_format($value, 2, ',', ' ') . ' €';
  }

}
