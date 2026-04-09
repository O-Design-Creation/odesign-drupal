<?php

namespace Drupal\odesign_reviews\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\odesign_reviews\Service\ReviewManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Public review form, one submission per delivered order.
 */
class ReviewSubmitForm extends FormBase {

  public function __construct(
    private readonly ReviewManager $reviewManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('odesign_reviews.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'odesign_reviews_submit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, int $order_id = 0, string $token = ''): array {
    $request = $this->reviewManager->getValidRequest($order_id, $token);
    if (!$request) {
      $form['invalid'] = [
        '#type' => 'markup',
        '#markup' => '<p>Ce lien d\'avis n\'est plus valide.</p>',
      ];
      return $form;
    }

    $form_state->set('review_request', $request);

    $form['intro'] = [
      '#type' => 'markup',
      '#markup' => '<p>Commande <strong>#'
        . htmlspecialchars((string) $request['order_number'])
        . '</strong> — Merci pour votre retour.</p>',
    ];

    $form['author_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Prénom (ou initiales)'),
      '#required' => TRUE,
      '#maxlength' => 128,
    ];

    $form['occasion'] = [
      '#type' => 'select',
      '#title' => $this->t('Occasion'),
      '#options' => [
        '' => $this->t('- Sélectionner -'),
        'mariage' => $this->t('Mariage'),
        'naissance' => $this->t('Naissance / bébé'),
        'cadeau' => $this->t('Cadeau personnalisé'),
        'autre' => $this->t('Autre'),
      ],
      '#required' => TRUE,
    ];

    $form['rating'] = [
      '#type' => 'select',
      '#title' => $this->t('Note'),
      '#options' => [
        5 => '5 / 5',
        4 => '4 / 5',
        3 => '3 / 5',
        2 => '2 / 5',
        1 => '1 / 5',
      ],
      '#required' => TRUE,
      '#default_value' => 5,
    ];

    $form['comment'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Votre avis'),
      '#required' => TRUE,
      '#rows' => 5,
      '#maxlength' => 1200,
      '#description' => $this->t('Parlez du rendu, de la qualité, du délai ou de la personnalisation.'),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Envoyer mon avis'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (mb_strlen(trim((string) $form_state->getValue('comment'))) < 10) {
      $form_state->setErrorByName('comment', $this->t('Veuillez saisir au moins 10 caractères.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $request = $form_state->get('review_request');
    if (!is_array($request)) {
      $this->messenger()->addError($this->t('Lien invalide.'));
      return;
    }

    $this->reviewManager->saveReview($request, [
      'author_name' => trim((string) $form_state->getValue('author_name')),
      'occasion' => (string) $form_state->getValue('occasion'),
      'rating' => (int) $form_state->getValue('rating'),
      'comment' => trim((string) $form_state->getValue('comment')),
    ]);

    $this->messenger()->addStatus($this->t('Merci ! Votre avis a bien été envoyé.'));
    $form_state->setRedirect('<front>');
  }

}

