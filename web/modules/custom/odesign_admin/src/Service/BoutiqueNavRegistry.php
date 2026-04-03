<?php

namespace Drupal\odesign_admin\Service;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;

/**
 * Collecte et enrichit les items de navigation du back-office boutique.
 *
 * Les items sont déclarés via hook_odesign_boutique_nav_items_alter().
 * L'état actif est résolu à partir des active_routes[] déclarés par chaque item
 * (convention D-03 : déclaratif, pas de magie par comparaison de préfixe d'URL).
 */
class BoutiqueNavRegistry {

  public function __construct(
    protected ModuleHandlerInterface $moduleHandler,
    protected RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Retourne les items de navigation prêts pour le template Twig.
   *
   * Chaque item contient :
   *   - id        : string (machine name)
   *   - label     : string (FR)
   *   - icon      : string (classe Phosphor, ex. "ph-receipt")
   *   - url       : string (URL résolue)
   *   - is_active : bool
   *   - badge     : int|null (null = pas de badge)
   *   - weight    : int
   *
   * @return array<string, array>
   */
  public function getNavItems(): array {
    $items = [];
    $this->moduleHandler->alter('odesign_boutique_nav_items', $items);

    uasort($items, fn($a, $b) => ($a['weight'] ?? 50) <=> ($b['weight'] ?? 50));

    $active_route = $this->routeMatch->getRouteName() ?? '';

    foreach ($items as $id => &$item) {
      $item['id'] = $id;

      // Résolution de l'URL de destination.
      try {
        $item['url'] = Url::fromRoute($item['route'])->toString();
      }
      catch (\Exception) {
        $item['url'] = '/admin';
      }

      // État actif : chaque item déclare les routes pour lesquelles il est actif.
      // Si active_routes n'est pas déclaré, seule la route de destination est utilisée.
      $active_routes = $item['active_routes'] ?? [$item['route'] ?? ''];
      $item['is_active'] = in_array($active_route, $active_routes, TRUE);

      // Badge : calculé directement par le module déclarant dans le hook.
      // Valeur NULL = pas de badge. Valeur int > 0 = badge visible.
      $item['badge'] = isset($item['badge']) && $item['badge'] > 0 ? (int) $item['badge'] : NULL;
    }
    unset($item);

    return $items;
  }

}
