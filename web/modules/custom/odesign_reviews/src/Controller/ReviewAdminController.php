<?php

namespace Drupal\odesign_reviews\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\odesign_reviews\Service\ReviewManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Back-office moderation list for collected customer reviews.
 */
class ReviewAdminController extends ControllerBase {

  public function __construct(
    private readonly ReviewManager $reviewManager,
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('odesign_reviews.manager'),
      $container->get('date.formatter')
    );
  }

  /**
   * Admin listing page.
   */
  public function list(): array {
    $rows = [];
    foreach ($this->reviewManager->getAdminRows() as $row) {
      $actions = [];
      $token = \Drupal::csrfToken()->get('review_moderate_' . $row->id);

      if ($row->status !== 'approved') {
        $actions[] = Link::fromTextAndUrl(
          $this->t('Approuver'),
          Url::fromRoute('odesign_reviews.admin_approve', ['review_id' => $row->id], [
            'query' => ['token' => $token],
            'attributes' => ['class' => ['button', 'button--small']],
          ])
        )->toRenderable();
      }
      if ($row->status !== 'rejected') {
        $actions[] = Link::fromTextAndUrl(
          $this->t('Rejeter'),
          Url::fromRoute('odesign_reviews.admin_reject', ['review_id' => $row->id], [
            'query' => ['token' => $token],
            'attributes' => ['class' => ['button', 'button--small', 'button--danger']],
          ])
        )->toRenderable();
      }

      $rows[] = [
        'data' => [
          ['data' => '#' . htmlspecialchars((string) $row->order_number)],
          ['data' => htmlspecialchars((string) $row->author_name)],
          ['data' => str_repeat('★', (int) $row->rating)],
          ['data' => htmlspecialchars((string) $row->occasion)],
          ['data' => nl2br(htmlspecialchars(mb_strimwidth((string) $row->comment, 0, 160, '…')))],
          ['data' => htmlspecialchars((string) $row->status)],
          ['data' => $this->dateFormatter->format((int) $row->created, 'short')],
          ['data' => $actions],
        ],
      ];
    }

    return [
      '#type' => 'table',
      '#header' => [
        $this->t('Commande'),
        $this->t('Client'),
        $this->t('Note'),
        $this->t('Occasion'),
        $this->t('Avis'),
        $this->t('Statut'),
        $this->t('Date'),
        $this->t('Actions'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('Aucun avis pour le moment.'),
      '#attributes' => ['class' => ['odesign-reviews-admin']],
      '#attached' => [
        'library' => ['odesign_reviews/admin'],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Approves one review.
   */
  public function approve(int $review_id, Request $request): RedirectResponse {
    $this->moderate($review_id, 'approved', $request);
    return new RedirectResponse(Url::fromRoute('odesign_reviews.admin_list')->toString());
  }

  /**
   * Rejects one review.
   */
  public function reject(int $review_id, Request $request): RedirectResponse {
    $this->moderate($review_id, 'rejected', $request);
    return new RedirectResponse(Url::fromRoute('odesign_reviews.admin_list')->toString());
  }

  /**
   * Internal moderation helper.
   */
  private function moderate(int $review_id, string $status, Request $request): void {
    $token = (string) $request->query->get('token', '');
    if (!\Drupal::csrfToken()->validate($token, 'review_moderate_' . $review_id)) {
      $this->messenger()->addError($this->t('Jeton de sécurité invalide.'));
      return;
    }
    $this->reviewManager->setStatus($review_id, $status);
    $this->messenger()->addStatus($status === 'approved'
      ? $this->t('Avis approuvé.')
      : $this->t('Avis rejeté.'));
  }

}
