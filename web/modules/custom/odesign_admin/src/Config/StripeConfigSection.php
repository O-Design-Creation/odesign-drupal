<?php

namespace Drupal\odesign_admin\Config;

use Drupal\Core\Form\FormStateInterface;

/**
 * Section "Paiements en ligne" (Stripe).
 */
class StripeConfigSection implements ConfigSectionInterface {

  public function getId(): string {
    return 'stripe';
  }

  public function getTitle(): string {
    return ($this->isConfigured() ? '✓ ' : '⚠ ') . 'Paiements (Stripe)';
  }

  public function getWeight(): int {
    return 20;
  }

  public function buildForm(array &$tab, FormStateInterface $form_state): void {
    $stripe = \Drupal::configFactory()->get('commerce_payment.commerce_payment_gateway.stripe_sandbox');
    $ok     = $this->isConfigured();

    $tab['#attributes']['class'][] = $ok ? 'config-ok' : 'config-warning';

    $tab['stripe_help'] = [
      '#markup' => '<div class="config-help">'
        . '<strong>À quoi ça sert ?</strong> Stripe permet à vos clients de payer par carte bancaire. '
        . '<a href="https://dashboard.stripe.com/test/apikeys" target="_blank">Ouvrir Stripe (clés API) →</a>'
        . '</div>',
    ];

    $tab['stripe_mode_info'] = [
      '#markup' => '<div class="config-mode-badge config-mode-' . ($ok ? 'test' : 'inactive') . '">'
        . ($ok
          ? '🧪 Mode test actif — utilisez la carte <strong>4242 4242 4242 4242</strong> pour tester'
          : '⚠️ Non configuré')
        . '</div>',
    ];

    $current_pk = $stripe->get('configuration.publishable_key') ?? '';
    $tab['publishable_key'] = [
      '#type'          => 'textfield',
      '#title'         => 'Clé publiable (Publishable key)',
      '#description'   => 'Commence par <code>pk_test_</code> (mode test) ou <code>pk_live_</code> (mode réel).',
      '#default_value' => str_contains($current_pk, 'PLACEHOLDER') ? '' : $current_pk,
      '#placeholder'   => 'pk_test_...',
      '#maxlength'     => 255,
    ];

    $tab['secret_key'] = [
      '#type'        => 'password',
      '#title'       => 'Clé secrète (Secret key)',
      '#description' => 'Commence par <code>sk_test_</code>. Laissez vide pour ne pas modifier.',
      '#placeholder' => $ok ? '(clé enregistrée — laisser vide pour ne pas changer)' : 'sk_test_...',
    ];

    $tab['webhook_secret'] = [
      '#type'        => 'password',
      '#title'       => 'Webhook secret',
      '#description' => 'Commence par <code>whsec_</code>. Laissez vide pour ne pas modifier.',
      '#placeholder' => !empty($stripe->get('configuration.webhook_secret'))
        ? '(secret enregistré — laisser vide pour ne pas changer)'
        : 'whsec_...',
    ];

    $webhook_url = \Drupal::request()->getSchemeAndHttpHost() . '/payment/notify/stripe_sandbox';
    $tab['webhook_url_info'] = [
      '#markup' => '<div class="config-webhook-url">'
        . '<strong>URL de votre webhook Stripe :</strong><br>'
        . '<code>' . htmlspecialchars($webhook_url) . '</code>'
        . '<br><small>Copiez cette URL dans Stripe → Développeurs → Webhooks → Ajouter un endpoint</small>'
        . '</div>',
    ];
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Pas de validation bloquante : les clés peuvent être partiellement remplies.
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $stripe = \Drupal::configFactory()->getEditable('commerce_payment.commerce_payment_gateway.stripe_sandbox');
    $config = $stripe->get('configuration') ?? [];

    if ($pk = $form_state->getValue('publishable_key')) {
      $config['publishable_key'] = trim($pk);
    }
    if ($sk = $form_state->getValue('secret_key')) {
      $config['secret_key'] = trim($sk);
    }
    if ($wh = $form_state->getValue('webhook_secret')) {
      $config['webhook_secret'] = trim($wh);
    }
    if (!empty($config['publishable_key']) && !empty($config['secret_key'])) {
      $config['authentication_method'] = 'api_keys';
      $config['access_token'] = '';
      $config['stripe_user_id'] = '';
    }
    $stripe->set('configuration', $config)->save();
  }

  private function isConfigured(): bool {
    $stripe = \Drupal::configFactory()->get('commerce_payment.commerce_payment_gateway.stripe_sandbox');
    $pk = $stripe->get('configuration.publishable_key') ?? '';
    $sk = $stripe->get('configuration.secret_key') ?? '';
    return !empty($pk) && !str_contains($pk, 'PLACEHOLDER')
      && !empty($sk) && !str_contains($sk, 'PLACEHOLDER');
  }

  private function statusBadge(bool $ok): string {
    return $ok
      ? '<span class="config-badge config-badge--ok">✓ Configuré</span>'
      : '<span class="config-badge config-badge--warning">⚠ À configurer</span>';
  }

}
