<?php

namespace Drupal\odesign_promotions\OrderProcessor;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderProcessorInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductInterface;

/**
 * Applique des remises volume configurées depuis le back-office.
 */
class VolumeRulesOrderProcessor implements OrderProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function process(OrderInterface $order) {
    $currency_code = NULL;
    foreach ($order->getItems() as $order_item) {
      $unit_price = $order_item->getUnitPrice();
      if ($unit_price) {
        $currency_code = $unit_price->getCurrencyCode();
        break;
      }
      $purchased_entity = $order_item->getPurchasedEntity();
      if ($purchased_entity && method_exists($purchased_entity, 'getPrice') && $purchased_entity->getPrice()) {
        $currency_code = $purchased_entity->getPrice()->getCurrencyCode();
        break;
      }
    }
    if (!$currency_code) {
      return;
    }

    $rules = $this->loadRules();
    if (!$rules) {
      return;
    }

    $items = [];
    foreach ($order->getItems() as $order_item) {
      $purchased_entity = $order_item->getPurchasedEntity();
      $product = $purchased_entity?->getProduct();
      if (!$product instanceof ProductInterface) {
        continue;
      }

      $term_ids = [];
      if ($product->hasField('field_life_occasion') && !$product->get('field_life_occasion')->isEmpty()) {
        foreach ($product->get('field_life_occasion')->referencedEntities() as $term) {
          $term_ids[] = (int) $term->id();
        }
      }

      $unit_price = $order_item->getUnitPrice();
      if (!$unit_price && $purchased_entity && method_exists($purchased_entity, 'getPrice')) {
        $unit_price = $purchased_entity->getPrice();
      }
      if (!$unit_price || $unit_price->getCurrencyCode() !== $currency_code) {
        continue;
      }

      $items[] = [
        'product_id' => (int) $product->id(),
        'term_ids' => $term_ids,
        'quantity' => (int) round((float) $order_item->getQuantity()),
        'unit_price' => (float) $unit_price->getNumber(),
      ];
    }

    foreach ($rules as $rule) {
      $eligible_quantity = 0;
      $eligible_subtotal = 0.0;

      foreach ($items as $item) {
        if (!$this->itemMatchesRule($item, $rule)) {
          continue;
        }
        $eligible_quantity += $item['quantity'];
        $eligible_subtotal += $item['unit_price'] * $item['quantity'];
      }

      $tier = $this->resolveTier($rule['tiers'], $eligible_quantity);
      if (!$tier) {
        continue;
      }

      $discount_total = min($eligible_subtotal, (float) $tier['discount']);
      if ($discount_total <= 0) {
        continue;
      }

      $order->addAdjustment(new Adjustment([
        'type' => 'promotion',
        'label' => $rule['label'] . ' (-' . rtrim(rtrim(number_format((float) $tier['discount'], 2, '.', ''), '0'), '.') . '€ panier)',
        'amount' => new Price('-' . number_format($discount_total, 2, '.', ''), $currency_code),
        'source_id' => 'odesign_promotions:volume_rule:' . substr(hash('sha256', $rule['label'] . '|' . json_encode($rule['target_ids'])), 0, 16),
        'included' => FALSE,
      ]));

      if (!empty($rule['stop_further'])) {
        break;
      }
    }
  }

  /**
   * Retourne les règles actives triées par priorité décroissante.
   *
   * @return array<int, array<string, mixed>>
   *   Règles prêtes au calcul.
   */
  private function loadRules(): array {
    $settings = \Drupal::config('odesign_admin.settings');
    $rules = $settings->get('promotion_volume_rules') ?? [];
    if (!is_array($rules)) {
      return [];
    }

    $normalized = [];
    foreach ($rules as $rule) {
      if (!is_array($rule) || empty($rule['enabled'])) {
        continue;
      }
      $scope = (string) ($rule['scope'] ?? 'life_occasion');
      if (!in_array($scope, ['life_occasion', 'product'], TRUE)) {
        continue;
      }
      $target_ids = array_values(array_filter(array_map('intval', (array) ($rule['target_ids'] ?? []))));
      $tiers = [];
      foreach ((array) ($rule['tiers'] ?? []) as $tier) {
        $threshold = (int) ($tier['threshold'] ?? 0);
        $discount = (float) ($tier['discount'] ?? 0);
        if ($threshold > 0 && $discount > 0) {
          $tiers[] = ['threshold' => $threshold, 'discount' => $discount];
        }
      }
      if (!$target_ids || !$tiers) {
        continue;
      }

      usort($tiers, static fn(array $a, array $b): int => $a['threshold'] <=> $b['threshold']);
      $normalized[] = [
        'label' => trim((string) ($rule['label'] ?? 'Offre volume')),
        'scope' => $scope,
        'target_ids' => $target_ids,
        'tiers' => $tiers,
        'priority' => (int) ($rule['priority'] ?? 0),
        'stop_further' => !empty($rule['stop_further']),
      ];
    }

    usort($normalized, static fn(array $a, array $b): int => $b['priority'] <=> $a['priority']);
    return $normalized;
  }

  /**
   * Vérifie l'éligibilité d'un item à une règle.
   *
   * @param array<string, mixed> $item
   *   Item normalisé.
   * @param array<string, mixed> $rule
   *   Règle normalisée.
   */
  private function itemMatchesRule(array $item, array $rule): bool {
    if ($rule['scope'] === 'product') {
      return in_array((int) $item['product_id'], $rule['target_ids'], TRUE);
    }
    return count(array_intersect($item['term_ids'], $rule['target_ids'])) > 0;
  }

  /**
   * Retourne le palier applicable pour la quantité donnée.
   *
   * @param array<int, array{threshold:int, discount:float}> $tiers
   *   Paliers.
   * @param int $quantity
   *   Quantité.
   *
   * @return array{threshold:int, discount:float}|null
   *   Palier retenu ou NULL.
   */
  private function resolveTier(array $tiers, int $quantity): ?array {
    $selected = NULL;
    foreach ($tiers as $tier) {
      if ($quantity >= $tier['threshold']) {
        $selected = $tier;
      }
    }
    return $selected;
  }

}

