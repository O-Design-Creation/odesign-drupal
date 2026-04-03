<?php

namespace Drupal\odesign_admin\Config;

use Drupal\file\Entity\File;
use Drupal\Core\Form\FormStateInterface;

/**
 * Section "Je me présente" + vidéo homepage (bloc fondatrice + vidéo atelier).
 */
class StoryConfigSection implements ConfigSectionInterface {

  public function getId(): string {
    return 'homepage_story';
  }

  public function getTitle(): string {
    return 'Je me présente';
  }

  public function getWeight(): int {
    return 50;
  }

  public function buildForm(array &$tab, FormStateInterface $form_state): void {
    $settings = \Drupal::configFactory()->get('odesign_admin.settings');

    $story_founder_fid      = (int) ($settings->get('homepage_story_founder_image') ?? 0);
    $story_video_fid        = (int) ($settings->get('homepage_story_video_file') ?? 0);
    $story_video_poster_fid = (int) ($settings->get('homepage_story_video_poster') ?? 0);

    $story_founder_file     = $story_founder_fid ? File::load($story_founder_fid) : NULL;
    $story_video_file       = $story_video_fid ? File::load($story_video_fid) : NULL;
    $story_video_poster_file = $story_video_poster_fid ? File::load($story_video_poster_fid) : NULL;

    $default_founder_text = "Je m'appelle Océane, j'ai 22 ans, je suis mariée et maman d'un petit garçon.\n\n"
      . "En 2023, j'ai créé mon entreprise avec un rêve simple mais essentiel : construire une vie professionnelle "
      . "qui me permette d'être présente pour ma famille et pour l'éducation de mon enfant.\n\n"
      . "Aujourd'hui, je vis de mon travail passion, un projet né du cœur, qui me ressemble et qui donne du sens à chaque journée.";

    $tab['story_help'] = [
      '#markup' => '<div class="config-help">'
        . 'Ce bloc renforce la confiance client : présentation de l\'atelier et vidéo réelle de fabrication. '
        . 'Tous les textes et médias ci-dessous sont modifiables.'
        . '</div>',
    ];

    // ─ Fondatrice ──────────────────────────────────────────────────────────────
    $tab['story_founder'] = [
      '#type'  => 'details',
      '#title' => 'Présentation de la fondatrice',
      '#open'  => TRUE,
    ];

    $tab['story_founder']['homepage_story_founder_title'] = [
      '#type'          => 'textfield',
      '#title'         => 'Titre de la section',
      '#default_value' => (string) ($settings->get('homepage_story_founder_title') ?? 'Un atelier incarné, un travail de cœur'),
      '#maxlength'     => 160,
    ];

    $tab['story_founder']['homepage_story_founder_text'] = [
      '#type'          => 'textarea',
      '#title'         => 'Texte de présentation',
      '#default_value' => (string) ($settings->get('homepage_story_founder_text') ?? $default_founder_text),
      '#rows'          => 7,
    ];

    $tab['story_founder']['homepage_story_founder_image'] = [
      '#type'              => 'managed_file',
      '#title'             => 'Photo de la fondatrice',
      '#default_value'     => $story_founder_file ? [$story_founder_file->id()] : [],
      '#upload_location'   => 'public://homepage-story/',
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg webp'],
        'file_validate_size'       => [8 * 1024 * 1024],
      ],
    ];

    // ─ Vidéo atelier ──────────────────────────────────────────────────────────
    $tab['story_video'] = [
      '#type'  => 'details',
      '#title' => 'Vidéo de fabrication',
      '#open'  => FALSE,
    ];

    $tab['story_video']['homepage_story_video_title'] = [
      '#type'          => 'textfield',
      '#title'         => 'Titre de la vidéo',
      '#default_value' => (string) ($settings->get('homepage_story_video_title') ?? 'Réalisation d\'un arbre de vie'),
      '#maxlength'     => 160,
    ];

    $tab['story_video']['homepage_story_video_text'] = [
      '#type'          => 'textarea',
      '#title'         => 'Description de la vidéo',
      '#default_value' => (string) ($settings->get('homepage_story_video_text')
        ?? 'Une vidéo réelle de fabrication pour montrer le niveau de finition, la précision de la gravure et le soin apporté aux créations.'),
      '#rows'          => 3,
    ];

    $tab['story_video']['homepage_story_video_file'] = [
      '#type'              => 'managed_file',
      '#title'             => 'Vidéo MP4',
      '#default_value'     => $story_video_file ? [$story_video_file->id()] : [],
      '#upload_location'   => 'public://homepage-story/',
      '#upload_validators' => [
        'file_validate_extensions' => ['mp4'],
        'file_validate_size'       => [30 * 1024 * 1024],
      ],
    ];

    $tab['story_video']['homepage_story_video_poster'] = [
      '#type'              => 'managed_file',
      '#title'             => 'Image d\'aperçu vidéo (poster)',
      '#default_value'     => $story_video_poster_file ? [$story_video_poster_file->id()] : [],
      '#upload_location'   => 'public://homepage-story/',
      '#upload_validators' => [
        'file_validate_extensions' => ['png jpg jpeg webp'],
        'file_validate_size'       => [8 * 1024 * 1024],
      ],
    ];
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $settings = \Drupal::configFactory()->getEditable('odesign_admin.settings');

    $this->manageFileField('homepage_story_founder_image', 'homepage_story_founder_image', $form_state, $settings);
    $this->manageFileField('homepage_story_video_file', 'homepage_story_video_file', $form_state, $settings);
    $this->manageFileField('homepage_story_video_poster', 'homepage_story_video_poster', $form_state, $settings);

    $settings
      ->set('homepage_story_founder_title', trim((string) $form_state->getValue('homepage_story_founder_title')))
      ->set('homepage_story_founder_text', trim((string) $form_state->getValue('homepage_story_founder_text')))
      ->set('homepage_story_video_title', trim((string) $form_state->getValue('homepage_story_video_title')))
      ->set('homepage_story_video_text', trim((string) $form_state->getValue('homepage_story_video_text')))
      ->save();
  }

  /**
   * Gère un champ de fichier managé (use/delete).
   */
  private function manageFileField(string $setting_key, string $form_key, FormStateInterface $form_state, $settings): void {
    $current_fid = (int) ($settings->get($setting_key) ?? 0);
    $new_fids    = array_values(array_filter($form_state->getValue($form_key) ?? []));
    $new_fid     = $new_fids ? (int) reset($new_fids) : 0;

    if ($new_fid) {
      $file = File::load($new_fid);
      if ($file) {
        $file->setPermanent();
        $file->save();
        \Drupal::service('file.usage')->add($file, 'odesign_admin', 'config', $setting_key);
      }
    }

    if ($current_fid && $current_fid !== $new_fid) {
      $old_file = File::load($current_fid);
      if ($old_file) {
        \Drupal::service('file.usage')->delete($old_file, 'odesign_admin', 'config', $setting_key);
      }
    }

    $settings->set($setting_key, $new_fid ?: NULL);
  }

}
