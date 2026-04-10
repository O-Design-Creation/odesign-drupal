<?php

namespace Drupal\odesign_personalization;

use Drupal\commerce_order\Entity\OrderItemInterface;

/**
 * Helpers for reading/writing personalization payloads on order items.
 */
final class PersonalizationValues {

  /**
   * Technical key used in order_item data as resilient storage.
   */
  private const DATA_KEY = 'odesign_personalization_values';

  /**
   * Encodes personalization values to JSON.
   */
  public static function encode(array $values): string {
    return json_encode($values, JSON_UNESCAPED_UNICODE) ?: '{}';
  }

  /**
   * Decodes personalization values from JSON.
   */
  public static function decode(?string $raw): array {
    if (!is_string($raw) || trim($raw) === '') {
      return [];
    }
    $decoded = json_decode($raw, TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  /**
   * Reads values from field storage first, then from order_item data fallback.
   */
  public static function get(OrderItemInterface $orderItem): array {
    if ($orderItem->hasField('field_personalization_values') && !$orderItem->get('field_personalization_values')->isEmpty()) {
      $fromField = self::decode((string) $orderItem->get('field_personalization_values')->value);
      if ($fromField) {
        return $fromField;
      }
    }

    $data = $orderItem->getData(self::DATA_KEY);
    if (is_array($data)) {
      return $data;
    }
    if (is_string($data)) {
      return self::decode($data);
    }

    return [];
  }

  /**
   * Stores values in both canonical field (if available) and data fallback.
   */
  public static function set(OrderItemInterface $orderItem, array $values): void {
    if (!$values) {
      return;
    }
    $json = self::encode($values);
    if ($orderItem->hasField('field_personalization_values')) {
      $orderItem->set('field_personalization_values', $json);
    }
    $orderItem->setData(self::DATA_KEY, $values);
  }

  /**
   * Collects personalization values from a raw input tree.
   */
  public static function collectFromInput(array $input): array {
    $values = [];
    $walker = function (array $node) use (&$walker, &$values): void {
      foreach ($node as $key => $val) {
        if (is_array($val)) {
          $walker($val);
        }

        if (!is_string($key) || strpos($key, 'perso_') === FALSE) {
          continue;
        }

        $label = match ($key) {
          'perso_tree_slot_map' => 'Plan de pose prénoms',
          default => ucfirst(str_replace('_', ' ', substr($key, 6))),
        };
        $cleanVal = is_array($val) ? implode(', ', array_filter($val)) : trim((string) $val);
        if ($cleanVal !== '' && $cleanVal !== '_none') {
          $values[$label] = $cleanVal;
        }
      }
    };
    $walker($input);
    return $values;
  }

}
