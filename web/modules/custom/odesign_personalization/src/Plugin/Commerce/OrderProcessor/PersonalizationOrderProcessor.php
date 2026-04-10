<?php

namespace Drupal\odesign_personalization\Plugin\Commerce\OrderProcessor;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderProcessorInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\odesign_personalization\PersonalizationValues;

/**
 * Applies personalization price modifiers as Commerce Adjustments.
 *
 * Runs on every order refresh. Reads field_personalization_values from each
 * order item, loads the product's PersonalizationTemplate, and adds
 * Adjustment objects for each modifier.
 *
 * @CommerceOrderProcessor(
 *   id = "personalization_order_processor",
 *   label = @Translation("Personalization price modifiers"),
 *   priority = 100,
 * )
 */
class PersonalizationOrderProcessor implements OrderProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function process(OrderInterface $order): void {
    foreach ($order->getItems() as $orderItem) {
      // Nettoyer les ajustements personnalisation existants avant recalcul.
      foreach ($orderItem->getAdjustments() as $adj) {
        if ($adj->getType() === 'personalization') {
          $orderItem->removeAdjustment($adj);
        }
      }

      $values = PersonalizationValues::get($orderItem);
      if (empty($values)) {
        continue;
      }

      // Charger le template depuis le produit.
      $purchasedEntity = $orderItem->getPurchasedEntity();
      if (!$purchasedEntity instanceof ProductVariationInterface) {
        continue;
      }
      $product = $purchasedEntity->getProduct();
      if (!$product || !$product->hasField('field_personalization_template') || $product->get('field_personalization_template')->isEmpty()) {
        continue;
      }

      /** @var \Drupal\odesign_personalization\Entity\PersonalizationTemplate $template */
      $template = $product->get('field_personalization_template')->entity;
      if (!$template || !$template->hasPriceModifiers()) {
        continue;
      }

      $currencyCode = $orderItem->getUnitPrice()?->getCurrencyCode() ?? 'EUR';

      foreach ($template->getFields() as $field) {
        $mod = $field['price_modifier'] ?? [];
        if (empty($mod['type']) || $mod['type'] === 'none' || empty($mod['amount'])) {
          continue;
        }

        $fieldLabel = $field['label'] ?? '';
        $submitted  = NULL;
        foreach ($values as $label => $val) {
          if (mb_strtolower(trim($label)) === mb_strtolower(trim($fieldLabel))) {
            $submitted = $val;
            break;
          }
        }
        if ($submitted === NULL || $submitted === '' || $submitted === '_none') {
          continue;
        }

        $baseAmount = (string) $mod['amount'];
        $totalAmount = '0.00';

        if ($mod['type'] === 'fixed') {
          $totalAmount = $baseAmount;
        }
        elseif ($mod['type'] === 'per_unit') {
          $units = max(1, count(array_filter(array_map('trim', preg_split('/[,\n]+/', (string) $submitted)))));
          $totalAmount = bcmul($baseAmount, (string) $units, 2);
        }

        if ($totalAmount === '0.00' || $totalAmount === '0') {
          continue;
        }

        $adjLabel = !empty($mod['label']) ? $mod['label'] : ('+ ' . $totalAmount . ' €');
        $orderItem->addAdjustment(new Adjustment([
          'type'               => 'personalization',
          'label'              => $adjLabel,
          'amount'             => new Price($totalAmount, $currencyCode),
          'source_id'          => $template->id() . ':' . ($field['id'] ?? ''),
          'included'           => FALSE,
          'locked'             => FALSE,
        ]));
      }
    }
  }

}
