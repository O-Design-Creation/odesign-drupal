<?php
/**
 * configure-taxonomy-forms.php
 *
 * 1. Expose field_category sur le formulaire produit (widget autocomplete tags + autocreate)
 * 2. Crée field_tags sur les articles de blog (vocabulaire "Tags articles")
 * 3. Configure les widgets avec autocreate activé
 */

use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

// =============================================================================
// 1. PRODUIT — field_category visible + autocreate
// =============================================================================

// Activer auto_create sur le FieldConfig
$fieldConfig = FieldConfig::loadByName('commerce_product', 'default', 'field_category');
if ($fieldConfig) {
  $settings = $fieldConfig->getSettings();
  $settings['handler_settings']['auto_create'] = TRUE;
  $settings['handler_settings']['auto_create_bundle'] = 'product_category';
  $fieldConfig->setSettings($settings);
  $fieldConfig->save();
  echo "✅ field_category produit : auto_create activé\n";
}

// Configurer le form display : déplacer field_category de hidden → content
$formDisplay = \Drupal::entityTypeManager()
  ->getStorage('entity_form_display')
  ->load('commerce_product.default.default');

if ($formDisplay) {
  // Retirer de hidden
  $formDisplay->removeComponent('field_category');
  // Ajouter en mode autocomplete tags (permet création de nouveaux termes)
  $formDisplay->setComponent('field_category', [
    'type'   => 'entity_reference_autocomplete_tags',
    'weight' => 25,
    'region' => 'content',
    'settings' => [
      'match_operator'  => 'CONTAINS',
      'match_limit'     => 10,
      'size'            => 60,
      'placeholder'     => 'Saisir ou créer une catégorie…',
    ],
    'third_party_settings' => [],
  ]);
  $formDisplay->save();
  echo "✅ field_category ajouté au formulaire produit (widget autocomplete tags)\n";
}

// =============================================================================
// 2. BLOG — vocabulaire "Tags articles" + field_tags
// =============================================================================

// Créer le vocabulaire
if (!Vocabulary::load('article_tags')) {
  Vocabulary::create([
    'vid'         => 'article_tags',
    'name'        => 'Tags articles',
    'description' => 'Étiquettes thématiques pour les articles de blog.',
  ])->save();
  echo "✅ Vocabulaire 'article_tags' créé\n";
} else {
  echo "ℹ️  Vocabulaire 'article_tags' déjà existant\n";
}

// Créer le FieldStorage
if (!FieldStorageConfig::loadByName('node', 'field_tags')) {
  FieldStorageConfig::create([
    'field_name'  => 'field_tags',
    'entity_type' => 'node',
    'type'        => 'entity_reference',
    'cardinality' => -1, // illimité
    'settings'    => ['target_type' => 'taxonomy_term'],
  ])->save();
  echo "✅ FieldStorage field_tags (node) créé\n";
} else {
  echo "ℹ️  FieldStorage field_tags déjà existant\n";
}

// Attacher au bundle article avec auto_create
if (!FieldConfig::loadByName('node', 'article', 'field_tags')) {
  FieldConfig::create([
    'field_name'  => 'field_tags',
    'entity_type' => 'node',
    'bundle'      => 'article',
    'label'       => 'Tags',
    'required'    => FALSE,
    'settings'    => [
      'handler'          => 'default:taxonomy_term',
      'handler_settings' => [
        'target_bundles' => ['article_tags' => 'article_tags'],
        'auto_create'    => TRUE,
        'auto_create_bundle' => 'article_tags',
      ],
    ],
  ])->save();
  echo "✅ field_tags attaché au type 'article'\n";
} else {
  echo "ℹ️  field_tags déjà attaché\n";
}

// Ajouter field_tags au form display de l'article
$articleFormDisplay = \Drupal::entityTypeManager()
  ->getStorage('entity_form_display')
  ->load('node.article.default');

if ($articleFormDisplay) {
  $articleFormDisplay->setComponent('field_tags', [
    'type'   => 'entity_reference_autocomplete_tags',
    'weight' => 10,
    'region' => 'content',
    'settings' => [
      'match_operator'  => 'CONTAINS',
      'match_limit'     => 10,
      'size'            => 60,
      'placeholder'     => 'Ajouter des tags (séparés par des virgules)…',
    ],
    'third_party_settings' => [],
  ]);
  $articleFormDisplay->save();
  echo "✅ field_tags ajouté au formulaire article\n";
}

// =============================================================================
// 3. BLOG — field_category aussi sur les articles (optionnel mais cohérent)
// =============================================================================
// Vocabulaire commun "Catégories blog"
if (!Vocabulary::load('blog_category')) {
  Vocabulary::create([
    'vid'         => 'blog_category',
    'name'        => 'Catégories blog',
    'description' => 'Thèmes des articles de blog.',
  ])->save();
  echo "✅ Vocabulaire 'blog_category' créé\n";
}

if (!FieldConfig::loadByName('node', 'article', 'field_blog_category')) {
  // FieldStorage partagé ou dédié
  if (!FieldStorageConfig::loadByName('node', 'field_blog_category')) {
    FieldStorageConfig::create([
      'field_name'  => 'field_blog_category',
      'entity_type' => 'node',
      'type'        => 'entity_reference',
      'cardinality' => 1,
      'settings'    => ['target_type' => 'taxonomy_term'],
    ])->save();
  }
  FieldConfig::create([
    'field_name'  => 'field_blog_category',
    'entity_type' => 'node',
    'bundle'      => 'article',
    'label'       => 'Catégorie',
    'required'    => FALSE,
    'settings'    => [
      'handler'          => 'default:taxonomy_term',
      'handler_settings' => [
        'target_bundles' => ['blog_category' => 'blog_category'],
        'auto_create'    => TRUE,
        'auto_create_bundle' => 'blog_category',
      ],
    ],
  ])->save();
  echo "✅ field_blog_category attaché au type 'article'\n";

  // Widget select + autocreate sur le formulaire
  $articleFormDisplay->setComponent('field_blog_category', [
    'type'   => 'entity_reference_autocomplete',
    'weight' => 8,
    'region' => 'content',
    'settings' => [
      'match_operator'  => 'CONTAINS',
      'match_limit'     => 10,
      'size'            => 60,
      'placeholder'     => 'Saisir ou créer une catégorie…',
    ],
    'third_party_settings' => [],
  ]);
  $articleFormDisplay->save();
  echo "✅ field_blog_category ajouté au formulaire article\n";
}

echo "\n✅ Configuration taxonomie terminée.\n";
