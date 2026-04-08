<?php

declare(strict_types=1);

namespace Drupal\odesign_admin\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Bloc "Campagne en cours" pour la homepage.
 *
 * Affiche la dernière campagne avec promote=1 et status=1.
 * Le bloc est invisible s'il n'y a aucune campagne active.
 *
 * @Block(
 *   id = "campagne_en_cours",
 *   admin_label = @Translation("Campagne en cours"),
 *   category = @Translation("Atelier O Design"),
 * )
 */
final class CampagneEnCoursBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $storage = $this->entityTypeManager->getStorage('node');

    $nids = $storage->getQuery()
      ->condition('type', 'campagne')
      ->condition('status', 1)
      ->condition('promote', 1)
      ->sort('created', 'DESC')
      ->range(0, 1)
      ->accessCheck(TRUE)
      ->execute();

    if (empty($nids)) {
      return [];
    }

    /** @var \Drupal\node\NodeInterface $node */
    $node = $storage->load(reset($nids));
    if (!$node instanceof NodeInterface) {
      return [];
    }

    $view_builder = $this->entityTypeManager->getViewBuilder('node');

    // Récupération du CTA.
    $cta_label = $node->get('field_campagne_cta_label')->value ?? '';
    $cta_url   = $node->get('field_campagne_cta_url')->uri ?? $node->toUrl()->toString();
    if (!$cta_label) {
      $cta_label = $this->t('Découvrir la campagne');
    }

    // Image hero.
    $hero_render = [];
    if (!$node->get('field_campagne_image_hero')->isEmpty()) {
      $hero_render = $view_builder->viewField($node->get('field_campagne_image_hero'), [
        'label' => 'hidden',
        'type'  => 'image',
        'settings' => [
          'image_style' => 'large',
          'image_link'  => '',
        ],
      ]);
    }

    return [
      '#theme'      => 'campagne_en_cours_widget',
      '#node'       => $node,
      '#title'      => $node->label(),
      '#hero'       => $hero_render,
      '#cta_label'  => $cta_label,
      '#cta_url'    => $cta_url,
      '#cache'      => [
        'tags'     => Cache::mergeTags(['node_list:campagne'], $node->getCacheTags()),
        'contexts' => ['url.path'],
      ],
      '#attached'   => [
        'library' => ['odesign_base/campagnes'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), ['node_list:campagne']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['url.path']);
  }

}
