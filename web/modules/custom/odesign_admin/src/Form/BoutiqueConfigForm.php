<?php

namespace Drupal\odesign_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Hub de configuration de la boutique.
 *
 * Collecte les sections de configuration via hook_odesign_boutique_config_sections_alter().
 * Chaque section implémente ConfigSectionInterface et est responsable de ses propres
 * champs, validation et sauvegarde.
 *
 * Pour ajouter une section depuis un module tiers :
 * @see odesign_admin.api.php
 * @see \Drupal\odesign_admin\Config\ConfigSectionInterface
 */
class BoutiqueConfigForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'odesign_boutique_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // CSS du formulaire.
    $form['#attached']['html_head'][] = [
      [
        '#type'       => 'html_tag',
        '#tag'        => 'link',
        '#attributes' => [
          'rel'   => 'stylesheet',
          'href'  => '/modules/custom/odesign_admin/css/boutique-config.css',
          'media' => 'all',
        ],
      ],
      'odesign_boutique_config_css',
    ];

    // Collecte des sections via hook.
    $sections = [];
    \Drupal::moduleHandler()->alter('odesign_boutique_config_sections', $sections);
    uasort($sections, static fn($a, $b) => $a->getWeight() <=> $b->getWeight());

    // Onglets vertical_tabs.
    $form['config_tabs'] = [
      '#type'        => 'vertical_tabs',
      '#default_tab' => 'edit-emails',
    ];

    // Construire chaque section dans son onglet.
    foreach ($sections as $section) {
      $id = $section->getId();
      $form[$id] = [
        '#type'  => 'details',
        '#title' => $section->getTitle(),
        '#group' => 'config_tabs',
        '#tree'  => FALSE,
      ];
      $section->buildForm($form[$id], $form_state);
    }

    $form['actions'] = [
      '#type'       => 'actions',
      '#attributes' => ['class' => ['config-actions']],
    ];
    $form['actions']['submit'] = [
      '#type'        => 'submit',
      '#value'       => 'Enregistrer la configuration',
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $sections = [];
    \Drupal::moduleHandler()->alter('odesign_boutique_config_sections', $sections);
    foreach ($sections as $section) {
      $section->validateForm($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $sections = [];
    \Drupal::moduleHandler()->alter('odesign_boutique_config_sections', $sections);
    foreach ($sections as $section) {
      $section->submitForm($form, $form_state);
    }

    $this->messenger()->addStatus('Configuration enregistrée avec succès.');
    \Drupal::service('config.factory')->reset();
  }

  /**
   * Callback pour l'envoi d'un email de test (utilisé par EmailConfigSection).
   *
   * Drupal résout '::sendTestEmail' sur la classe du formulaire, donc cette méthode
   * doit rester ici même si la logique appartient à la section Emails.
   */
  public function sendTestEmail(array &$form, FormStateInterface $form_state): void {
    $email = $form_state->getValue('test_email');
    if (!$email) {
      $this->messenger()->addError('Veuillez entrer une adresse email de test.');
      return;
    }

    $result = \Drupal::service('plugin.manager.mail')->mail(
      'odesign_admin',
      'test_email',
      $email,
      \Drupal::languageManager()->getDefaultLanguage()->getId(),
      [
        'subject' => 'Test email — Atelier O Design',
        'body'    => 'Si vous recevez cet email, votre configuration Brevo fonctionne correctement.',
      ],
    );

    if ($result['result'] ?? FALSE) {
      $this->messenger()->addStatus("Email de test envoyé à $email. Vérifiez votre boîte.");
    }
    else {
      $this->messenger()->addError("Échec de l'envoi. Vérifiez vos identifiants Brevo ci-dessus.");
    }
  }

}
