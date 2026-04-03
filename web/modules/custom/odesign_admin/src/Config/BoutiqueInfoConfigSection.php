<?php

namespace Drupal\odesign_admin\Config;

use Drupal\Core\Form\FormStateInterface;

/**
 * Section "Informations de la boutique" (nom, email, adresse expéditeur).
 */
class BoutiqueInfoConfigSection implements ConfigSectionInterface {

  public function getId(): string {
    return 'boutique';
  }

  public function getTitle(): string {
    return ($this->isConfigured() ? '✓ ' : '⚠ ') . 'Ma boutique';
  }

  public function getWeight(): int {
    return 30;
  }

  public function buildForm(array &$tab, FormStateInterface $form_state): void {
    $smtp  = \Drupal::configFactory()->get('smtp.settings');
    $store = \Drupal::entityTypeManager()->getStorage('commerce_store')->load(1);
    $ok    = $this->isConfigured();

    $tab['#attributes']['class'][] = $ok ? 'config-ok' : 'config-warning';

    $tab['boutique_help'] = [
      '#markup' => '<div class="config-help">'
        . 'Nom et email affichés sur les emails transactionnels et les factures.'
        . '</div>',
    ];

    $tab['store_name'] = [
      '#type'          => 'textfield',
      '#title'         => 'Nom de la boutique',
      '#default_value' => $store ? $store->getName() : '',
      '#description'   => 'Affiché sur les emails et factures.',
      '#required'      => FALSE,
    ];

    $tab['store_email'] = [
      '#type'          => 'email',
      '#title'         => 'Email de contact',
      '#default_value' => $store ? $store->getEmail() : '',
      '#description'   => 'Email de contact affiché sur les confirmations de commande.',
    ];

    $tab['smtp_from'] = [
      '#type'          => 'email',
      '#title'         => 'Adresse d\'expédition des emails',
      '#default_value' => $smtp->get('smtp_from') ?? 'boutique@atelierodesign.fr',
      '#description'   => 'Adresse affichée dans le champ "De :" de tous les emails automatiques.',
    ];
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Pas de validation bloquante.
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $store = \Drupal::entityTypeManager()->getStorage('commerce_store')->load(1);
    if ($store) {
      if ($name = $form_state->getValue('store_name')) {
        $store->setName($name);
      }
      if ($email = $form_state->getValue('store_email')) {
        $store->setEmail($email);
      }
      $store->save();
    }

    // smtp_from est également sauvegardé par MailgunConfigSection::submitForm().
    // On le sauvegarde ici aussi pour que la section Boutique puisse fonctionner indépendamment.
    if ($from = $form_state->getValue('smtp_from')) {
      $smtp = \Drupal::configFactory()->getEditable('smtp.settings');
      $smtp->set('smtp_from', $from)->save();
    }
  }

  private function isConfigured(): bool {
    $store = \Drupal::entityTypeManager()->getStorage('commerce_store')->load(1);
    return $store && $store->getName() && $store->getEmail();
  }

  private function statusBadge(bool $ok): string {
    return $ok
      ? '<span class="config-badge config-badge--ok">✓ Configuré</span>'
      : '<span class="config-badge config-badge--warning">⚠ À compléter</span>';
  }

}
