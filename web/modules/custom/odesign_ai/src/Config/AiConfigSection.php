<?php

namespace Drupal\odesign_ai\Config;

use Drupal\Core\Form\FormStateInterface;
use Drupal\odesign_admin\Config\ConfigSectionInterface;

/**
 * Section "Intelligence artificielle" dans BoutiqueConfigForm.
 *
 * Permet au gestionnaire de configurer le fournisseur IA, le modèle et la clé
 * API OpenAI utilisée pour la génération de texte (fiches produit, articles,
 * newsletter). La clé API est stockée dans \Drupal::state() et n'est jamais
 * exportée dans config/sync/.
 */
class AiConfigSection implements ConfigSectionInterface {

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    return 'ia';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(): string {
    $configured = !empty(\Drupal::state()->get('odesign_ai.api_key', ''));
    return ($configured ? '✓ ' : '⚠ ') . 'Intelligence artificielle';
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight(): int {
    return 60;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array &$tab, FormStateInterface $form_state): void {
    $config    = \Drupal::configFactory()->get('odesign_ai.settings');
    $key_set   = !empty(\Drupal::state()->get('odesign_ai.api_key', ''));

    $tab['#attributes']['class'][] = $key_set ? 'config-ok' : 'config-warning';

    $tab['ai_help'] = [
      '#markup' => '<div class="config-help">'
        . '<strong>À quoi ça sert ?</strong> L\'assistant IA génère automatiquement des textes '
        . 'pour vos fiches produit, articles de blog et newsletters. '
        . 'Il utilise l\'IA d\'OpenAI (GPT-4o-mini par défaut). '
        . '<a href="https://platform.openai.com/api-keys" target="_blank">Obtenir une clé API OpenAI →</a>'
        . '</div>',
    ];

    $tab['ai_provider'] = [
      '#type'          => 'select',
      '#title'         => 'Fournisseur IA',
      '#options'       => [
        'openai'    => 'OpenAI (GPT-4o-mini)',
        'mistral'   => 'Mistral',
        'anthropic' => 'Anthropic Claude',
      ],
      '#default_value' => $config->get('provider') ?? 'openai',
      '#description'   => 'Fournisseur utilisé pour la génération de texte. Seul OpenAI est pris en charge en V1.',
    ];

    $tab['ai_model'] = [
      '#type'          => 'textfield',
      '#title'         => 'Modèle',
      '#default_value' => $config->get('model') ?? 'gpt-4o-mini',
      '#description'   => 'Exemple : <code>gpt-4o-mini</code> (économique), <code>gpt-4o</code> (haute qualité).',
      '#maxlength'     => 64,
    ];

    $tab['ai_api_key'] = [
      '#type'          => 'password',
      '#title'         => 'Clé API OpenAI',
      '#default_value' => '',
      '#description'   => 'Commence par <code>sk-</code>. '
        . ($key_set
          ? 'Une clé est déjà enregistrée — laissez vide pour la conserver.'
          : 'Aucune clé enregistrée. Saisissez votre clé pour activer l\'assistant IA.'),
      '#attributes'    => ['autocomplete' => 'new-password'],
      '#placeholder'   => $key_set ? '(clé enregistrée — laisser vide pour ne pas changer)' : 'sk-...',
    ];

    $tab['ai_status'] = [
      '#markup' => '<div class="config-mode-badge config-mode-' . ($key_set ? 'ok' : 'inactive') . '">'
        . ($key_set
          ? '✅ Clé API configurée — l\'assistant IA est actif.'
          : '⚠️ Clé API non configurée — saisissez votre clé OpenAI ci-dessus.')
        . '</div>',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $key = $form_state->getValue('ai_api_key');
    if (!empty($key) && !str_starts_with(trim($key), 'sk-')) {
      $form_state->setErrorByName('ai_api_key', 'La clé API OpenAI doit commencer par <code>sk-</code>.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Sauvegarder provider et modèle dans la config.
    $config = \Drupal::configFactory()->getEditable('odesign_ai.settings');
    $config
      ->set('provider', $form_state->getValue('ai_provider'))
      ->set('model', $form_state->getValue('ai_model'))
      ->save();

    // Sauvegarder la clé API dans state (jamais exportée en config/sync/).
    $key = $form_state->getValue('ai_api_key');
    if (!empty(trim($key))) {
      \Drupal::state()->set('odesign_ai.api_key', trim($key));
    }
  }

}
