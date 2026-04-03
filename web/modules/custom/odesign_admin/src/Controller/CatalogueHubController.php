<?php

namespace Drupal\odesign_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\views\Views;

/**
 * Hub catalogue produits pour la gestionnaire boutique.
 *
 * Route : /admin/boutique/catalogue
 *
 * Embarque la vue Commerce Products (display 'default') + actions rapides.
 * Extensible via hook_odesign_boutique_catalogue_actions_alter().
 */
class CatalogueHubController extends ControllerBase {

  /**
   * Page hub catalogue.
   */
  public function page(): array {
    $build = [];

    // Barre d'actions rapides.
    $build['actions'] = [
      '#type'       => 'container',
      '#attributes' => ['class' => ['odesign-hub__actions']],
      'new_product' => [
        '#type'       => 'link',
        '#title'      => $this->t('Nouveau produit'),
        '#url'        => Url::fromRoute('entity.commerce_product.add_page'),
        '#attributes' => ['class' => ['button', 'button--primary', 'odesign-hub__action-btn']],
        '#prefix'     => '<i class="ph ph-plus" aria-hidden="true"></i> ',
      ],
      'all_products' => [
        '#type'       => 'link',
        '#title'      => $this->t('Voir dans Commerce'),
        '#url'        => Url::fromRoute('entity.commerce_product.collection'),
        '#attributes' => ['class' => ['button', 'odesign-hub__action-btn']],
        '#prefix'     => '<i class="ph ph-arrow-square-out" aria-hidden="true"></i> ',
      ],
    ];

    // Vue produits embarquée.
    $view = Views::getView('commerce_products');
    if ($view && $view->access('default')) {
      $view->setDisplay('default');
      $build['products_view'] = $view->buildRenderable('default', [], FALSE);
    }
    else {
      // Fallback : lien direct.
      $build['products_fallback'] = [
        '#markup' => '<p>' . $this->t('La vue produits n\'est pas disponible.') . '</p>',
      ];
    }

    $build['#cache'] = ['max-age' => 0];
    $build['#attached'] = ['library' => ['odesign_admin/hub']];

    return $build;
  }

}
