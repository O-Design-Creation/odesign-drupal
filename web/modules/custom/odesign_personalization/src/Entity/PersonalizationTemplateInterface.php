<?php

namespace Drupal\odesign_personalization\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Interface for PersonalizationTemplate config entities.
 */
interface PersonalizationTemplateInterface extends ConfigEntityInterface {

  /**
   * Returns the ordered list of field definitions.
   */
  public function getFields(): array;

  /**
   * Returns a single field definition by id, or NULL.
   */
  public function getField(string $fieldId): ?array;

  /**
   * Sets the field definitions.
   */
  public function setFields(array $fields): static;

  /**
   * Returns TRUE if any field carries a price modifier.
   */
  public function hasPriceModifiers(): bool;

}
