<?php

namespace Drupal\odesign_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\views\Views;

/**
 * Hub éditorial (articles + univers) pour la gestionnaire boutique.
 *
 * Route : /admin/boutique/editorial
 *
 * Deux sections :
 * - Articles de blog : vue `content` (display 'default') + "Nouvel article"
 * - Univers (taxonomy life_occasion) : liste des termes avec liens édition
 *   + lien "Voir sur le site /occasions/{slug}"
 */
class EditorialHubController extends ControllerBase {

  /**
   * Page hub éditorial.
   */
  public function page(): array {
    $build = [];

    // ── Section Articles ────────────────────────────────────────────────────
    $build['articles_section'] = [
      '#type'       => 'container',
      '#attributes' => ['class' => ['odesign-hub__section']],
    ];

    $build['articles_section']['header'] = [
      '#type'       => 'container',
      '#attributes' => ['class' => ['odesign-hub__section-header']],
      'title'       => ['#markup' => '<h2 class="odesign-hub__section-title"><i class="ph ph-article" aria-hidden="true"></i> ' . $this->t('Articles de blog') . '</h2>'],
      'action'      => [
        '#type'       => 'link',
        '#title'      => $this->t('Nouvel article'),
        '#url'        => Url::fromRoute('node.add', ['node_type' => 'article']),
        '#attributes' => ['class' => ['button', 'button--primary', 'odesign-hub__action-btn']],
        '#prefix'     => '<i class="ph ph-plus" aria-hidden="true"></i> ',
      ],
    ];

    $articles_view = Views::getView('content');
    if ($articles_view && $articles_view->access('default')) {
      $articles_view->setDisplay('default');
      $build['articles_section']['view'] = $articles_view->buildRenderable('default', [], FALSE);
    }
    else {
      $build['articles_section']['fallback'] = [
        '#type'       => 'link',
        '#title'      => $this->t('Gérer les articles'),
        '#url'        => Url::fromRoute('system.admin_content'),
        '#attributes' => ['class' => ['button']],
      ];
    }

    // ── Section Univers (life_occasion) ─────────────────────────────────────
    $build['univers_section'] = [
      '#type'       => 'container',
      '#attributes' => ['class' => ['odesign-hub__section', 'odesign-hub__section--univers']],
    ];

    $build['univers_section']['header'] = [
      '#type'       => 'container',
      '#attributes' => ['class' => ['odesign-hub__section-header']],
      'title'       => ['#markup' => '<h2 class="odesign-hub__section-title"><i class="ph ph-globe" aria-hidden="true"></i> ' . $this->t('Univers de la boutique') . '</h2>'],
      'action'      => [
        '#type'       => 'link',
        '#title'      => $this->t('Gérer les univers'),
        '#url'        => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => 'life_occasion']),
        '#attributes' => ['class' => ['button', 'odesign-hub__action-btn']],
      ],
    ];

    // Liste des termes life_occasion avec actions.
    $terms = $this->entityTypeManager()
      ->getStorage('taxonomy_term')
      ->loadByProperties(['vid' => 'life_occasion']);

    uasort($terms, fn($a, $b) => $a->getWeight() <=> $b->getWeight());

    $rows = [];
    foreach ($terms as $term) {
      // Résoudre l'alias de chemin du terme si disponible.
      $term_url = Url::fromRoute('entity.taxonomy_term.canonical', ['taxonomy_term' => $term->id()]);

      $rows[] = [
        '#type'       => 'container',
        '#attributes' => ['class' => ['odesign-hub__univers-row']],
        'name'        => ['#markup' => '<span class="odesign-hub__univers-name">' . htmlspecialchars($term->label()) . '</span>'],
        'edit'        => [
          '#type'       => 'link',
          '#title'      => $this->t('Modifier'),
          '#url'        => Url::fromRoute('entity.taxonomy_term.edit_form', ['taxonomy_term' => $term->id()]),
          '#attributes' => ['class' => ['button', 'button--small']],
        ],
        'view'        => [
          '#type'       => 'link',
          '#title'      => $this->t('Voir sur le site'),
          '#url'        => $term_url,
          '#attributes' => ['class' => ['button', 'button--small', 'button--secondary'], 'target' => '_blank', 'rel' => 'noopener noreferrer'],
          '#prefix'     => '<i class="ph ph-arrow-square-out" aria-hidden="true"></i> ',
        ],
      ];
    }

    $build['univers_section']['terms'] = [
      '#type'       => 'container',
      '#attributes' => ['class' => ['odesign-hub__univers-list']],
      'rows'        => $rows,
    ];

    $build['#cache']    = ['max-age' => 0];
    $build['#attached'] = ['library' => ['odesign_admin/hub']];

    return $build;
  }

}
