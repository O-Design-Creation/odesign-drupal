<?php
/**
 * @file
 * Setup : types de contenu, format de texte, pages légales, blog.
 *
 * Usage : ddev drush php-script scripts/setup-content-types.php
 *         terminus drush atelierodesign.dev -- php-script scripts/setup-content-types.php
 */

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;

use Drupal\filter\Entity\FilterFormat;
use Drupal\node\Entity\NodeType;
use Drupal\node\Entity\Node;

/**
 * Ajoute le champ body à un node type (Drupal 11.3+ — node_add_body_field dépréciée).
 */
function odesign_add_body_field(string $bundle): void {
  // S'assurer que le field storage 'body' existe.
  $field_storage = FieldStorageConfig::loadByName('node', 'body');
  if (!$field_storage) {
    $field_storage = FieldStorageConfig::create([
      'field_name'   => 'body',
      'entity_type'  => 'node',
      'type'         => 'text_with_summary',
      'cardinality'  => 1,
      'translatable' => TRUE,
    ]);
    $field_storage->save();
  }

  // Créer la field instance si absente.
  $field = FieldConfig::loadByName('node', $bundle, 'body');
  if (!$field) {
    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle'        => $bundle,
      'label'         => 'Contenu',
      'settings'      => ['display_summary' => TRUE, 'allowed_formats' => []],
    ]);
    $field->save();
  }

  // Form display.
  $form_display = EntityFormDisplay::load("node.{$bundle}.default");
  if (!$form_display) {
    $form_display = EntityFormDisplay::create(['targetEntityType' => 'node', 'bundle' => $bundle, 'mode' => 'default', 'status' => TRUE]);
  }
  $form_display->setComponent('body', ['type' => 'text_textarea_with_summary', 'weight' => 1]);
  $form_display->save();

  // View display.
  $view_display = EntityViewDisplay::load("node.{$bundle}.default");
  if (!$view_display) {
    $view_display = EntityViewDisplay::create(['targetEntityType' => 'node', 'bundle' => $bundle, 'mode' => 'default', 'status' => TRUE]);
  }
  $view_display->setComponent('body', ['type' => 'text_default', 'weight' => 1, 'label' => 'hidden']);
  $view_display->save();
}

// ---------------------------------------------------------------------------
// 1. Format de texte basic_html
// ---------------------------------------------------------------------------
$storage = \Drupal::entityTypeManager()->getStorage('filter_format');
if (!$storage->load('basic_html')) {
  $format = FilterFormat::create([
    'format'  => 'basic_html',
    'name'    => 'HTML de base',
    'weight'  => 1,
    'filters' => [
      'filter_html' => [
        'status'   => 1,
        'settings' => [
          'allowed_html'        => '<a href hreflang> <em> <strong> <cite> <blockquote cite> <code> <ul type> <ol start type> <li> <dl> <dt> <dd> <h2 id> <h3 id> <h4 id> <h5 id> <h6 id> <p> <br> <span> <img src alt height width> <div> <table> <thead> <tbody> <tr> <th> <td>',
          'filter_html_help'    => 1,
          'filter_html_nofollow' => 0,
        ],
      ],
      'filter_autop'       => ['status' => 1],
      'filter_htmlcorrector' => ['status' => 1],
    ],
  ]);
  $format->save();
  echo "✓ Format basic_html créé\n";
}
else {
  echo "SKIP : basic_html existe déjà\n";
}

// ---------------------------------------------------------------------------
// 2. Node type "page" (CGV, mentions légales)
// ---------------------------------------------------------------------------
$node_type_storage = \Drupal::entityTypeManager()->getStorage('node_type');

if (!$node_type_storage->load('page')) {
  $page_type = NodeType::create([
    'type'              => 'page',
    'name'              => 'Page',
    'description'       => 'Pages de contenu statiques (CGV, mentions légales, politique de confidentialité).',
    'new_revision'      => TRUE,
    'display_submitted' => FALSE,
  ]);
  $page_type->save();
  odesign_add_body_field('page');
  echo "✓ Node type 'page' créé\n";
}
else {
  echo "SKIP : node type 'page' existe déjà\n";
}

// ---------------------------------------------------------------------------
// 3. Node type "article" (blog ADR-005)
// ---------------------------------------------------------------------------
if (!$node_type_storage->load('article')) {
  $article_type = NodeType::create([
    'type'              => 'article',
    'name'              => 'Article',
    'description'       => 'Articles de blog pour le journal Atelier O Design (ADR-005).',
    'new_revision'      => TRUE,
    'display_submitted' => TRUE,
  ]);
  $article_type->save();
  odesign_add_body_field('article');
  echo "✓ Node type 'article' créé\n";
}
else {
  echo "SKIP : node type 'article' existe déjà\n";
}

// ---------------------------------------------------------------------------
// 4. Pages légales (CGV + Mentions légales)
// ---------------------------------------------------------------------------
$node_storage = \Drupal::entityTypeManager()->getStorage('node');

$pages_legales = [
  [
    'title'  => 'Conditions Générales de Vente',
    'alias'  => '/cgv',
    'body'   => '<h2>Conditions Générales de Vente</h2>
<p>Les présentes conditions générales de vente (CGV) régissent les relations contractuelles entre Atelier O Design et ses clients pour toute commande passée sur le site atelierodesign.fr.</p>

<h3>1. Objet</h3>
<p>Les présentes CGV définissent les droits et obligations des parties dans le cadre de la vente en ligne de produits personnalisés proposés par Atelier O Design.</p>

<h3>2. Produits</h3>
<p>Les produits proposés sont décrits avec la plus grande précision possible. Les photographies et visuels sont fournis à titre indicatif. Les créations personnalisées sont réalisées sur commande.</p>

<h3>3. Prix</h3>
<p>Les prix sont indiqués en euros TTC. Atelier O Design se réserve le droit de modifier ses prix à tout moment, étant entendu que le prix figurant au catalogue le jour de la commande sera le seul applicable.</p>

<h3>4. Commande</h3>
<p>Toute commande vaut acceptation des prix et descriptifs des produits disponibles à la vente. En cas de litige, l\'acheteur devra apporter la preuve de son paiement.</p>

<h3>5. Livraison</h3>
<p>Les délais de livraison indiqués sont donnés à titre indicatif. La livraison standard est à 4,90 € en France métropolitaine. La livraison est offerte pour toute commande égale ou supérieure à 50 €.</p>

<h3>6. Droit de rétractation</h3>
<p>Conformément à l\'article L221-18 du Code de la consommation, vous disposez d\'un délai de 14 jours pour exercer votre droit de rétractation. Ce droit ne s\'applique pas aux produits personnalisés selon l\'article L221-28.</p>

<h3>7. Contact</h3>
<p>Pour toute question : odesigncrea@gmail.com</p>
<p><em>Document à compléter avec les informations juridiques complètes de l\'entreprise avant mise en ligne.</em></p>',
  ],
  [
    'title'  => 'Mentions Légales',
    'alias'  => '/mentions-legales',
    'body'   => '<h2>Mentions Légales</h2>
<p>Conformément aux dispositions des articles 6-III et 19 de la Loi n° 2004-575 du 21 juin 2004 pour la Confiance dans l\'économie numérique, dite L.C.E.N., les présentes mentions légales sont portées à la connaissance des utilisateurs et visiteurs du site atelierodesign.fr.</p>

<h3>Éditeur du site</h3>
<p>Atelier O Design<br>
Email : odesigncrea@gmail.com<br>
<em>(À compléter : forme juridique, SIRET, adresse, capital social)</em></p>

<h3>Hébergement</h3>
<p>Ce site est hébergé par Pantheon Systems, Inc.<br>
717 California Street, San Francisco, CA 94108, États-Unis</p>

<h3>Propriété intellectuelle</h3>
<p>L\'ensemble du contenu de ce site (textes, images, vidéos, créations) est la propriété exclusive d\'Atelier O Design. Toute reproduction, distribution ou utilisation sans autorisation préalable est interdite.</p>

<h3>Données personnelles</h3>
<p>Conformément au RGPD et à la loi Informatique et Libertés, vous disposez d\'un droit d\'accès, de rectification et de suppression des données vous concernant. Pour exercer ce droit : odesigncrea@gmail.com</p>

<h3>Cookies</h3>
<p>Ce site utilise des cookies nécessaires à son fonctionnement. Votre consentement est demandé pour les cookies non essentiels.</p>',
  ],
  [
    'title'  => 'Politique de Confidentialité',
    'alias'  => '/politique-confidentialite',
    'body'   => '<h2>Politique de Confidentialité</h2>
<p>Atelier O Design s\'engage à protéger votre vie privée et vos données personnelles conformément au Règlement Général sur la Protection des Données (RGPD) et à la loi Informatique et Libertés.</p>

<h3>Données collectées</h3>
<p>Lors d\'une commande : nom, prénom, adresse de livraison, adresse email, numéro de téléphone. Ces données sont nécessaires au traitement et à la livraison de votre commande.</p>

<h3>Finalités du traitement</h3>
<p>Traitement des commandes, gestion de la relation client, envoi de confirmations et notifications liées à votre commande, respect des obligations légales.</p>

<h3>Conservation des données</h3>
<p>Vos données sont conservées pendant la durée nécessaire aux finalités pour lesquelles elles ont été collectées, et au maximum 3 ans après votre dernière interaction.</p>

<h3>Vos droits</h3>
<p>Vous disposez d\'un droit d\'accès, de rectification, d\'effacement, de limitation et de portabilité de vos données. Pour exercer ces droits : odesigncrea@gmail.com</p>',
  ],
];

foreach ($pages_legales as $page_data) {
  $existing = $node_storage->loadByProperties(['title' => $page_data['title'], 'type' => 'page']);
  if (!empty($existing)) {
    echo "SKIP : page '" . $page_data['title'] . "' existe déjà\n";
    continue;
  }
  $node = Node::create([
    'type'   => 'page',
    'title'  => $page_data['title'],
    'body'   => ['value' => $page_data['body'], 'format' => 'basic_html'],
    'status' => 1,
    'path'   => [['alias' => $page_data['alias']]],
  ]);
  $node->save();
  echo "✓ Page '" . $page_data['title'] . "' créée (alias=" . $page_data['alias'] . ", id=" . $node->id() . ")\n";
}

echo "\nSetup terminé.\n";
