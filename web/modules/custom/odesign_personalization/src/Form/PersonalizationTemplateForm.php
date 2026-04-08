<?php

namespace Drupal\odesign_personalization\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\odesign_personalization\Entity\PersonalizationTemplate;

/**
 * Builder form for PersonalizationTemplate config entities.
 *
 * Provides a full drag-and-drop-style field manager allowing the gestionnaire
 * to define personalization fields with types, validation rules, and price
 * modifiers — all without writing code.
 */
class PersonalizationTemplateForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\odesign_personalization\Entity\PersonalizationTemplate $template */
    $template = $this->entity;

    $form['#attached']['library'][] = 'odesign_personalization/builder';
    $form['#attributes']['class'][] = 'perso-template-form';

    // --- Infos générales ---------------------------------------------------
    $form['label'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Nom du template'),
      '#default_value' => $template->label(),
      '#required'      => TRUE,
      '#maxlength'     => 128,
      '#description'   => $this->t('Ex : "Prénom simple", "Support naissance", "Gravure photo"'),
    ];

    $form['id'] = [
      '#type'          => 'machine_name',
      '#default_value' => $template->id(),
      '#disabled'      => !$template->isNew(),
      '#machine_name'  => ['exists' => '\Drupal\odesign_personalization\Entity\PersonalizationTemplate::load'],
    ];

    $form['description'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Description (usage interne)'),
      '#default_value' => $template->getDescription(),
      '#maxlength'     => 255,
    ];

    // --- Champs de personnalisation ----------------------------------------
    $form['fields_wrapper'] = [
      '#type'       => 'container',
      '#attributes' => ['id' => 'fields-wrapper'],
    ];

    $form['fields_wrapper']['fields_title'] = [
      '#markup' => '<h3>' . $this->t('Champs de personnalisation') . '</h3>',
    ];

    // Récupérer les champs en cours (depuis form_state si AJAX, sinon depuis l'entité)
    $fields = $form_state->get('fields');
    if ($fields === NULL) {
      $fields = $template->getFields();
      $form_state->set('fields', $fields);
    }

    // Tableau des champs existants
    $form['fields_wrapper']['fields_table'] = [
      '#type'       => 'table',
      '#header'     => [
        $this->t('Libellé'),
        $this->t('Type'),
        $this->t('Obligatoire'),
        $this->t('Modificateur de prix'),
        $this->t('Actions'),
        $this->t('Ordre'),
      ],
      '#empty'      => $this->t('Aucun champ. Ajoutez un champ ci-dessous.'),
      '#tabledrag'  => [
        [
          'action'       => 'order',
          'relationship' => 'sibling',
          'group'        => 'field-weight',
        ],
      ],
    ];

    foreach ($fields as $delta => $field) {
      $form['fields_wrapper']['fields_table'][$delta] = $this->buildFieldRow($delta, $field, $form_state);
    }

    // Formulaire d'ajout d'un nouveau champ
    $form['fields_wrapper']['new_field'] = $this->buildNewFieldForm($form_state);

    $form['fields_wrapper']['add_field'] = [
      '#type'   => 'submit',
      '#value'  => $this->t('+ Ajouter ce champ'),
      '#submit' => ['::addField'],
      '#ajax'   => [
        'callback' => '::ajaxRefreshFields',
        'wrapper'  => 'fields-wrapper',
      ],
      '#limit_validation_errors' => [['new_field']],
    ];

    return $form;
  }

  /**
   * Builds a table row for an existing field.
   */
  protected function buildFieldRow(int $delta, array $field, FormStateInterface $form_state): array {
    $modType = $field['price_modifier']['type'] ?? 'none';
    $modAmount = $field['price_modifier']['amount'] ?? '';
    $modLabel = $field['price_modifier']['label'] ?? '';

    $priceDisplay = $modType === 'none' || empty($modAmount)
      ? '—'
      : ($modType === 'fixed' ? '+' . $modAmount . ' €' : '+' . $modAmount . ' €/unité') . ($modLabel ? ' (' . $modLabel . ')' : '');

    $row = [
      '#attributes' => ['class' => ['draggable']],
    ];

    $row['label'] = [
      '#markup' => '<strong>' . htmlspecialchars($field['label'] ?? '') . '</strong>'
        . (!empty($field['help']) ? '<br><small>' . htmlspecialchars($field['help']) . '</small>' : ''),
    ];

    $fieldTypes = PersonalizationTemplate::fieldTypes();
    $row['type'] = [
      '#markup' => $fieldTypes[$field['type'] ?? 'text'] ?? $field['type'],
    ];

    $row['required'] = [
      '#markup' => !empty($field['required']) ? '✓' : '—',
    ];

    $row['price_modifier'] = [
      '#markup' => $priceDisplay,
    ];

    $row['actions'] = [
      '#type'   => 'container',
    ];
    $row['actions']['edit'] = [
      '#type'              => 'submit',
      '#value'             => $this->t('Éditer'),
      '#name'              => 'edit_field_' . $delta,
      '#submit'            => ['::editField'],
      '#ajax'              => [
        'callback' => '::ajaxRefreshFields',
        'wrapper'  => 'fields-wrapper',
      ],
      '#attributes'        => ['data-delta' => $delta],
      '#limit_validation_errors' => [],
    ];
    $row['actions']['remove'] = [
      '#type'              => 'submit',
      '#value'             => $this->t('Supprimer'),
      '#name'              => 'remove_field_' . $delta,
      '#submit'            => ['::removeField'],
      '#ajax'              => [
        'callback' => '::ajaxRefreshFields',
        'wrapper'  => 'fields-wrapper',
      ],
      '#attributes'        => ['data-delta' => $delta, 'class' => ['button--danger']],
      '#limit_validation_errors' => [],
    ];

    $row['weight'] = [
      '#type'          => 'weight',
      '#default_value' => $field['weight'] ?? $delta,
      '#attributes'    => ['class' => ['field-weight']],
    ];

    return $row;
  }

  /**
   * Builds the "add new field" sub-form.
   */
  protected function buildNewFieldForm(FormStateInterface $form_state): array {
    $editing = $form_state->get('editing_field');
    $editData = $editing !== NULL ? ($form_state->get('fields')[$editing] ?? []) : [];

    $form = [
      '#type'       => 'fieldset',
      '#title'      => $editing !== NULL
        ? $this->t('Modifier le champ « @label »', ['@label' => $editData['label'] ?? ''])
        : $this->t('Nouveau champ'),
      '#collapsible' => FALSE,
    ];

    $form['label'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Libellé (affiché au client)'),
      '#default_value' => $editData['label'] ?? '',
      '#required'      => FALSE,
      '#placeholder'   => $this->t('Ex : Prénom, Texte de dédicace, Photo...'),
    ];

    $form['type'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Type de champ'),
      '#options'       => PersonalizationTemplate::fieldTypes(),
      '#default_value' => $editData['type'] ?? 'text',
    ];

    $form['required'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Champ obligatoire'),
      '#default_value' => $editData['required'] ?? FALSE,
    ];

    $form['placeholder'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Texte de suggestion (placeholder)'),
      '#default_value' => $editData['placeholder'] ?? '',
      '#placeholder'   => $this->t('Ex : Hugo, Marie...'),
    ];

    $form['help'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Aide (texte sous le champ)'),
      '#default_value' => $editData['help'] ?? '',
    ];

    $form['maxlength'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Longueur maximale (0 = illimitée)'),
      '#default_value' => $editData['maxlength'] ?? 0,
      '#min'           => 0,
      '#max'           => 10000,
    ];

    // Options (pour select/radio/checkboxes)
    $form['options'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('Options (une par ligne, pour les types "liste de choix")'),
      '#default_value' => implode("\n", array_column($editData['options'] ?? [], 'label')),
      '#description'   => $this->t('Saisissez une option par ligne. Ex : Argent, Or, Bronze'),
      '#rows'          => 4,
    ];

    // Groupe répétable
    $form['group_max'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Nb maximum de répétitions (pour "Groupe répétable")'),
      '#default_value' => $editData['group_max'] ?? 5,
      '#min'           => 1,
      '#max'           => 25,
    ];

    // Modificateur de prix
    $form['price_modifier'] = [
      '#type'       => 'fieldset',
      '#title'      => $this->t('Impact sur le prix'),
    ];

    $currentModType = $editData['price_modifier']['type'] ?? 'none';
    $form['price_modifier']['type'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Type de modificateur'),
      '#options'       => PersonalizationTemplate::priceModifierTypes(),
      '#default_value' => $currentModType,
    ];

    $form['price_modifier']['amount'] = [
      '#type'          => 'number',
      '#title'         => $this->t('Montant (€)'),
      '#default_value' => $editData['price_modifier']['amount'] ?? '',
      '#min'           => 0,
      '#step'          => '0.01',
      '#placeholder'   => '5.00',
    ];

    $form['price_modifier']['label'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('Libellé du supplément (affiché au client)'),
      '#default_value' => $editData['price_modifier']['label'] ?? '',
      '#placeholder'   => $this->t('Ex : + 5 € (gravure photo)'),
    ];

    if ($editing !== NULL) {
      $form['editing_delta'] = [
        '#type'  => 'hidden',
        '#value' => $editing,
      ];
      $form['save_edit'] = [
        '#type'   => 'submit',
        '#value'  => $this->t('Enregistrer les modifications'),
        '#submit' => ['::saveFieldEdit'],
        '#ajax'   => [
          'callback' => '::ajaxRefreshFields',
          'wrapper'  => 'fields-wrapper',
        ],
        '#limit_validation_errors' => [['new_field']],
      ];
      $form['cancel_edit'] = [
        '#type'   => 'submit',
        '#value'  => $this->t('Annuler'),
        '#submit' => ['::cancelFieldEdit'],
        '#ajax'   => [
          'callback' => '::ajaxRefreshFields',
          'wrapper'  => 'fields-wrapper',
        ],
        '#limit_validation_errors' => [],
      ];
    }

    return $form;
  }

  // ---------------------------------------------------------------------------
  // AJAX / submit handlers
  // ---------------------------------------------------------------------------

  /**
   * AJAX callback — refreshes the fields wrapper.
   */
  public function ajaxRefreshFields(array &$form, FormStateInterface $form_state): array {
    return $form['fields_wrapper'];
  }

  /**
   * Submit: add a new field from the new_field sub-form.
   */
  public function addField(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValue('new_field') ?? [];
    $fields = $form_state->get('fields') ?? [];
    $newField = $this->buildFieldDefinition($values, count($fields));
    if (!empty($newField['label'])) {
      $fields[] = $newField;
      $form_state->set('fields', $fields);
    }
    $form_state->setRebuild();
  }

  /**
   * Submit: start editing an existing field.
   */
  public function editField(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $delta = (int) ($trigger['#attributes']['data-delta'] ?? 0);
    $form_state->set('editing_field', $delta);
    $form_state->setRebuild();
  }

  /**
   * Submit: save edits to an existing field.
   */
  public function saveFieldEdit(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValue('new_field') ?? [];
    $delta = (int) ($values['editing_delta'] ?? 0);
    $fields = $form_state->get('fields') ?? [];
    if (isset($fields[$delta])) {
      $fields[$delta] = $this->buildFieldDefinition($values, $fields[$delta]['weight'] ?? $delta);
    }
    $form_state->set('fields', $fields);
    $form_state->set('editing_field', NULL);
    $form_state->setRebuild();
  }

  /**
   * Submit: cancel field editing.
   */
  public function cancelFieldEdit(array &$form, FormStateInterface $form_state): void {
    $form_state->set('editing_field', NULL);
    $form_state->setRebuild();
  }

  /**
   * Submit: remove a field.
   */
  public function removeField(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $delta = (int) ($trigger['#attributes']['data-delta'] ?? 0);
    $fields = $form_state->get('fields') ?? [];
    unset($fields[$delta]);
    $fields = array_values($fields);
    $form_state->set('fields', $fields);
    $form_state->setRebuild();
  }

  /**
   * Builds a normalized field definition array from form values.
   */
  protected function buildFieldDefinition(array $values, int $weight): array {
    $rawOptions = array_filter(array_map('trim', explode("\n", $values['options'] ?? '')));
    $options = array_values(array_map(fn($o) => ['value' => strtolower(preg_replace('/[^a-z0-9_]/i', '_', $o)), 'label' => $o], $rawOptions));

    $modType   = $values['price_modifier']['type'] ?? 'none';
    $modAmount = trim($values['price_modifier']['amount'] ?? '');
    $modLabel  = trim($values['price_modifier']['label'] ?? '');

    return [
      'id'          => uniqid('field_'),
      'label'       => trim($values['label'] ?? ''),
      'type'        => $values['type'] ?? 'text',
      'required'    => (bool) ($values['required'] ?? FALSE),
      'placeholder' => trim($values['placeholder'] ?? ''),
      'help'        => trim($values['help'] ?? ''),
      'maxlength'   => (int) ($values['maxlength'] ?? 0) ?: NULL,
      'weight'      => $weight,
      'options'     => $options,
      'group_max'   => (int) ($values['group_max'] ?? 5),
      'price_modifier' => [
        'type'     => $modType,
        'amount'   => $modAmount,
        'currency' => 'EUR',
        'label'    => $modLabel,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    $fields = $form_state->get('fields') ?? [];
    if (empty($fields)) {
      $form_state->setErrorByName('fields_wrapper', $this->t('Le template doit contenir au moins un champ.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Récupérer les poids depuis la table (drag & drop).
    $weights = $form_state->getValue('fields_table') ?? [];
    $fields = $form_state->get('fields') ?? [];
    foreach ($fields as $delta => &$field) {
      $field['weight'] = (int) ($weights[$delta]['weight'] ?? $delta);
    }
    usort($fields, fn($a, $b) => $a['weight'] <=> $b['weight']);
    $form_state->set('fields', $fields);
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    /** @var \Drupal\odesign_personalization\Entity\PersonalizationTemplate $template */
    $template = $this->entity;
    $template->setFields($form_state->get('fields') ?? []);

    $status = parent::save($form, $form_state);
    $label = $template->label();

    if ($status === SAVED_NEW) {
      $this->messenger()->addStatus($this->t('Template « @label » créé avec succès.', ['@label' => $label]));
    }
    else {
      $this->messenger()->addStatus($this->t('Template « @label » mis à jour.', ['@label' => $label]));
    }

    $form_state->setRedirectUrl($template->toUrl('collection'));
    return $status;
  }

}
