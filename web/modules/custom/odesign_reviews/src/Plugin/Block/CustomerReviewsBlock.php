<?php

namespace Drupal\odesign_reviews\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\odesign_reviews\Service\ReviewManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Affiche les avis clients validés sur la homepage.
 *
 * @Block(
 *   id = "odesign_customer_reviews",
 *   admin_label = @Translation("Avis clients (homepage)"),
 *   category = @Translation("O'Design Création")
 * )
 */
class CustomerReviewsBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly ReviewManager $reviewManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('odesign_reviews.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $reviews = $this->reviewManager->getApproved(3);
    if (empty($reviews)) {
      return [];
    }

    return [
      '#theme' => 'odesign_reviews_block',
      '#reviews' => $reviews,
      '#eyebrow' => $this->t('Ils nous ont fait confiance'),
      '#title' => $this->t('Avis clients'),
      '#variant' => 'home',
      '#attached' => [
        'library' => ['odesign_reviews/frontend'],
      ],
      '#cache' => [
        'max-age' => 300,
        'tags' => ['odesign_reviews:list'],
      ],
    ];
  }

}
