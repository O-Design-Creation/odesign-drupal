<?php

namespace Drupal\odesign_admin\Config;

use Drupal\Core\Form\FormStateInterface;

/**
 * Section "Promotions volume" (remises dégressives par lot, low-code).
 *
 * Permet de configurer jusqu'à 3 règles de promotion volume sans coder.
 * Chaque règle cible un univers (taxonomy) ou une liste de produits.
 */
class PromotionsVolumeConfigSection implements ConfigSectionInterface {

  /** Nombre de règles configurables dans l'interface. */
  private const MAX_RULES = 3;

  public function getId(): string {
    return 'promotions_volume';
  }

  public function getTitle(): string {
    return 'Promotions volume';
  }

  public function getWeight(): int {
    return 60;
  }

  public function buildForm(array &$tab, FormStateInterface $form_state): void {
    $settings        = \Drupal::configFactory()->get('odesign_admin.settings');
    $promotion_rules = $this->normalizePromotionRules((array) ($settings->get('promotion_volume_rules') ?? []));

    $tab['promotions_volume_help'] = [
      '#markup' => '<div class="config-help">'
        . '<strong>À quoi ça sert ?</strong> Créez des remises dégressives par lot sans code '
        . '(ex : -3 € dès 3 articles, -5 € dès 5). Vous pouvez cibler un univers (taxonomy) ou une liste de produits.'
        . '</div>',
    ];

    for ($i = 0; $i < self::MAX_RULES; $i++) {
      $rule = $promotion_rules[$i] ?? [
        'enabled'    => FALSE,
        'label'      => '',
        'scope'      => 'life_occasion',
        'target_ids' => [],
        'tiers'      => [],
        'priority'   => 0,
        'stop_further' => FALSE,
      ];
      $default_target_values = $this->buildPromotionTargetDefaults(
        (string) ($rule['scope'] ?? 'life_occasion'),
        (array) ($rule['target_ids'] ?? [])
      );

      $tab["promotion_rule_{$i}"] = [
        '#type'  => 'details',
        '#title' => 'Règle volume #' . ($i + 1),
        '#open'  => ($i === 0),
      ];

      $tab["promotion_rule_{$i}"]["promotion_rule_{$i}_enabled"] = [
        '#type'          => 'checkbox',
        '#title'         => 'Activer cette règle',
        '#default_value' => !empty($rule['enabled']),
      ];

      $tab["promotion_rule_{$i}"]["promotion_rule_{$i}_label"] = [
        '#type'          => 'textfield',
        '#title'         => 'Nom commercial de la règle',
        '#default_value' => (string) ($rule['label'] ?? ''),
        '#placeholder'   => 'Ex : Offre lot pancartes',
        '#maxlength'     => 160,
      ];

      $tab["promotion_rule_{$i}"]["promotion_rule_{$i}_scope"] = [
        '#type'          => 'select',
        '#title'         => 'Cible',
        '#options'       => [
          'life_occasion' => 'Univers',
          'product'       => 'Produits',
        ],
        '#default_value' => (string) ($rule['scope'] ?? 'life_occasion'),
      ];

      $tab["promotion_rule_{$i}"]["promotion_rule_{$i}_target_terms"] = [
        '#type'               => 'entity_autocomplete',
        '#title'              => 'Univers ciblés',
        '#target_type'        => 'taxonomy_term',
        '#selection_settings' => ['target_bundles' => ['life_occasion' => 'life_occasion']],
        '#tags'               => TRUE,
        '#default_value'      => (string) ($rule['scope'] ?? 'life_occasion') === 'life_occasion' ? $default_target_values : [],
        '#description'        => 'Sélectionnez un ou plusieurs univers.',
        '#states'             => [
          'visible' => [
            ':input[name="promotion_rule_' . $i . '_scope"]' => ['value' => 'life_occasion'],
          ],
        ],
      ];

      $tab["promotion_rule_{$i}"]["promotion_rule_{$i}_target_products"] = [
        '#type'          => 'entity_autocomplete',
        '#title'         => 'Produits ciblés',
        '#target_type'   => 'commerce_product',
        '#tags'          => TRUE,
        '#default_value' => (string) ($rule['scope'] ?? 'life_occasion') === 'product' ? $default_target_values : [],
        '#description'   => 'Sélectionnez un ou plusieurs produits.',
        '#states'        => [
          'visible' => [
            ':input[name="promotion_rule_' . $i . '_scope"]' => ['value' => 'product'],
          ],
        ],
      ];

      $tab["promotion_rule_{$i}"]["promotion_rule_{$i}_tiers"] = [
        '#type'          => 'textarea',
        '#title'         => 'Paliers (une ligne = quantité:remise)',
        '#default_value' => $this->tiersToLines((array) ($rule['tiers'] ?? [])),
        '#description'   => "Format : quantité:remise. Exemple :\n3:3\n5:5\n10:10",
        '#rows'          => 4,
      ];

      $tab["promotion_rule_{$i}"]["promotion_rule_{$i}_priority"] = [
        '#type'          => 'number',
        '#title'         => 'Priorité (plus grand = appliqué en premier)',
        '#default_value' => (int) ($rule['priority'] ?? 0),
      ];

      $tab["promotion_rule_{$i}"]["promotion_rule_{$i}_stop_further"] = [
        '#type'          => 'checkbox',
        '#title'         => 'Arrêter après cette règle (bloquer les suivantes)',
        '#default_value' => !empty($rule['stop_further']),
      ];
    }
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    for ($i = 0; $i < self::MAX_RULES; $i++) {
      if (!(bool) $form_state->getValue("promotion_rule_{$i}_enabled")) {
        continue;
      }
      $label  = trim((string) $form_state->getValue("promotion_rule_{$i}_label"));
      $scope  = (string) $form_state->getValue("promotion_rule_{$i}_scope");
      $targets = $this->extractTargetIdsFromFormState($form_state, $i, $scope);
      $tiers_raw = trim((string) $form_state->getValue("promotion_rule_{$i}_tiers"));
      $errors = $this->validateTierLines($tiers_raw);

      if ($label === '') {
        $form_state->setErrorByName("promotion_rule_{$i}_label", 'Le nom commercial est obligatoire pour une règle active.');
      }
      if (!$targets) {
        $target_field = $scope === 'product' ? "promotion_rule_{$i}_target_products" : "promotion_rule_{$i}_target_terms";
        $form_state->setErrorByName($target_field, 'Sélectionnez au moins une cible.');
      }
      if ($tiers_raw === '') {
        $form_state->setErrorByName("promotion_rule_{$i}_tiers", 'Ajoutez des paliers valides au format quantité:remise (ex : 3:3).');
      }
      elseif ($errors) {
        $form_state->setErrorByName("promotion_rule_{$i}_tiers", $errors[0]);
      }
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $settings        = \Drupal::configFactory()->getEditable('odesign_admin.settings');
    $promotion_rules = [];

    for ($i = 0; $i < self::MAX_RULES; $i++) {
      $enabled    = (bool) $form_state->getValue("promotion_rule_{$i}_enabled");
      $label      = trim((string) $form_state->getValue("promotion_rule_{$i}_label"));
      $scope      = (string) $form_state->getValue("promotion_rule_{$i}_scope");
      $target_ids = $this->extractTargetIdsFromFormState($form_state, $i, $scope);
      $tiers_raw  = trim((string) $form_state->getValue("promotion_rule_{$i}_tiers"));
      $priority   = (int) $form_state->getValue("promotion_rule_{$i}_priority");
      $stop       = (bool) $form_state->getValue("promotion_rule_{$i}_stop_further");

      if (!$enabled || $label === '' || !$target_ids) {
        continue;
      }

      $tiers = $this->parseTierLines($tiers_raw);
      if (!$tiers) {
        continue;
      }

      $promotion_rules[] = [
        'enabled'      => TRUE,
        'label'        => $label,
        'scope'        => in_array($scope, ['life_occasion', 'product'], TRUE) ? $scope : 'life_occasion',
        'target_ids'   => $target_ids,
        'tiers'        => $tiers,
        'priority'     => $priority,
        'stop_further' => $stop,
      ];
    }

    $settings->set('promotion_volume_rules', $promotion_rules)->save();
  }

  // ─── Helpers ────────────────────────────────────────────────────────────────

  private function normalizePromotionRules(array $rules): array {
    $normalized = [];
    foreach ($rules as $rule) {
      if (!is_array($rule)) {
        continue;
      }
      $scope = (string) ($rule['scope'] ?? 'life_occasion');
      $scope = in_array($scope, ['life_occasion', 'product'], TRUE) ? $scope : 'life_occasion';
      $normalized[] = [
        'enabled'      => !empty($rule['enabled']),
        'label'        => trim((string) ($rule['label'] ?? '')),
        'scope'        => $scope,
        'target_ids'   => array_values(array_filter(array_map('intval', (array) ($rule['target_ids'] ?? [])))),
        'tiers'        => $this->parseTierLines($this->tiersToLines((array) ($rule['tiers'] ?? []))),
        'priority'     => (int) ($rule['priority'] ?? 0),
        'stop_further' => !empty($rule['stop_further']),
      ];
    }
    return $normalized;
  }

  private function tiersToLines(array $tiers): string {
    $lines = [];
    foreach ($tiers as $tier) {
      if (!is_array($tier)) {
        continue;
      }
      $threshold = (int) ($tier['threshold'] ?? 0);
      $discount  = (float) ($tier['discount'] ?? 0);
      if ($threshold > 0 && $discount > 0) {
        $lines[] = $threshold . ':' . rtrim(rtrim(number_format($discount, 2, '.', ''), '0'), '.');
      }
    }
    return implode("\n", $lines);
  }

  private function parseTierLines(string $input): array {
    $tiers = [];
    foreach (preg_split('/\R+/', $input) ?: [] as $line) {
      $line = trim($line);
      if ($line === '' || !str_contains($line, ':')) {
        continue;
      }
      [$threshold_raw, $discount_raw] = array_map('trim', explode(':', $line, 2));
      $threshold = (int) $threshold_raw;
      $discount  = (float) str_replace(',', '.', $discount_raw);
      if ($threshold > 0 && $discount > 0) {
        $tiers[] = ['threshold' => $threshold, 'discount' => $discount];
      }
    }
    usort($tiers, static fn(array $a, array $b): int => $a['threshold'] <=> $b['threshold']);
    return $tiers;
  }

  /**
   * @return array<int, string>
   */
  private function validateTierLines(string $input): array {
    $errors          = [];
    $seen_thresholds = [];
    foreach (preg_split('/\R+/', $input) ?: [] as $index => $line) {
      $line = trim($line);
      if ($line === '') {
        continue;
      }
      $line_number = $index + 1;
      if (!str_contains($line, ':')) {
        $errors[] = "Ligne {$line_number} invalide : utilisez le format quantité:remise (ex : 3:3).";
        continue;
      }
      [$threshold_raw, $discount_raw] = array_map('trim', explode(':', $line, 2));
      if (!is_numeric($threshold_raw) || !is_numeric(str_replace(',', '.', $discount_raw))) {
        $errors[] = "Ligne {$line_number} invalide : quantité et remise doivent être numériques.";
        continue;
      }
      $threshold = (int) $threshold_raw;
      $discount  = (float) str_replace(',', '.', $discount_raw);
      if ($threshold <= 0 || $discount <= 0) {
        $errors[] = "Ligne {$line_number} invalide : quantité et remise doivent être supérieures à 0.";
        continue;
      }
      if (in_array($threshold, $seen_thresholds, TRUE)) {
        $errors[] = "Ligne {$line_number} invalide : la quantité {$threshold} est déjà utilisée.";
        continue;
      }
      $seen_thresholds[] = $threshold;
    }
    return $errors;
  }

  private function extractTargetIdsFromFormState(FormStateInterface $form_state, int $rule_index, string $scope): array {
    $field_name = $scope === 'product'
      ? "promotion_rule_{$rule_index}_target_products"
      : "promotion_rule_{$rule_index}_target_terms";
    $raw_values = $form_state->getValue($field_name);
    if (!is_array($raw_values)) {
      return [];
    }
    $ids = [];
    foreach ($raw_values as $value) {
      if (is_array($value) && isset($value['target_id'])) {
        $ids[] = (int) $value['target_id'];
      }
      elseif (is_scalar($value)) {
        $ids[] = (int) $value;
      }
    }
    return array_values(array_filter($ids));
  }

  private function buildPromotionTargetDefaults(string $scope, array $target_ids): array {
    $ids = array_values(array_filter(array_map('intval', $target_ids)));
    if (!$ids) {
      return [];
    }
    if ($scope === 'product') {
      $storage = \Drupal::entityTypeManager()->getStorage('commerce_product');
      return array_values($storage->loadMultiple($ids));
    }
    $storage  = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $entities = array_values($storage->loadMultiple($ids));
    return array_values(array_filter($entities, static fn($term): bool =>
      method_exists($term, 'bundle') && $term->bundle() === 'life_occasion'
    ));
  }

}
