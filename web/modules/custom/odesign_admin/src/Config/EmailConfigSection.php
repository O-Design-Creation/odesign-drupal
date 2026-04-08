<?php

namespace Drupal\odesign_admin\Config;

use Drupal\Core\Form\FormStateInterface;

/**
 * Section "Emails transactionnels" (Brevo SMTP).
 */
class EmailConfigSection implements ConfigSectionInterface {

  public function getId(): string {
    return 'emails';
  }

  public function getTitle(): string {
    return ($this->isConfigured() ? '✓ ' : '⚠ ') . 'Emails (Brevo)';
  }

  public function getWeight(): int {
    return 10;
  }

  public function buildForm(array &$tab, FormStateInterface $form_state): void {
    $smtp   = \Drupal::configFactory()->get('smtp.settings');
    $ok     = $this->isConfigured();

    $tab['#attributes']['class'][] = $ok ? 'config-ok' : 'config-warning';

    $tab['smtp_help'] = [
      '#markup' => '<div class="config-help">'
        . '<strong>À quoi ça sert ?</strong> Brevo envoie vos emails automatiques : '
        . 'confirmation de commande, notification d\'expédition, réinitialisation de mot de passe. '
        . '<a href="https://app.brevo.com/settings/keys/smtp" target="_blank">Ouvrir Brevo → SMTP & API →</a>'
        . '</div>',
    ];

    $tab['smtp_username'] = [
      '#type'          => 'textfield',
      '#title'         => 'Identifiant SMTP Brevo',
      '#description'   => 'Votre adresse email de compte Brevo — visible dans <em>Brevo → SMTP & API → SMTP</em>',
      '#default_value' => $smtp->get('smtp_username') ?? '',
      '#placeholder'   => 'vous@exemple.com',
    ];

    $tab['smtp_password'] = [
      '#type'          => 'password',
      '#title'         => 'Clé SMTP Brevo',
      '#description'   => 'Clé SMTP générée dans <em>Brevo → SMTP & API → SMTP Keys</em>. Laissez vide pour conserver la clé actuelle.',
      '#default_value' => '',
      '#placeholder'   => $ok ? '(clé enregistrée — laisser vide pour ne pas changer)' : '',
    ];

    $tab['smtp_from'] = [
      '#type'          => 'email',
      '#title'         => 'Adresse d\'expédition',
      '#default_value' => $smtp->get('smtp_from') ?? 'boutique@atelierodesign.fr',
      '#description'   => 'Adresse affichée dans le champ "De :" de tous les emails. Doit être vérifiée dans Brevo.',
    ];

    // Bloc test email (sous-onglet).
    $tab['smtp_test'] = [
      '#type'       => 'details',
      '#title'      => 'Tester l\'envoi d\'email',
      '#open'       => FALSE,
      '#attributes' => ['class' => ['config-test-block']],
    ];
    $tab['smtp_test']['test_email'] = [
      '#type'        => 'email',
      '#title'       => 'Adresse de test',
      '#placeholder' => 'vous@atelierodesign.fr',
    ];
    $tab['smtp_test']['send_test'] = [
      '#type'       => 'submit',
      '#value'      => 'Envoyer un email de test',
      '#submit'     => ['::sendTestEmail'],
      '#attributes' => ['class' => ['button', 'button--secondary', 'button--small']],
    ];
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Pas de validation particulière pour Brevo.
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $smtp = \Drupal::configFactory()->getEditable('smtp.settings');
    $smtp->set('smtp_username', $form_state->getValue('smtp_username'));
    if ($form_state->getValue('smtp_password')) {
      $smtp->set('smtp_password', $form_state->getValue('smtp_password'));
    }
    if ($from = $form_state->getValue('smtp_from')) {
      $smtp->set('smtp_from', $from);
    }
    $smtp->set('smtp_on', TRUE)->save();
  }

  private function isConfigured(): bool {
    $smtp = \Drupal::configFactory()->get('smtp.settings');
    return !empty($smtp->get('smtp_username')) && !empty($smtp->get('smtp_password'));
  }

}
