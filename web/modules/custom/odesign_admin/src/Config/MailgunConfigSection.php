<?php

namespace Drupal\odesign_admin\Config;

use Drupal\Core\Form\FormStateInterface;

/**
 * Section "Emails transactionnels" (Mailgun SMTP).
 */
class MailgunConfigSection implements ConfigSectionInterface {

  public function getId(): string {
    return 'emails';
  }

  public function getTitle(): string {
    return ($this->isConfigured() ? '✓ ' : '⚠ ') . 'Emails (Mailgun)';
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
        . '<strong>À quoi ça sert ?</strong> Mailgun envoie vos emails automatiques : '
        . 'confirmation de commande, notification d\'expédition, réinitialisation de mot de passe. '
        . '<a href="https://app.mailgun.com/mg/sending/domains" target="_blank">Ouvrir Mailgun →</a>'
        . '</div>',
    ];

    $tab['smtp_username'] = [
      '#type'          => 'textfield',
      '#title'         => 'Identifiant SMTP Mailgun',
      '#description'   => 'Exemple : <code>postmaster@mg.atelierodesign.fr</code> — trouvé dans Mailgun sous <em>Sending → Domains → SMTP credentials</em>',
      '#default_value' => $smtp->get('smtp_username') ?? '',
      '#placeholder'   => 'postmaster@mg.atelierodesign.fr',
    ];

    $tab['smtp_password'] = [
      '#type'          => 'password',
      '#title'         => 'Mot de passe SMTP Mailgun',
      '#description'   => 'Laissez vide pour conserver le mot de passe actuel.',
      '#default_value' => '',
      '#placeholder'   => $ok ? '(mot de passe enregistré — laisser vide pour ne pas changer)' : '',
    ];

    $tab['smtp_from'] = [
      '#type'          => 'email',
      '#title'         => 'Adresse d\'expédition',
      '#default_value' => $smtp->get('smtp_from') ?? 'boutique@atelierodesign.fr',
      '#description'   => 'Adresse affichée dans le champ "De :" de tous les emails.',
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
    // Pas de validation particulière pour Mailgun.
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

  private function statusBadge(bool $ok): string {
    return $ok
      ? '<span class="config-badge config-badge--ok">✓ Configuré</span>'
      : '<span class="config-badge config-badge--warning">⚠ À configurer</span>';
  }

}
