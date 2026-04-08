<?php

namespace Drupal\odesign_admin\Config;

use Drupal\Core\Form\FormStateInterface;

/**
 * Section "Comment ça marche" — 3 étapes du processus de commande.
 */
class MethodeConfigSection implements ConfigSectionInterface {

  public function getId(): string {
    return 'homepage_methode';
  }

  public function getTitle(): string {
    return 'Comment ça marche';
  }

  public function getWeight(): int {
    return 55;
  }

  public function buildForm(array &$tab, FormStateInterface $form_state): void {
    $settings = \Drupal::configFactory()->get('odesign_admin.settings');

    $tab['methode_help'] = [
      '#markup' => '<div class="config-help">'
        . 'Cette section présente le processus de commande en 3 étapes sur la homepage. '
        . 'Elle rassure les visiteurs sur la simplicité du parcours d\'achat personnalisé.'
        . '</div>',
    ];

    $tab['homepage_methode_title'] = [
      '#type'          => 'textfield',
      '#title'         => 'Titre de la section',
      '#default_value' => (string) ($settings->get('homepage_methode_title') ?? 'Comment ça marche ?'),
      '#maxlength'     => 120,
    ];

    $tab['homepage_methode_subtitle'] = [
      '#type'          => 'textfield',
      '#title'         => 'Sous-titre (optionnel)',
      '#default_value' => (string) ($settings->get('homepage_methode_subtitle') ?? 'Recevoir une création personnalisée, c\'est simple et rapide.'),
      '#maxlength'     => 200,
    ];

    // Icônes Phosphor disponibles pour les étapes.
    $icon_options = [
      'ph-pencil-simple' => '✏️  ph-pencil-simple — Personnaliser',
      'ph-scissors'      => '✂️  ph-scissors — Découpe / fabrication',
      'ph-gift'          => '🎁  ph-gift — Recevoir / offrir',
      'ph-package'       => '📦  ph-package — Livraison / colis',
      'ph-star'          => '⭐  ph-star — Qualité',
      'ph-heart'         => '❤️  ph-heart — Coup de cœur',
      'ph-chat-circle'   => '💬  ph-chat-circle — Commande / contact',
      'ph-truck'         => '🚚  ph-truck — Livraison',
      'ph-check-circle'  => '✅  ph-check-circle — Validation',
    ];

    for ($i = 1; $i <= 3; $i++) {
      $defaults = $this->getStepDefaults($i);
      $tab["step_{$i}"] = [
        '#type'  => 'details',
        '#title' => "Étape {$i}",
        '#open'  => TRUE,
      ];
      $tab["step_{$i}"]["homepage_methode_step{$i}_title"] = [
        '#type'          => 'textfield',
        '#title'         => 'Titre de l\'étape',
        '#default_value' => (string) ($settings->get("homepage_methode_step{$i}_title") ?? $defaults['title']),
        '#maxlength'     => 80,
        '#required'      => TRUE,
      ];
      $tab["step_{$i}"]["homepage_methode_step{$i}_desc"] = [
        '#type'          => 'textarea',
        '#title'         => 'Description courte',
        '#default_value' => (string) ($settings->get("homepage_methode_step{$i}_desc") ?? $defaults['desc']),
        '#rows'          => 2,
        '#maxlength'     => 200,
      ];
      $tab["step_{$i}"]["homepage_methode_step{$i}_icon"] = [
        '#type'          => 'select',
        '#title'         => 'Icône Phosphor',
        '#options'       => $icon_options,
        '#default_value' => (string) ($settings->get("homepage_methode_step{$i}_icon") ?? $defaults['icon']),
      ];
    }
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Pas de validation spécifique — les champs sont tous optionnels.
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = \Drupal::configFactory()->getEditable('odesign_admin.settings');
    $config->set('homepage_methode_title',    $form_state->getValue('homepage_methode_title'));
    $config->set('homepage_methode_subtitle', $form_state->getValue('homepage_methode_subtitle'));
    for ($i = 1; $i <= 3; $i++) {
      $config->set("homepage_methode_step{$i}_title", $form_state->getValue("homepage_methode_step{$i}_title"));
      $config->set("homepage_methode_step{$i}_desc",  $form_state->getValue("homepage_methode_step{$i}_desc"));
      $config->set("homepage_methode_step{$i}_icon",  $form_state->getValue("homepage_methode_step{$i}_icon"));
    }
    $config->save();
  }

  /**
   * Valeurs par défaut pour chaque étape.
   */
  private function getStepDefaults(int $step): array {
    $defaults = [
      1 => [
        'title' => 'Je personnalise',
        'desc'  => 'Choisissez votre création et indiquez-nous le texte, le prénom ou le motif souhaité.',
        'icon'  => 'ph-pencil-simple',
      ],
      2 => [
        'title' => 'L\'atelier crée',
        'desc'  => 'Océane grave et découpe à la main votre pièce dans son atelier. Chaque création est unique.',
        'icon'  => 'ph-scissors',
      ],
      3 => [
        'title' => 'Je reçois ma création',
        'desc'  => 'Votre commande est soigneusement emballée et expédiée ou disponible en retrait atelier.',
        'icon'  => 'ph-gift',
      ],
    ];
    return $defaults[$step];
  }

}
