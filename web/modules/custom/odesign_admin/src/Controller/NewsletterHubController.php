<?php

namespace Drupal\odesign_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

/**
 * Hub newsletter pour la gestionnaire boutique.
 *
 * Route : /admin/boutique/newsletter
 *
 * Affiche les statistiques d'abonnement, les derniers inscrits,
 * et les actions principales (rédiger, exporter, paramétrer).
 */
class NewsletterHubController extends ControllerBase {

  /**
   * Page hub newsletter.
   */
  public function page(): array {
    $build = [];

    // ── Statistiques ────────────────────────────────────────────────────────
    $total_confirmed = $this->countSubscribers(1);
    $total_pending   = $this->countSubscribers(0);

    $build['stats'] = [
      '#type'       => 'container',
      '#attributes' => ['class' => ['odesign-hub__stats-row']],
      'confirmed'   => $this->statCard(
        (string) $total_confirmed,
        $this->t('Abonnés confirmés'),
        'ph-user-check',
        TRUE
      ),
      'pending'     => $this->statCard(
        (string) $total_pending,
        $this->t('En attente de confirmation'),
        'ph-hourglass',
        FALSE
      ),
    ];

    // ── Actions principales ─────────────────────────────────────────────────
    $build['actions'] = [
      '#type'       => 'container',
      '#attributes' => ['class' => ['odesign-hub__section', 'odesign-hub__section--actions']],
    ];

    $build['actions']['header'] = [
      '#markup' => '<h2 class="odesign-hub__section-title"><i class="ph ph-lightning" aria-hidden="true"></i> ' . $this->t('Actions') . '</h2>',
    ];

    $build['actions']['buttons'] = [
      '#type'       => 'container',
      '#attributes' => ['class' => ['odesign-hub__actions']],
      'subscribers' => [
        '#type'       => 'link',
        '#title'      => $this->t('Voir tous les abonnés'),
        '#url'        => Url::fromRoute('view.simplenews_subscribers.page_1'),
        '#attributes' => ['class' => ['button', 'button--primary', 'odesign-hub__action-btn']],
      ],
      'export' => [
        '#type'       => 'link',
        '#title'      => $this->t('Exporter la liste'),
        '#url'        => Url::fromRoute('simplenews.subscriber_export'),
        '#attributes' => ['class' => ['button', 'odesign-hub__action-btn']],
      ],
      'settings' => [
        '#type'       => 'link',
        '#title'      => $this->t('Paramètres newsletter'),
        '#url'        => Url::fromRoute('simplenews.newsletter_overview'),
        '#attributes' => ['class' => ['button', 'odesign-hub__action-btn']],
      ],
    ];

    // ── Derniers inscrits ───────────────────────────────────────────────────
    $subscribers = $this->loadRecentSubscribers(20);

    $build['subscribers_section'] = [
      '#type'       => 'container',
      '#attributes' => ['class' => ['odesign-hub__section']],
    ];

    $build['subscribers_section']['header'] = [
      '#markup' => '<h2 class="odesign-hub__section-title"><i class="ph ph-users" aria-hidden="true"></i> ' . $this->t('Derniers abonnés confirmés') . '</h2>',
    ];

    if (empty($subscribers)) {
      $build['subscribers_section']['empty'] = [
        '#markup' => '<p class="odesign-hub__empty">' . $this->t("Aucun abonné confirmé pour l'instant. Partagez votre site pour commencer à construire votre liste.") . '</p>',
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
          'email'       => ['#markup' => '<span class="odesign-hub__subscriber-email">' . htmlspecialchars($subscriber->getMail()) . '</span>'],
          'date'        => ['#markup' => '<span class="odesign-hub__subscriber-date">' . $created . '</span>'],
          'delete'      => [
            '#type'       => 'link',
            '#title'      => $this->t('Supprimer'),
            '#url'        => Url::fromRoute('entity.simplenews_subscriber.delete_form', ['simplenews_subscriber' => $subscriber->id()]),
            '#attributes' => ['class' => ['button', 'button--small', 'button--danger']],
          ],
        ];
      }

      $build['subscribers_section']['list'] = [
        '#type'       => 'container',
        '#attributes' => ['class' => ['odesign-hub__subscriber-list']],
        'rows'        => $rows,
      ];

      if ($total_confirmed > 20) {
        $build['subscribers_section']['more'] = [
          '#type'       => 'link',
          '#title'      => $this->t('Voir les @count abonnés', ['@count' => $total_confirmed]),
          '#url'        => Url::fromRoute('view.simplenews_subscribers.page_1'),
          '#attributes' => ['class' => ['button', 'button--small']],
        ];
      }
    }

    // ── Info RGPD ───────────────────────────────────────────────────────────
    $build['rgpd'] = [
      '#type'       => 'container',
      '#attributes' => ['class' => ['odesign-hub__info-box']],
      '#markup'     => '<p><i class="ph ph-shield-check" aria-hidden="true"></i> <strong>' . $this->t('Conformité RGPD') . '</strong> — '
        . $this->t('Chaque inscription est soumise à un double opt-in. Les abonnés ont consenti explicitement et peuvent se désabonner à tout moment via le lien présent dans chaque email.')
        . '</p>',
    ];

    $build['#cache']    = ['max-age' => 0];
    $build['#attached'] = ['library' => ['odesign_admin/hub']];

    return $build;
  }

  /**
   * Compte les abonnés par statut (1 = confirmé, 0 = en attente).
   */
  private function countSubscribers(int $status): int {
    try {
      return (int) $this->entityTypeManager()
        ->getStorage('simplenews_subscriber')
        ->getQuery()
        ->condition('status', $status)
        ->accessCheck(FALSE)
        ->count()
        ->execute();
    }
    catch (\Exception) {
      return 0;
    }
  }

  /**
   * Charge les N derniers abonnés confirmés.
   */
  private function loadRecentSubscribers(int $limit): array {
    try {
      $ids = $this->entityTypeManager()
        ->getStorage('simplenews_subscriber')
        ->getQuery()
        ->condition('status', 1)
        ->sort('created', 'DESC')
        ->range(0, $limit)
        ->accessCheck(FALSE)
        ->execute();

      if (empty($ids)) {
        return [];
      }

      return $this->entityTypeManager()
        ->getStorage('simplenews_subscriber')
        ->loadMultiple($ids);
    }
    catch (\Exception) {
      return [];
    }
  }

  /**
   * Construit une carte de statistique.
   */
  private function statCard(string $value, $label, string $icon, bool $highlight): array {
    $classes = ['odesign-hub__stat-card'];
    if ($highlight) {
      $classes[] = 'odesign-hub__stat-card--highlight';
    }
    return [
      '#type'       => 'container',
      '#attributes' => ['class' => $classes],
      '#markup'     => '<i class="ph ' . $icon . '" aria-hidden="true"></i>'
        . '<span class="odesign-hub__stat-value">' . htmlspecialchars($value) . '</span>'
        . '<span class="odesign-hub__stat-label">' . $label . '</span>',
    ];
  }

}
