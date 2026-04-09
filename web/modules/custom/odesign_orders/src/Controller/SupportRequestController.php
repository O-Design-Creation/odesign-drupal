<?php

namespace Drupal\odesign_orders\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Back-office list and moderation of customer support requests.
 */
class SupportRequestController extends ControllerBase {

  public function __construct(
    private readonly DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('date.formatter'));
  }

  /**
   * Builds the management list.
   */
  public function list(): array {
    $rows = [];
    $result = \Drupal::database()->select('odesign_order_support_request', 'r')
      ->fields('r')
      ->orderBy('created', 'DESC')
      ->range(0, 200)
      ->execute();

    foreach ($result as $row) {
      $token = \Drupal::csrfToken()->get('sav_request_' . $row->id);
      $actions = [];
      if ($row->status !== 'in_progress') {
        $actions[] = Link::fromTextAndUrl(
          $this->t('En cours'),
          Url::fromRoute('odesign_orders.admin_sav_status', ['request_id' => $row->id, 'status' => 'in_progress'], [
            'query' => ['token' => $token],
            'attributes' => ['class' => ['button', 'button--small']],
          ])
        )->toString();
      }
      if ($row->status !== 'resolved') {
        $actions[] = Link::fromTextAndUrl(
          $this->t('Traité'),
          Url::fromRoute('odesign_orders.admin_sav_status', ['request_id' => $row->id, 'status' => 'resolved'], [
            'query' => ['token' => $token],
            'attributes' => ['class' => ['button', 'button--small']],
          ])
        )->toString();
      }
      if ($row->status !== 'new') {
        $actions[] = Link::fromTextAndUrl(
          $this->t('Remettre en nouveau'),
          Url::fromRoute('odesign_orders.admin_sav_status', ['request_id' => $row->id, 'status' => 'new'], [
            'query' => ['token' => $token],
            'attributes' => ['class' => ['button', 'button--small']],
          ])
        )->toString();
      }
      $actions_markup = implode(' ', $actions);

      $rows[] = [
        'data' => [
          ['data' => ['#plain_text' => '#' . (int) $row->order_id]],
          ['data' => ['#plain_text' => (string) $row->email]],
          ['data' => ['#plain_text' => $this->renderCategory((string) $row->category)]],
          ['data' => ['#markup' => nl2br(htmlspecialchars(mb_strimwidth((string) $row->message, 0, 180, '…')))]],
          ['data' => ['#plain_text' => $this->renderStatus((string) $row->status)]],
          ['data' => ['#plain_text' => $this->dateFormatter->format((int) $row->created, 'short')]],
          ['data' => ['#markup' => $actions_markup]],
        ],
      ];
    }

    return [
      '#type' => 'table',
      '#header' => [
        $this->t('Commande'),
        $this->t('Email client'),
        $this->t('Sujet'),
        $this->t('Message'),
        $this->t('Statut'),
        $this->t('Date'),
        $this->t('Actions'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('Aucune demande SAV.'),
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Updates a support request status.
   */
  public function setStatus(Request $request, int $request_id, string $status): RedirectResponse {
    $token = (string) $request->query->get('token', '');
    if (!\Drupal::csrfToken()->validate($token, 'sav_request_' . $request_id)) {
      $this->messenger()->addError($this->t('Jeton invalide.'));
      return new RedirectResponse(Url::fromRoute('odesign_orders.admin_sav_list')->toString());
    }

    \Drupal::database()->update('odesign_order_support_request')
      ->fields([
        'status' => $status,
        'updated' => \Drupal::time()->getRequestTime(),
      ])
      ->condition('id', $request_id)
      ->execute();

    $this->messenger()->addStatus($this->t('Statut SAV mis à jour.'));
    return new RedirectResponse(Url::fromRoute('odesign_orders.admin_sav_list')->toString());
  }

  /**
   * Maps category id to a readable label.
   */
  private function renderCategory(string $category): string {
    return match ($category) {
      'delivery' => 'Retard / livraison',
      'address' => 'Correction adresse',
      'personalization' => 'Personnalisation',
      'other' => 'Autre',
      default => 'Question',
    };
  }

  /**
   * Maps status id to a readable label.
   */
  private function renderStatus(string $status): string {
    return match ($status) {
      'in_progress' => 'En cours',
      'resolved' => 'Traité',
      default => 'Nouveau',
    };
  }

}
