<?php

/**
 * @file
 * Hooks fournis par odesign_admin.
 *
 * Ces hooks permettent à n'importe quel module d'étendre le back-office
 * gestionnaire boutique sans modifier odesign_admin directement.
 */

/**
 * Déclare ou modifie les liens de navigation du back-office boutique.
 *
 * Appelé par BoutiqueNavRegistry::getNavItems() lors de chaque rendu admin.
 *
 * @param array &$items
 *   Tableau associatif d'items indexé par machine name. Chaque item :
 *   - label         : string — libellé FR affiché dans la nav (court, ≤ 12 car.)
 *   - icon          : string — classe Phosphor sans le préfixe "ph", ex. "ph-receipt"
 *   - route         : string — nom de route Drupal (destination du lien)
 *   - weight        : int   — ordre d'affichage (croissant de gauche à droite)
 *   - active_routes : string[] — routes pour lesquelles cet item est considéré actif
 *   - badge         : int|null — nombre à afficher dans le badge (null = pas de badge)
 *
 * @see \Drupal\odesign_admin\Service\BoutiqueNavRegistry
 */
function hook_odesign_boutique_nav_items_alter(array &$items): void {
  $items['my_section'] = [
    'label'         => 'Ma section',
    'icon'          => 'ph-star',
    'route'         => 'my_module.my_route',
    'weight'        => 50,
    'active_routes' => [
      'my_module.my_route',
      'my_module.my_detail_route',
    ],
    'badge'         => NULL,
  ];
}

/**
 * Déclare ou modifie les tuiles de statistiques du tableau de bord.
 *
 * Appelé par DashboardController::page() lors du rendu du dashboard.
 *
 * @param array &$cards
 *   Tableau associatif de cards indexé par machine name. Chaque card :
 *   - title     : string  — libellé de la statistique
 *   - value     : int     — valeur numérique affichée
 *   - url       : string  — URL vers laquelle la card est cliquable
 *   - icon      : string  — classe Phosphor, ex. "ph-receipt"
 *   - highlight : bool    — true = fond coloré accentué (urgence)
 *   - weight    : int     — ordre d'affichage
 *
 * @see \Drupal\odesign_admin\Controller\DashboardController
 */
function hook_odesign_boutique_dashboard_cards_alter(array &$cards): void {
  $cards['my_stat'] = [
    'title'     => 'Mon indicateur',
    'value'     => 42,
    'url'       => '/admin/my-section',
    'icon'      => 'ph-star',
    'highlight' => FALSE,
    'weight'    => 50,
  ];
}

/**
 * Déclare ou modifie les sections dans le hub de configuration boutique.
 *
 * Appelé par ConfigHubController (Lot 3 — non encore implémenté).
 *
 * @param array &$sections
 *   Tableau associatif de sections indexé par machine name. Chaque section :
 *   - title           : string   — titre de la card de configuration
 *   - description     : string   — description courte
 *   - route           : string   — route vers le formulaire de configuration
 *   - icon            : string   — classe Phosphor
 *   - status_callback : callable|null — retourne 'ok'|'warning'|'error'
 *   - weight          : int
 */
function hook_odesign_boutique_config_sections_alter(array &$sections): void {
  $sections['my_config'] = [
    'title'           => 'Ma configuration',
    'description'     => 'Gérez les paramètres de mon module.',
    'route'           => 'my_module.config',
    'icon'            => 'ph-sliders',
    'status_callback' => NULL,
    'weight'          => 50,
  ];
}
