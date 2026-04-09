<?php

namespace Drupal\odesign_admin\Config;

use Drupal\file\Entity\File;
use Drupal\Core\Form\FormStateInterface;

/**
 * Section "Page d'accueil — Hero" (image de fond, couleur texte).
 */
class HeroConfigSection implements ConfigSectionInterface {

  public function getId(): string {
    return 'homepage_hero';
  }

  public function getTitle(): string {
    return 'Page d\'accueil — Hero';
  }

  public function getWeight(): int {
    return 40;
  }

  public function buildForm(array &$tab, FormStateInterface $form_state): void {
    $settings = \Drupal::configFactory()->get('odesign_admin.settings');

    $hero_fid  = (int) ($settings->get('homepage_hero_image') ?? 0);
    $hero_file = $hero_fid ? File::load($hero_fid) : NULL;
    $pancarte_fid = (int) ($settings->get('homepage_pancarte_offer_image') ?? 0);
    $pancarte_file = $pancarte_fid ? File::load($pancarte_fid) : NULL;

    $tab['hero_help'] = [
      '#markup' => '<div class="config-help">'
        . '<strong>À quoi ça sert ?</strong> Choisissez si le hero d\'accueil affiche une image de fond '
        . 'ou un fond coloré simple. Vous pouvez téléverser ou remplacer la photo à tout moment.'
        . '</div>',
    ];

    $tab['homepage_hero_enable_image'] = [
      '#type'          => 'checkbox',
      '#title'         => 'Afficher une image de fond dans le hero',
      '#default_value' => (bool) ($settings->get('homepage_hero_enable_image') ?? TRUE),
    ];

    $tab['homepage_hero_image'] = [
      '#type'              => 'managed_file',
      '#title'             => 'Photo de fond du hero',
      '#description'       => 'Recommandé : image horizontale, simple à lire, au moins 1600 px de large.',
      '#default_value'     => $hero_file ? [$hero_file->id()] : [],
      '#upload_location'   => 'public://homepage-hero/',
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg webp'],
        'file_validate_size'       => [8 * 1024 * 1024],
        'file_validate_image_resolution' => ['1600x0'],
      ],
      '#states' => [
        'visible' => [
          ':input[name="homepage_hero_enable_image"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $tab['homepage_hero_text_mode'] = [
      '#type'          => 'select',
      '#title'         => 'Couleur du texte sur la photo',
      '#description'   => 'Automatique analyse l\'image et choisit la variante la plus lisible.',
      '#options'       => [
        'auto'  => 'Automatique',
        'light' => 'Texte clair',
        'dark'  => 'Texte sombre',
      ],
      '#default_value' => $settings->get('homepage_hero_text_mode') ?: 'auto',
      '#states' => [
        'visible' => [
          ':input[name="homepage_hero_enable_image"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $tab['homepage_pancarte_offer_image'] = [
      '#type'              => 'managed_file',
      '#title'             => 'Photo dédiée pancartes événementielles',
      '#description'       => 'Utilisée pour la mise en avant homepage + univers pancartes. Importez une version nette (largeur recommandée : 1600 px min).',
      '#default_value'     => $pancarte_file ? [$pancarte_file->id()] : [],
      '#upload_location'   => 'public://homepage-pancarte/',
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg webp'],
        'file_validate_size'       => [8 * 1024 * 1024],
        'file_validate_image_resolution' => ['1600x0'],
      ],
    ];
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $settings        = \Drupal::configFactory()->getEditable('odesign_admin.settings');
    $hero_enabled    = (bool) $form_state->getValue('homepage_hero_enable_image');
    $current_hero_fid = (int) ($settings->get('homepage_hero_image') ?? 0);
    $new_hero_fids   = array_values(array_filter($form_state->getValue('homepage_hero_image') ?? []));
    $new_hero_fid    = $hero_enabled && $new_hero_fids ? (int) reset($new_hero_fids) : 0;
    $current_pancarte_fid = (int) ($settings->get('homepage_pancarte_offer_image') ?? 0);
    $new_pancarte_fids = array_values(array_filter($form_state->getValue('homepage_pancarte_offer_image') ?? []));
    $new_pancarte_fid = $new_pancarte_fids ? (int) reset($new_pancarte_fids) : 0;

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

    if ($new_pancarte_fid) {
      $pancarte_file = File::load($new_pancarte_fid);
      if ($pancarte_file) {
        $pancarte_file->setPermanent();
        $pancarte_file->save();
        \Drupal::service('file.usage')->add($pancarte_file, 'odesign_admin', 'config', 'homepage_pancarte_offer_image');
      }
    }

    if ($current_pancarte_fid && $current_pancarte_fid !== $new_pancarte_fid) {
      $old_pancarte_file = File::load($current_pancarte_fid);
      if ($old_pancarte_file) {
        \Drupal::service('file.usage')->delete($old_pancarte_file, 'odesign_admin', 'config', 'homepage_pancarte_offer_image');
      }
    }

    $settings
      ->set('homepage_hero_enable_image', $hero_enabled)
      ->set('homepage_hero_image', $new_hero_fid ?: NULL)
      ->set('homepage_pancarte_offer_image', $new_pancarte_fid ?: NULL)
      ->set('homepage_hero_text_mode', $form_state->getValue('homepage_hero_text_mode') ?: 'auto')
      ->save();
  }

}
