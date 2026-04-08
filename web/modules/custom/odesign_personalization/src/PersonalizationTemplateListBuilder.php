<?php

namespace Drupal\odesign_personalization;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of PersonalizationTemplate entities.
 */
class PersonalizationTemplateListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function getTitle(): string {
    return (string) $this->t('Templates de personnalisation');
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    return [
      'label'       => $this->t('Nom du template'),
      'id'          => $this->t('Identifiant'),
      'fields'      => $this->t('Champs'),
      'price'       => $this->t('Modificateurs prix'),
      'description' => $this->t('Description'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\odesign_personalization\Entity\PersonalizationTemplate $entity */
    $fieldCount = count($entity->getFields());
    return [
      'label'       => $entity->label(),
      'id'          => $entity->id(),
      'fields'      => $fieldCount . ' champ' . ($fieldCount > 1 ? 's' : ''),
      'price'       => $entity->hasPriceModifiers()
        ? ['data' => ['#markup' => '<span style="color:#2e7d32;font-weight:600">✓ oui</span>']]
        : '—',
      'description' => $entity->getDescription() ?: '—',
    ] + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();
    $build['table']['#empty'] = $this->t(
      'Aucun template de personnalisation. <a href=":add">Créer un premier template</a>.',
      [':add' => '/admin/boutique/personnalisation/templates/ajouter']
    );
    return $build;
  }

}
