<?php

namespace Drupal\odesign_personalization\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the PersonalizationTemplate config entity.
 *
 * A template defines all personalization fields for a product type:
 * field definitions, types, validation rules, and price modifiers.
 *
 * @ConfigEntityType(
 *   id = "personalization_template",
 *   label = @Translation("Personalization template"),
 *   label_collection = @Translation("Personalization templates"),
 *   label_singular = @Translation("personalization template"),
 *   label_plural = @Translation("personalization templates"),
 *   label_count = @PluralTranslation(
 *     singular = "@count personalization template",
 *     plural = "@count personalization templates",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\odesign_personalization\PersonalizationTemplateListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider",
 *     },
 *     "form" = {
 *       "default" = "Drupal\odesign_personalization\Form\PersonalizationTemplateForm",
 *       "edit"    = "Drupal\odesign_personalization\Form\PersonalizationTemplateForm",
 *       "delete"  = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   config_prefix = "personalization_template",
 *   admin_permission = "administer odesign personalization",
 *   entity_keys = {
 *     "id"    = "id",
 *     "label" = "label",
 *   },
 *   links = {
 *     "collection"  = "/admin/boutique/personnalisation/templates",
 *     "add-form"    = "/admin/boutique/personnalisation/templates/ajouter",
 *     "edit-form"   = "/admin/boutique/personnalisation/templates/{personalization_template}/modifier",
 *     "delete-form" = "/admin/boutique/personnalisation/templates/{personalization_template}/supprimer",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "fields",
 *   }
 * )
 */
class PersonalizationTemplate extends ConfigEntityBase implements PersonalizationTemplateInterface {

  /** @var string */
  protected string $id = '';

  /** @var string */
  protected string $label = '';

  /** @var string */
  protected string $description = '';

  /**
   * Field definitions array.
   *
   * Each element is an associative array with keys:
   *   id, label, type, required, placeholder, help, maxlength, weight,
   *   options, group_fields, group_min, group_max, price_modifier.
   *
   * @var array
   */
  protected array $fields = [];

  /**
   * Supported field types.
   */
  public static function fieldTypes(): array {
    return [
      'text'             => 'Texte court (prénom, titre...)',
      'textarea'         => 'Texte long (liste de prénoms, message...)',
      'number'           => 'Nombre (numéro, quantité...)',
      'date'             => 'Date',
      'select'           => 'Liste de choix (menu déroulant)',
      'radio'            => 'Choix unique (boutons radio)',
      'checkboxes'       => 'Choix multiples (cases à cocher)',
      'file'             => 'Photo / fichier',
      'repeatable_group' => 'Groupe répétable (ex : plusieurs contacts)',
    ];
  }

  /**
   * Price modifier types.
   */
  public static function priceModifierTypes(): array {
    return [
      'none'     => 'Aucun impact sur le prix',
      'fixed'    => 'Montant fixe si le champ est renseigné (ex : +5 € pour gravure photo)',
      'per_unit' => 'Montant × nombre d\'occurrences (ex : +2 € par prénom supplémentaire)',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFields(): array {
    $fields = $this->fields ?: [];
    usort($fields, fn($a, $b) => ($a['weight'] ?? 0) <=> ($b['weight'] ?? 0));
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getField(string $fieldId): ?array {
    foreach ($this->fields as $field) {
      if (($field['id'] ?? '') === $fieldId) {
        return $field;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setFields(array $fields): static {
    $this->fields = $fields;
    return $this;
  }

  /**
   * Returns TRUE if any field has a price modifier.
   */
  public function hasPriceModifiers(): bool {
    foreach ($this->fields as $field) {
      $mod = $field['price_modifier'] ?? [];
      if (!empty($mod['type']) && $mod['type'] !== 'none' && !empty($mod['amount'])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Returns the description.
   */
  public function getDescription(): string {
    return $this->description ?? '';
  }

}
