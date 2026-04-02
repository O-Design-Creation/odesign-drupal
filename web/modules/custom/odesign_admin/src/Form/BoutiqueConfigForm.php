<?php

namespace Drupal\odesign_admin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Page de configuration centralisée — services externes de la boutique.
 *
 * Regroupe en un seul endroit :
 * - Mailgun (SMTP) : credentials email transactionnel
 * - Stripe : clés API et webhook pour les paiements
 *
 * Les valeurs sont stockées directement dans la config Drupal (base de données).
 * Elles ne sont jamais exportées dans les fichiers de config git (config_ignore).
 */
class BoutiqueConfigForm extends FormBase {

  public static function create(ContainerInterface $container): static {
    return new static();
  }

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
    $smtp   = \Drupal::configFactory()->get('smtp.settings');
    $stripe = \Drupal::configFactory()->get('commerce_payment.commerce_payment_gateway.stripe_sandbox');
    $settings = \Drupal::configFactory()->get('odesign_admin.settings');
    $store = \Drupal::entityTypeManager()->getStorage('commerce_store')->load(1);

    // ── Statuts détectés ─────────────────────────────────────────────────────
    $smtp_ok   = !empty($smtp->get('smtp_username')) && !empty($smtp->get('smtp_password'));
    $stripe_ok = !empty($stripe->get('configuration.publishable_key'))
              && !str_contains($stripe->get('configuration.publishable_key') ?? '', 'PLACEHOLDER')
              && !empty($stripe->get('configuration.secret_key'))
              && !str_contains($stripe->get('configuration.secret_key') ?? '', 'PLACEHOLDER');
    $store_ok = $store && $store->getName() && $store->getEmail();

    $form['#attributes']['class'][] = 'odesign-config-form';

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

    // ── Intro ─────────────────────────────────────────────────────────────────
    $form['intro'] = [
      '#markup' => '<div class="config-intro">'
        . '<div class="config-intro__eyebrow">Configuration de la boutique</div>'
        . '<h2 class="config-intro__title">Centralisez ici vos réglages essentiels</h2>'
        . '<p class="config-intro__lead">Emails transactionnels, paiements et informations de contact : tout ce qui doit rester juste pour que la boutique fonctionne sans surprise.</p>'
        . '<div class="config-status-grid">'
        . $this->statusCard('Emails', $smtp_ok, $smtp_ok ? 'Mailgun prêt pour les confirmations et notifications.' : 'Identifiants SMTP à compléter.')
        . $this->statusCard('Paiement', $stripe_ok, $stripe_ok ? 'Stripe test prêt pour les essais client.' : 'Clés Stripe à renseigner.')
        . $this->statusCard('Boutique', (bool) $store_ok, $store_ok ? 'Nom et email boutique déjà renseignés.' : 'Nom ou email boutique à compléter.')
        . '</div>'
        . '<p class="config-intro__hint">Conseil : commencez par les sections marquées <strong>À configurer</strong>, puis enregistrez en bas de page.</p>'
        . '</div>',
    ];

    // ────────────────────────────────────────────────────────────────────────
    // SECTION 1 — SMTP / Mailgun
    // ────────────────────────────────────────────────────────────────────────
    $form['smtp'] = [
      '#type'        => 'details',
      '#title'       => $this->statusBadge($smtp_ok) . ' Emails transactionnels (Mailgun)',
      '#open'        => !$smtp_ok,
      '#attributes'  => ['class' => ['config-section', 'config-section--smtp', $smtp_ok ? 'config-ok' : 'config-warning']],
    ];

    $form['smtp']['smtp_help'] = [
      '#markup' => '<div class="config-help">'
        . '<strong>À quoi ça sert ?</strong> Mailgun envoie vos emails automatiques : confirmation de commande, notification d\'expédition, réinitialisation de mot de passe.'
        . ' <a href="https://app.mailgun.com/mg/sending/domains" target="_blank">Ouvrir Mailgun →</a>'
        . '</div>',
    ];

    $form['smtp']['smtp_username'] = [
      '#type'          => 'textfield',
      '#title'         => 'Identifiant SMTP Mailgun',
      '#description'   => 'Exemple : <code>postmaster@mg.atelierodesign.fr</code> — trouvé dans Mailgun sous <em>Sending → Domains → SMTP credentials</em>',
      '#default_value' => $smtp->get('smtp_username') ?? '',
      '#placeholder'   => 'postmaster@mg.atelierodesign.fr',
    ];

    $form['smtp']['smtp_password'] = [
      '#type'          => 'password',
      '#title'         => 'Mot de passe SMTP Mailgun',
      '#description'   => 'Laissez vide pour conserver le mot de passe actuel. Trouvé dans Mailgun sous <em>SMTP credentials → Reset password</em>.',
      '#default_value' => '',
      '#placeholder'   => $smtp_ok ? '(mot de passe enregistré — laisser vide pour ne pas changer)' : '',
    ];

    $form['smtp']['smtp_test'] = [
      '#type'        => 'details',
      '#title'       => 'Tester l\'envoi d\'email',
      '#open'        => FALSE,
      '#attributes'  => ['class' => ['config-test-block']],
    ];
    $form['smtp']['smtp_test']['test_email'] = [
      '#type'          => 'email',
      '#title'         => 'Adresse de test',
      '#description'   => 'Entrez votre adresse email pour recevoir un email de test.',
      '#placeholder'   => 'vous@atelierodesign.fr',
    ];
    $form['smtp']['smtp_test']['send_test'] = [
      '#type'   => 'submit',
      '#value'  => 'Envoyer un email de test',
      '#submit' => ['::sendTestEmail'],
      '#attributes' => ['class' => ['button', 'button--secondary', 'button--small']],
    ];

    // ────────────────────────────────────────────────────────────────────────
    // SECTION 2 — Stripe
    // ────────────────────────────────────────────────────────────────────────
    $form['stripe'] = [
      '#type'       => 'details',
      '#title'      => $this->statusBadge($stripe_ok) . ' Paiements en ligne (Stripe)',
      '#open'       => !$stripe_ok,
      '#attributes' => ['class' => ['config-section', 'config-section--stripe', $stripe_ok ? 'config-ok' : 'config-warning']],
    ];

    $form['stripe']['stripe_help'] = [
      '#markup' => '<div class="config-help">'
        . '<strong>À quoi ça sert ?</strong> Stripe permet à vos clients de payer par carte bancaire.'
        . ' <a href="https://dashboard.stripe.com/test/apikeys" target="_blank">Ouvrir Stripe (clés API) →</a>'
        . '</div>',
    ];

    $form['stripe']['stripe_mode_info'] = [
      '#markup' => '<div class="config-mode-badge config-mode-' . ($stripe_ok ? 'test' : 'inactive') . '">'
        . ($stripe_ok ? '🧪 Mode test actif — utiliser la carte <strong>4242 4242 4242 4242</strong> pour tester' : '⚠️ Non configuré')
        . '</div>',
    ];

    $current_pk = $stripe->get('configuration.publishable_key') ?? '';
    $form['stripe']['publishable_key'] = [
      '#type'          => 'textfield',
      '#title'         => 'Clé publiable (Publishable key)',
      '#description'   => 'Commence par <code>pk_test_</code> (mode test) ou <code>pk_live_</code> (mode réel). Trouvée dans Stripe sous <em>Développeurs → Clés API</em>.',
      '#default_value' => str_contains($current_pk, 'PLACEHOLDER') ? '' : $current_pk,
      '#placeholder'   => 'pk_test_...',
      '#maxlength'     => 255,
    ];

    $form['stripe']['secret_key'] = [
      '#type'          => 'password',
      '#title'         => 'Clé secrète (Secret key)',
      '#description'   => 'Commence par <code>sk_test_</code> (mode test) ou <code>sk_live_</code> (mode réel). Cliquez "Révéler" dans Stripe pour la voir. Laissez vide pour ne pas modifier.',
      '#placeholder'   => $stripe_ok ? '(clé enregistrée — laisser vide pour ne pas changer)' : 'sk_test_...',
    ];

    $form['stripe']['webhook_secret'] = [
      '#type'          => 'password',
      '#title'         => 'Webhook secret',
      '#description'   => 'Commence par <code>whsec_</code>. Trouvé dans Stripe sous <em>Développeurs → Webhooks → votre endpoint → Signing secret</em>. Laissez vide pour ne pas modifier.',
      '#placeholder'   => !empty($stripe->get('configuration.webhook_secret')) ? '(secret enregistré — laisser vide pour ne pas changer)' : 'whsec_...',
    ];

    $webhook_url = \Drupal::request()->getSchemeAndHttpHost() . '/payment/notify/stripe_sandbox';
    $form['stripe']['webhook_url_info'] = [
      '#markup' => '<div class="config-webhook-url">'
        . '<strong>URL de votre webhook Stripe :</strong><br>'
        . '<code>' . htmlspecialchars($webhook_url) . '</code>'
        . '<br><small>Copiez cette URL dans Stripe → Développeurs → Webhooks → Ajouter un endpoint</small>'
        . '</div>',
    ];

    // ────────────────────────────────────────────────────────────────────────
    // SECTION 3 — Informations boutique
    // ────────────────────────────────────────────────────────────────────────
    $form['boutique'] = [
      '#type'       => 'details',
      '#title'      => $this->statusBadge((bool) $store_ok) . ' Informations de la boutique',
      '#open'       => FALSE,
      '#attributes' => ['class' => ['config-section', 'config-section--boutique', $store_ok ? 'config-ok' : 'config-warning']],
    ];

    $form['boutique']['boutique_help'] = [
      '#markup' => '<div class="config-help">Nom et email affiché sur les emails transactionnels et les factures.</div>',
    ];

    $form['boutique']['store_name'] = [
      '#type'          => 'textfield',
      '#title'         => 'Nom de la boutique',
      '#default_value' => $store ? $store->getName() : '',
      '#description'   => 'Affiché sur les emails et factures.',
    ];

    $form['boutique']['store_email'] = [
      '#type'          => 'email',
      '#title'         => 'Email de contact boutique',
      '#default_value' => $store ? $store->getEmail() : '',
      '#description'   => 'Email de contact affiché sur les confirmations.',
    ];

    $form['boutique']['smtp_from'] = [
      '#type'          => 'email',
      '#title'         => 'Adresse d\'expédition des emails',
      '#default_value' => $smtp->get('smtp_from') ?? 'boutique@atelierodesign.fr',
      '#description'   => 'Adresse affichée dans le champ "De :" de tous les emails.',
    ];

    // ────────────────────────────────────────────────────────────────────────
    // SECTION 4 — Hero de la page d'accueil
    // ────────────────────────────────────────────────────────────────────────
    $hero_fid = (int) ($settings->get('homepage_hero_image') ?? 0);
    $hero_file = $hero_fid ? File::load($hero_fid) : NULL;
    $form['homepage_hero'] = [
      '#type'       => 'details',
      '#title'      => 'Hero de la page d’accueil',
      '#open'       => FALSE,
      '#attributes' => ['class' => ['config-section', 'config-section--hero']],
    ];

    $form['homepage_hero']['hero_help'] = [
      '#markup' => '<div class="config-help">'
        . '<strong>À quoi ça sert ?</strong> Vous pouvez remplacer le fond coloré du hero d’accueil par une photo plus incarnée.'
        . ' Si aucune photo n’est choisie, le fond actuel reste automatiquement en place.'
        . '</div>',
    ];

    $form['homepage_hero']['homepage_hero_image'] = [
      '#type' => 'managed_file',
      '#title' => 'Photo de fond du hero',
      '#description' => 'Photo optionnelle. Recommandé : image horizontale, simple à lire, au moins 1600 px de large. Si vous la supprimez, le fond coloré revient automatiquement.',
      '#default_value' => $hero_file ? [$hero_file->id()] : [],
      '#upload_location' => 'public://homepage-hero/',
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg webp'],
        'file_validate_size' => [8 * 1024 * 1024],
      ],
    ];

    $form['homepage_hero']['homepage_hero_text_mode'] = [
      '#type' => 'select',
      '#title' => 'Couleur du texte sur la photo',
      '#description' => 'Automatique analyse l’image et choisit la variante la plus lisible. En cas de doute, forcez Clair ou Sombre.',
      '#options' => [
        'auto' => 'Automatique',
        'light' => 'Texte clair',
        'dark' => 'Texte sombre',
      ],
      '#default_value' => $settings->get('homepage_hero_text_mode') ?: 'auto',
    ];

    // ────────────────────────────────────────────────────────────────────────
    // Submit
    // ────────────────────────────────────────────────────────────────────────
    $form['actions'] = [
      '#type' => 'actions',
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
   * Génère un badge de statut HTML.
   */
  private function statusBadge(bool $ok): string {
    return $ok
      ? '<span class="config-badge config-badge--ok">✓ Configuré</span> '
      : '<span class="config-badge config-badge--warning">⚠ À configurer</span> ';
  }

  /**
   * Génère une carte de statut synthétique.
   */
  private function statusCard(string $label, bool $ok, string $description): string {
    $state = $ok ? 'Prêt' : 'À compléter';
    $classes = $ok ? 'config-status-card config-status-card--ok' : 'config-status-card config-status-card--warning';

    return '<div class="' . $classes . '">'
      . '<div class="config-status-card__label">' . htmlspecialchars($label) . '</div>'
      . '<div class="config-status-card__state">' . htmlspecialchars($state) . '</div>'
      . '<p class="config-status-card__text">' . htmlspecialchars($description) . '</p>'
      . '</div>';
  }

  /**
   * Envoie un email de test.
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
      ['subject' => 'Test email — Atelier O Design', 'body' => 'Si vous recevez cet email, votre configuration Mailgun fonctionne correctement.'],
    );

    if ($result['result'] ?? FALSE) {
      $this->messenger()->addStatus("Email de test envoyé à $email. Vérifiez votre boîte.");
    }
    else {
      $this->messenger()->addError("Échec de l'envoi. Vérifiez vos identifiants Mailgun ci-dessus.");
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // ── SMTP ─────────────────────────────────────────────────────────────────
    $smtp = \Drupal::configFactory()->getEditable('smtp.settings');
    $smtp->set('smtp_username', $form_state->getValue('smtp_username'));
    if ($form_state->getValue('smtp_password')) {
      $smtp->set('smtp_password', $form_state->getValue('smtp_password'));
    }
    if ($form_state->getValue('smtp_from')) {
      $smtp->set('smtp_from', $form_state->getValue('smtp_from'));
    }
    $smtp->set('smtp_on', TRUE);
    $smtp->save();

    // ── Stripe ───────────────────────────────────────────────────────────────
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

    // ── Hero homepage ───────────────────────────────────────────────────────
    $settings = \Drupal::configFactory()->getEditable('odesign_admin.settings');
    $current_hero_fid = (int) ($settings->get('homepage_hero_image') ?? 0);
    $new_hero_fids = array_values(array_filter($form_state->getValue('homepage_hero_image') ?? []));
    $new_hero_fid = $new_hero_fids ? (int) reset($new_hero_fids) : 0;

    if ($new_hero_fid) {
      $hero_file = File::load($new_hero_fid);
      if ($hero_file) {
        $hero_file->setPermanent();
        $hero_file->save();
        \Drupal::service('file.usage')->add($hero_file, 'odesign_admin', 'config', 'homepage_hero_image');
      }
    }

    if ($current_hero_fid && $current_hero_fid !== $new_hero_fid) {
      $old_file = File::load($current_hero_fid);
      if ($old_file) {
        \Drupal::service('file.usage')->delete($old_file, 'odesign_admin', 'config', 'homepage_hero_image');
      }
    }

    $settings
      ->set('homepage_hero_image', $new_hero_fid ?: NULL)
      ->set('homepage_hero_text_mode', $form_state->getValue('homepage_hero_text_mode') ?: 'auto')
      ->save();

    // ── Boutique ─────────────────────────────────────────────────────────────
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

    $this->messenger()->addStatus('Configuration enregistrée avec succès.');

    // Vider le cache de config.
    \Drupal::service('config.factory')->reset();
  }

}
