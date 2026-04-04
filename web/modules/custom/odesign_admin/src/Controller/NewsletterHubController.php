<?php

namespace Drupal\odesign_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Hub newsletter pour la gestionnaire boutique.
 *
 * Route : /admin/boutique/newsletter
 * Permissions requises : administer simplenews subscriptions
 */
class NewsletterHubController extends ControllerBase {

  public function page(): array {
    $build = [];
    $total_confirmed = $this->countSubscribers(1);
    $total_pending   = $this->countSubscribers(0);

    // ── En-tête stats + actions ─────────────────────────────────────────────
    $build['header'] = [
      '#type'       => 'container',
      '#attributes' => ['class' => ['odesign-hub__newsletter-header']],
    ];
    $build['header']['stats'] = [
      '#type'       => 'container',
      '#attributes' => ['class' => ['odesign-hub__newsletter-stats']],
      'confirmed'   => $this->statCard((string) $total_confirmed, $this->t('Abonnés confirmés'), 'ph-user-check', TRUE),
      'pending'     => $this->statCard((string) $total_pending, $this->t('En attente de confirmation'), 'ph-hourglass-medium', FALSE),
    ];
    $build['header']['actions'] = [
      '#type'       => 'container',
      '#attributes' => ['class' => ['odesign-hub__actions', 'odesign-hub__newsletter-actions']],
      'write'  => [
        '#type'       => 'link',
        '#title'      => $this->t('Rédiger une newsletter'),
        '#url'        => Url::fromRoute('node.add', ['node_type' => 'simplenews_issue']),
        '#attributes' => ['class' => ['button', 'button--primary', 'odesign-hub__action-btn']],
      ],
      'add'    => [
        '#type'       => 'link',
        '#title'      => $this->t('Ajouter un abonné'),
        '#url'        => Url::fromRoute('entity.simplenews_subscriber.add_form'),
        '#attributes' => ['class' => ['button', 'odesign-hub__action-btn']],
      ],
      'list'   => [
        '#type'       => 'link',
        '#title'      => $this->t('Voir la liste complète'),
        '#url'        => Url::fromRoute('entity.simplenews_subscriber.collection'),
        '#attributes' => ['class' => ['button', 'odesign-hub__action-btn']],
      ],
      'export' => [
        '#type'       => 'link',
        '#title'      => $this->t('Exporter en CSV'),
        '#url'        => Url::fromRoute('simplenews.subscriber_export'),
        '#attributes' => ['class' => ['button', 'odesign-hub__action-btn']],
      ],
    ];

    // ── Liste des 15 derniers abonnés confirmés ─────────────────────────────
    $build['subscribers_section'] = [
      '#type'       => 'container',
      '#attributes' => ['class' => ['odesign-hub__section']],
    ];
    $build['subscribers_section']['header'] = [
      '#markup' => '<h2 class="odesign-hub__section-title"><i class="ph ph-users" aria-hidden="true"></i> '
        . $this->t('Derniers abonnés confirmés') . '</h2>',
    ];

    $subscribers = $this->loadRecentSubscribers(15);
    if (empty($subscribers)) {
      $build['subscribers_section']['empty'] = [
        '#type'       => 'container',
        '#attributes' => ['class' => ['odesign-hub__empty-state']],
        '#markup'     => '<i class="ph ph-envelope-open" aria-hidden="true"></i>'
          . '<p>' . $this->t("Aucun abonné confirmé pour l'instant.") . '</p>'
          . '<p class="odesign-hub__empty-hint">'
          . $this->t('Le bandeau newsletter est visible sur toutes les pages du site. Les inscriptions confirmées apparaîtront ici.')
          . '</p>',
      ];
    }
    else {
      $rows = [];
      foreach ($subscribers as $subscriber) {
        $created = $subscriber->get('created')->value
          ? \Drupal::service('date.formatter')->format((int) $subscriber->get('created')->value, 'short')
          : '—';
        $rows[] = [
          '#type'       => 'container',
          '#attributes' => ['class' => ['odesign-hub__subscriber-row']],
          'email'   => ['#markup' => '<span class="odesign-hub__subscriber-email"><i class="ph ph-envelope" aria-hidden="true"></i> ' . htmlspecialchars($subscriber->getMail()) . '</span>'],
          'date'    => ['#markup' => '<span class="odesign-hub__subscriber-date">' . $created . '</span>'],
          'actions' => [
            '#type'       => 'container',
            '#attributes' => ['class' => ['odesign-hub__subscriber-actions']],
            'edit'   => ['#type' => 'link', '#title' => $this->t('Modifier'), '#url' => Url::fromRoute('entity.simplenews_subscriber.edit_form', ['simplenews_subscriber' => $subscriber->id()]), '#attributes' => ['class' => ['button', 'button--small']]],
            'delete' => ['#type' => 'link', '#title' => $this->t('Supprimer'), '#url' => Url::fromRoute('entity.simplenews_subscriber.delete_form', ['simplenews_subscriber' => $subscriber->id()]), '#attributes' => ['class' => ['button', 'button--small', 'button--danger']]],
          ],
        ];
      }
      $build['subscribers_section']['list'] = [
        '#type'       => 'container',
        '#attributes' => ['class' => ['odesign-hub__subscriber-list']],
        'rows'        => $rows,
      ];
      if ($total_confirmed > 15) {
        $build['subscribers_section']['footer'] = [
          '#type'       => 'container',
          '#attributes' => ['class' => ['odesign-hub__section-footer']],
          'more' => ['#type' => 'link', '#title' => $this->t('Voir les @count abonnés', ['@count' => $total_confirmed]), '#url' => Url::fromRoute('entity.simplenews_subscriber.collection'), '#attributes' => ['class' => ['button', 'button--small']]],
        ];
      }
    }

    // ── Encart RGPD ─────────────────────────────────────────────────────────
    $build['rgpd'] = [
      '#type'       => 'container',
      '#attributes' => ['class' => ['odesign-hub__info-box', 'odesign-hub__info-box--rgpd']],
      '#markup'     => '<i class="ph ph-shield-check" aria-hidden="true"></i>'
        . '<div><strong>' . $this->t('Conformité RGPD') . '</strong>'
        . '<p>' . $this->t("Chaque inscription est soumise à un double opt-in : un email de confirmation est envoyé avant d'activer l'abonnement. Les abonnés peuvent se désabonner à tout moment.") . '</p></div>',
    ];

    $build['#cache']    = ['max-age' => 0];
    $build['#attached'] = ['library' => ['odesign_admin/hub']];
    return $build;
  }

  private function countSubscribers(int $status): int {
    try {
      return (int) $this->entityTypeManager()->getStorage('simplenews_subscriber')->getQuery()
        ->condition('status', $status)->accessCheck(FALSE)->count()->execute();
    }
    catch (\Exception) { return 0; }
  }

  private function loadRecentSubscribers(int $limit): array {
    try {
      $ids = $this->entityTypeManager()->getStorage('simplenews_subscriber')->getQuery()
        ->condition('status', 1)->sort('created', 'DESC')->range(0, $limit)->accessCheck(FALSE)->execute();
      return empty($ids) ? [] : $this->entityTypeManager()->getStorage('simplenews_subscriber')->loadMultiple($ids);
    }
    catch (\Exception) { return []; }
  }

  private function statCard(string $value, $label, string $icon, bool $highlight): array {
    $classes = ['odesign-hub__newsletter-stat'];
    if ($highlight) { $classes[] = 'odesign-hub__newsletter-stat--highlight'; }
    return [
      '#type'       => 'container',
      '#attributes' => ['class' => $classes],
      '#markup'     => '<i class="ph ' . $icon . '" aria-hidden="true"></i>'
        . '<span class="odesign-hub__stat-value">' . htmlspecialchars($value) . '</span>'
        . '<span class="odesign-hub__stat-label">' . $label . '</span>',
    ];
  }

}
