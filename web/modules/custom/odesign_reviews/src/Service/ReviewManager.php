<?php

namespace Drupal\odesign_reviews\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Url;

/**
 * Handles review request and review persistence lifecycle.
 */
class ReviewManager {

  public function __construct(
    private readonly Connection $database,
    private readonly MailManagerInterface $mailManager,
    private readonly LanguageManagerInterface $languageManager,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {}

  /**
   * Creates and sends a review request email for an order if absent.
   */
  public function createAndSendRequest(int $order_id, string $order_number, string $email): bool {
    if ($email === '' || $order_id <= 0) {
      return FALSE;
    }

    // One request per order.
    $existing = $this->database->select('odesign_review_requests', 'r')
      ->fields('r', ['id', 'token'])
      ->condition('order_id', $order_id)
      ->execute()
      ->fetchAssoc();

    if ($existing) {
      return FALSE;
    }

    $token = bin2hex(random_bytes(16));
    $this->database->insert('odesign_review_requests')
      ->fields([
        'order_id' => $order_id,
        'order_number' => $order_number,
        'email' => mb_strtolower(trim($email)),
        'token' => $token,
        'created' => \Drupal::time()->getRequestTime(),
        'used' => 0,
      ])
      ->execute();

    $review_url = Url::fromRoute('odesign_reviews.form', [
      'order_id' => $order_id,
      'token' => $token,
    ], ['absolute' => TRUE])->toString();

    $params = [
      'order_number' => $order_number,
      'review_url' => $review_url,
    ];

    $result = $this->mailManager->mail(
      'odesign_reviews',
      'review_request',
      $email,
      $this->languageManager->getDefaultLanguage()->getId(),
      $params
    );

    if (empty($result['result'])) {
      $this->loggerFactory->get('odesign_reviews')
        ->error('Échec envoi demande avis pour commande @order.', ['@order' => $order_number]);
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Returns request row if token is valid and not already used.
   */
  public function getValidRequest(int $order_id, string $token): ?array {
    $row = $this->database->select('odesign_review_requests', 'r')
      ->fields('r')
      ->condition('order_id', $order_id)
      ->condition('token', $token)
      ->condition('used', 0)
      ->execute()
      ->fetchAssoc();

    if (!$row) {
      return NULL;
    }

    $already_reviewed = (bool) $this->database->select('odesign_reviews', 'v')
      ->fields('v', ['id'])
      ->condition('order_id', $order_id)
      ->execute()
      ->fetchField();

    if ($already_reviewed) {
      return NULL;
    }

    return $row;
  }

  /**
   * Saves a pending review and marks request as used.
   */
  public function saveReview(array $request, array $review_values): void {
    $timestamp = \Drupal::time()->getRequestTime();
    $this->database->insert('odesign_reviews')
      ->fields([
        'order_id' => (int) $request['order_id'],
        'order_number' => (string) $request['order_number'],
        'email' => (string) $request['email'],
        'author_name' => (string) $review_values['author_name'],
        'occasion' => (string) $review_values['occasion'],
        'rating' => (int) $review_values['rating'],
        'comment' => (string) $review_values['comment'],
        'status' => 'pending',
        'created' => $timestamp,
        'updated' => $timestamp,
      ])
      ->execute();

    $this->database->update('odesign_review_requests')
      ->fields(['used' => 1])
      ->condition('id', (int) $request['id'])
      ->execute();
    $this->cacheTagsInvalidator->invalidateTags(['odesign_reviews:list']);
  }

  /**
   * Returns admin list rows sorted by most recent first.
   */
  public function getAdminRows(int $limit = 100): array {
    return $this->database->select('odesign_reviews', 'r')
      ->fields('r')
      ->orderBy('created', 'DESC')
      ->range(0, $limit)
      ->execute()
      ->fetchAllAssoc('id');
  }

  /**
   * Updates moderation status for one review row.
   */
  public function setStatus(int $review_id, string $status): void {
    if (!in_array($status, ['pending', 'approved', 'rejected'], TRUE)) {
      return;
    }
    $this->database->update('odesign_reviews')
      ->fields([
        'status' => $status,
        'updated' => \Drupal::time()->getRequestTime(),
      ])
      ->condition('id', $review_id)
      ->execute();
    $this->cacheTagsInvalidator->invalidateTags(['odesign_reviews:list']);
  }

  /**
   * Returns most recent approved reviews for frontend.
   */
  public function getApproved(int $limit = 3): array {
    $rows = $this->database->select('odesign_reviews', 'r')
      ->fields('r')
      ->condition('status', 'approved')
      ->orderBy('created', 'DESC')
      ->range(0, $limit)
      ->execute()
      ->fetchAllAssoc('id');

    foreach ($rows as &$row) {
      $row->created_label = $this->dateFormatter->format((int) $row->created, 'custom', 'd/m/Y');
    }
    return $rows;
  }

}
