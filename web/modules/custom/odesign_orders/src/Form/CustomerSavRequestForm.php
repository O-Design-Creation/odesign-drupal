<?php

namespace Drupal\odesign_orders\Form;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Customer form to submit a support request linked to an order.
 */
class CustomerSavRequestForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'odesign_orders_customer_sav_request_form';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, int $user = 0, ?OrderInterface $commerce_order = NULL): array {
    $account = $this->currentUser();
    if (!$commerce_order || (int) $account->id() !== $user || (int) $commerce_order->getCustomerId() !== $user) {
      $form['forbidden'] = [
        '#type' => 'markup',
        '#markup' => '<p>Cette demande SAV n\'est pas accessible.</p>',
      ];
      return $form;
    }

    $form_state->set('sav_order_id', (int) $commerce_order->id());

    $form['intro'] = [
      '#type' => 'markup',
      '#markup' => '<p>Commande <strong>#' . htmlspecialchars((string) ($commerce_order->getOrderNumber() ?: $commerce_order->id())) . '</strong> — précisez votre demande.</p>',
    ];

    $form['category'] = [
      '#type' => 'select',
      '#title' => $this->t('Sujet'),
      '#required' => TRUE,
      '#options' => [
        'question' => $this->t('Question sur la commande'),
        'delivery' => $this->t('Retard / livraison'),
        'address' => $this->t('Correction adresse'),
        'personalization' => $this->t('Personnalisation'),
        'other' => $this->t('Autre'),
      ],
      '#default_value' => 'question',
    ];

    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Votre message'),
      '#required' => TRUE,
      '#rows' => 5,
      '#maxlength' => 2000,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Envoyer ma demande'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $message = trim((string) $form_state->getValue('message'));
    if (mb_strlen($message) < 10) {
      $form_state->setErrorByName('message', $this->t('Merci de détailler votre demande (10 caractères minimum).'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $order_id = (int) ($form_state->get('sav_order_id') ?? 0);
    if ($order_id <= 0) {
      $this->messenger()->addError($this->t('Commande introuvable.'));
      return;
    }

    $now = \Drupal::time()->getRequestTime();
    $user = \Drupal\user\Entity\User::load((int) $this->currentUser()->id());
    $mail = $user ? (string) $user->getEmail() : '';
    if ($mail === '') {
      $this->messenger()->addError($this->t('Impossible d\'envoyer la demande (email introuvable).'));
      return;
    }
    \Drupal::database()->insert('odesign_order_support_request')
      ->fields([
        'order_id' => $order_id,
        'uid' => (int) $this->currentUser()->id(),
        'email' => $mail,
        'category' => (string) $form_state->getValue('category'),
        'message' => trim((string) $form_state->getValue('message')),
        'status' => 'new',
        'created' => $now,
        'updated' => $now,
      ])
      ->execute();

    $this->notifyManagers($order_id, $mail);

    $this->messenger()->addStatus($this->t('Votre demande a bien été envoyée.'));
    $form_state->setRedirectUrl(Url::fromRoute('entity.commerce_order.user_view', [
      'user' => (int) $this->currentUser()->id(),
      'commerce_order' => $order_id,
    ]));
  }

  /**
   * Notifies managers that a new support request was created.
   */
  private function notifyManagers(int $order_id, string $customer_email): void {
    $manager_emails = [];
    $ids = \Drupal::entityQuery('user')
      ->condition('roles', 'gestionnaire_boutique')
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->execute();

    foreach (\Drupal::entityTypeManager()->getStorage('user')->loadMultiple($ids) as $user) {
      $manager_emails[] = $user->getEmail();
    }
    $manager_emails = array_unique(array_filter($manager_emails));

    foreach ($manager_emails as $email) {
      \Drupal::service('plugin.manager.mail')->mail(
        'odesign_orders',
        'support_request_notification',
        $email,
        \Drupal::languageManager()->getDefaultLanguage()->getId(),
        [
          'order_id' => $order_id,
          'customer_email' => $customer_email,
          'admin_url' => Url::fromRoute('odesign_orders.admin_sav_list', [], ['absolute' => TRUE])->toString(),
        ]
      );
    }
  }

}
