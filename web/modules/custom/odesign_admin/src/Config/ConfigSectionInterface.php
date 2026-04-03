<?php

namespace Drupal\odesign_admin\Config;

use Drupal\Core\Form\FormStateInterface;

/**
 * Interface pour les sections de configuration de la boutique.
 *
 * Chaque section contribue ses propres champs dans le hub de configuration.
 * Les modules tiers peuvent ajouter des sections via hook_odesign_boutique_config_sections_alter().
 */
interface ConfigSectionInterface {

  /**
   * Identifiant machine de la section (utilisé comme clé du #group vertical_tabs).
   */
  public function getId(): string;

  /**
   * Titre de l'onglet affiché dans la navigation vertical_tabs.
   */
  public function getTitle(): string;

  /**
   * Poids de tri entre sections (plus petit = à gauche).
   */
  public function getWeight(): int;

  /**
   * Construit les champs du formulaire pour cette section.
   *
   * @param array $tab
   *   Le render array de l'onglet (details avec #group déjà positionné).
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   État du formulaire.
   */
  public function buildForm(array &$tab, FormStateInterface $form_state): void;

  /**
   * Valide les champs de cette section.
   *
   * @param array $form
   *   Le formulaire complet.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   État du formulaire.
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void;

  /**
   * Sauvegarde les valeurs de cette section.
   *
   * @param array $form
   *   Le formulaire complet.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   État du formulaire.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void;

}
