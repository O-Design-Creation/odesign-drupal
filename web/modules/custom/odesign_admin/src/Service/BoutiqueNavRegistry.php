<?php

namespace Drupal\odesign_admin\Service;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;

/**
 * Collecte et enrichit les items de navigation du back-office boutique.
 *
 * Les items sont déclarés via hook_odesign_boutique_nav_items_alter().
 *
 * Types d'items :
 *   - 'link'  (défaut) : item cliquable avec une route.
 *   - 'group' : parent déroulant ; liste ses enfants dans 'children' (array d'IDs).
 *
 * L'état actif d'un groupe est vrai si l'un de ses enfants est actif.
 */
class BoutiqueNavRegistry {

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Retourne les items de navigation prêts pour le template Twig.
   *
   * Items de type 'link' :
   *   - id, label, icon, url, is_active, badge, weight
   *
   * Items de type 'group' :
   *   - id, label, icon, is_active, weight, children (array d'items résolus)
   *
   * @return array<string, array>
   */
  public function getNavItems(): array {
    $items = [];
    $this->moduleHandler->alter('odesign_boutique_nav_items', $items);

    uasort($items, fn($a, $b) => ($a['weight'] ?? 50) <=> ($b['weight'] ?? 50));

    $active_route = $this->routeMatch->getRouteName() ?? '';

    // Première passe : résolution des URLs et états actifs pour les items de type 'link'.
    foreach ($items as $id => &$item) {
      $item['id']   = $id;
      $item['type'] = $item['type'] ?? 'link';

      if ($item['type'] === 'link') {
        try {
          $item['url'] = Url::fromRoute($item['route'])->toString();
        }
        catch (\Exception) {
          $item['url'] = '/admin';
        }

        $active_routes   = $item['active_routes'] ?? [$item['route'] ?? ''];
        $item['is_active'] = in_array($active_route, $active_routes, TRUE);
        $item['badge']   = isset($item['badge']) && $item['badge'] > 0 ? (int) $item['badge'] : NULL;
      }
    }
    unset($item);

    // Identifier les IDs réclamés par des groupes (ne remonteront pas au niveau racine).
    $claimed_ids = [];
    foreach ($items as $item) {
      if ($item['type'] === 'group') {
        foreach ($item['children'] ?? [] as $child_id) {
          $claimed_ids[$child_id] = TRUE;
        }
      }
    }

    // Deuxième passe : assembler la structure finale.
    $result = [];
    foreach ($items as $id => $item) {
      // Les enfants de groupes sont inclus dans leur parent, pas au niveau racine.
      if (isset($claimed_ids[$id])) {
        continue;
      }

      if ($item['type'] === 'group') {
        // Résoudre et trier les enfants.
        $children     = [];
        $group_active = FALSE;
        foreach ($item['children'] ?? [] as $child_id) {
          if (!isset($items[$child_id])) {
            continue;
          }
          $children[] = $items[$child_id];
          if ($items[$child_id]['is_active'] ?? FALSE) {
            $group_active = TRUE;
          }
        }
        uasort($children, fn($a, $b) => ($a['weight'] ?? 50) <=> ($b['weight'] ?? 50));
        $item['children']  = $children;
        $item['is_active'] = $group_active;
      }

      $result[$id] = $item;
    }

    return $result;
  }

}
