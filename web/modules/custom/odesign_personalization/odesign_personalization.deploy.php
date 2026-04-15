<?php

declare(strict_types=1);

use Drupal\commerce_product\Entity\Product;
use Drupal\odesign_personalization\Entity\PersonalizationTemplate;

/**
 * Assigne les modèles de personnalisation manquants sur 4 produits importés.
 *
 * Déclencheur : écarts vérifiés entre Shopify live et preprod lors de la
 * recette du 2026-04-13.
 */
function odesign_personalization_deploy_001(): void {
  $assignments = [
    'support-cartes-jeux-bois-personnalise' => 'import_d82d2bec',
    'puzzle-prenom-en-bois' => 'atelier_puzzle_prenom_motif',
    'support-bracelet-de-naissance-en-bois' => 'atelier_support_naissance_complet',
    'support-bracelet-naissance-nuage-bois' => 'atelier_support_naissance_complet',
  ];

  foreach ($assignments as $handle => $template_id) {
    odesign_personalization_assign_template_by_handle($handle, $template_id, 'deploy_001');
  }
}

/**
 * Rétablit la personnalisation simple "Prénom" sur la carte ovale.
 *
 * Déclencheur : `TCK-140` vérifié sur preprod après correction de `TCK-132`.
 */
function odesign_personalization_deploy_002(): void {
  odesign_personalization_assign_template_by_handle(
    'carte-naissance-ovale-bois',
    'import_d82d2bec',
    'deploy_002'
  );
}

/**
 * Corrige la description mojibake de la plaque de chaise.
 *
 * Déclencheur : `TCK-142` vérifié sur preprod et source catalogue locale.
 */
function odesign_personalization_deploy_003(): void {
  odesign_personalization_replace_body_text_by_handle(
    'plaque-chaise-mariage-bois',
    'Cette d��coration peut être personnalisée avec le texte de votre choix, par exemple :',
    'Cette décoration peut être personnalisée avec le texte de votre choix, par exemple :',
    'deploy_003'
  );
}

/**
 * Aligne les champs de personnalisation avec Shopify live.
 *
 * Déclencheur : `TCK-170`, complément client du 2026-04-15. La cliente
 * demande la parité des noms d'options, textes d'aide et placeholders.
 */
function odesign_personalization_deploy_004(): void {
  $json = <<<'JSON'
{"compteur-de-petanque":[{"id":"field_1","label":"Personnalisation","type":"text","required":false,"placeholder":"Ex : Papi, tu tires ou tu pointes ?","help":"Personnalisez entièrement la phrase selon vos envies.","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":0}],"magnet-photo-personnalise-bois":[{"id":"field_1","label":"Personnalisation","type":"text","required":true,"placeholder":"","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":0},{"id":"field_2","label":"Votre photo","type":"file","required":true,"placeholder":"Ajouter votre photo","help":"Votre photo sera retravaillée afin qu’elle soit adaptée et éligible à la gravure sur bois","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":1}],"porte-cle-personnalise-bois-super":[{"id":"field_1","label":"Personnalisation","type":"text","required":true,"placeholder":"ex : Mamie","help":"Texte qui apparaitra en dessous de \"super\"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":0},{"id":"field_2","label":"Prénoms","type":"text","required":true,"placeholder":"ex : Théo, Raphaël, Nathan","help":"Veuillez indiquer les prénoms, séparés par une virgule","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":1}],"cadre-jardin-fleuri-personnalise-bois":[{"id":"field_1","label":"Personnalisation","type":"text","required":true,"placeholder":"Ex : Le jardin de Mamie","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":0},{"id":"field_2","label":"Prénoms","type":"textarea","required":true,"placeholder":"ex : Marine, Juline, Pierre, Marie","help":"Veuillez indiquer 10 prénoms maximum, séparés par une virgule","maxlength":0,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":1}],"plaque-chaise-mariage-bois":[{"id":"field_1","label":"Texte 1","type":"text","required":true,"placeholder":"ex : Madame","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":0},{"id":"field_2","label":"Texte 2","type":"text","required":true,"placeholder":"ex : Couronnel","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":1},{"id":"field_3","label":"Symbole","type":"checkboxes","required":false,"placeholder":"","help":"","maxlength":80,"options":[{"value":"Coeur","label":"Coeur"},{"value":"Feuille","label":"Feuille"}],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":2}],"cadre-photo-bois-personnalise":[{"id":"field_1","label":"Texte 1","type":"text","required":true,"placeholder":"ex : Bonne fête mamie","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":0},{"id":"field_2","label":"Texte 2","type":"text","required":true,"placeholder":"ex : Choupinette & Poupette","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":1},{"id":"field_3","label":"Motifs de fleurs ( Comme sur l'image du produit )","type":"checkboxes","required":true,"placeholder":"","help":"","maxlength":80,"options":[{"value":"Oui","label":"Oui"},{"value":"Non","label":"Non"}],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":2}],"numero-de-table-en-bois-arrondi":[{"id":"field_1","label":"Nom ou numéro de la table","type":"text","required":true,"placeholder":"ex : Table des mariés","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":0},{"id":"field_2","label":"Motif de feuille","type":"checkboxes","required":true,"placeholder":"","help":"","maxlength":80,"options":[{"value":"Oui","label":"Oui"},{"value":"Non","label":"Non"}],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":1}],"magnet-d-urgence-en-bois-personnalise":[{"id":"field_1","label":"Contact d'urgence 1","type":"text","required":true,"placeholder":"ex : Maman","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":0},{"id":"field_2","label":"Numéro de téléphone","type":"text","required":true,"placeholder":"Entrez le numéro de téléphone","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":1},{"id":"field_3","label":"Contact d'urgence 2","type":"text","required":false,"placeholder":"ex : Papa","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":2},{"id":"field_4","label":"Numéro de téléphone","type":"text","required":false,"placeholder":"Entrez le numéro de téléphone","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":3},{"id":"field_5","label":"Contact d'urgence 3","type":"text","required":false,"placeholder":"ex : Papi","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":4},{"id":"field_6","label":"Numéro de téléphone","type":"text","required":false,"placeholder":"Entrez le numéro de téléphone","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":5},{"id":"field_7","label":"Contact d'urgence 4","type":"text","required":false,"placeholder":"ex : Mamie","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":6},{"id":"field_8","label":"Numéro de téléphone","type":"text","required":false,"placeholder":"Entrez le numéro de téléphone","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":7},{"id":"field_9","label":"Contact d'urgence 5","type":"text","required":false,"placeholder":"ex : Police","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":8},{"id":"field_10","label":"Numéro de téléphone","type":"text","required":false,"placeholder":"Entrez le numéro de téléphone","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":9},{"id":"field_11","label":"Contact d'urgence 6","type":"text","required":false,"placeholder":"ex : Pompier","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":10},{"id":"field_12","label":"Numéro de téléphone","type":"text","required":false,"placeholder":"Entrez le numéro de téléphone","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":11},{"id":"field_13","label":"Adresse","type":"text","required":true,"placeholder":"Entrez votre adresse exacte","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":12}],"coeur-bois-personnalise-prenoms":[{"id":"field_1","label":"Personnalisation du cœur","type":"text","required":true,"placeholder":"ex : Mamie d'amour","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":0},{"id":"field_2","label":"Nombre de ligne","type":"text","required":true,"placeholder":"Please select","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":1},{"id":"field_3","label":"Prénom(s) ligne 1","type":"textarea","required":true,"placeholder":"ex : Sarah, Julien","help":"Si vous avez choisi une seule ligne, veuillez indiquer tous les prénoms dans ce champ. En revanche, si vous avez sélectionné deux ou trois lignes, merci de renseigner ici uniquement les prénoms destinés à la ligne de gauche.","maxlength":0,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":2},{"id":"field_4","label":"Prénom(s) ligne 2","type":"textarea","required":false,"placeholder":"ex : Marine, Léo, Séréna","help":"Merci de remplir ce champ uniquement si vous avez sélectionné deux ou trois lignes, puisqu’il correspond à la ligne du milieu de votre création personnalisée.","maxlength":0,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":3},{"id":"field_5","label":"Prénom(s) ligne 3","type":"textarea","required":false,"placeholder":"ex : Paolo, Suzane","help":"Merci de remplir ce champ uniquement si vous avez sélectionné trois lignes, car il correspond à la ligne de droite de votre création.","maxlength":0,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":4}],"marque-places-bois-personnalises":[{"id":"field_1","label":"Quantité","type":"text","required":true,"placeholder":"","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":0},{"id":"field_2","label":"Prénoms","type":"textarea","required":false,"placeholder":"ex : Lisa, Célia, Quentin, Séverine","help":"Veuillez entrer le même nombre de prénoms que la quantité sélectionnée et les séparer par une virgule","maxlength":0,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":1}],"pancartes-jeu-des-maries-bois":[{"id":"field_1","label":"Duo de pancartes","type":"checkboxes","required":true,"placeholder":"","help":"","maxlength":80,"options":[{"value":"Elle & Lui","label":"Elle & Lui"},{"value":"Elle & Elle","label":"Elle & Elle"},{"value":"Lui & Lui","label":"Lui & Lui"}],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":0},{"id":"field_2","label":"Modèle","type":"checkboxes","required":false,"placeholder":"","help":"","maxlength":80,"options":[{"value":"Voyage","label":"Voyage"},{"value":"Feuille","label":"Feuille"}],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":1}],"magnet-coeur-bois-personnalise":[{"id":"field_1","label":"Texte 1","type":"text","required":true,"placeholder":"ex : Papa","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":0},{"id":"field_2","label":"Texte 2","type":"text","required":true,"placeholder":"ex : d'amour","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":1}],"vase-compliments-bois-personnalise":[{"id":"field_1","label":"Personnalisation","type":"text","required":true,"placeholder":"Ma nounou","help":"Remplir ce champ avec la personnalisation du destinataire, par exemple, le prénom de la personne ou son surnom.","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":0},{"id":"field_2","label":"Adjectifs","type":"text","required":false,"placeholder":"ex : Bienveillante, Mature, Drôle, Géniale, Intelligente, Belle, Sympa, Forte","help":"Remplissez ce champ uniquement si vous souhaitez modifier les adjectifs présents dans les cœurs ; merci d’indiquer exactement 8 adjectifs et de les séparer par une virgule.","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":1}],"maillot-foot-bois-personnalise":[{"id":"field_1","label":"Prénom","type":"text","required":true,"placeholder":"","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":0},{"id":"field_2","label":"Numéro","type":"text","required":true,"placeholder":"","help":"Indiquez ici le numéro qui apparaîtra sur le maillot.","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":1}],"porte-alliances-en-bois-personnalise":[{"id":"field_1","label":"Prénoms des mariés","type":"text","required":true,"placeholder":"","help":"Entrez les prénoms des deux mariés","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":0},{"id":"field_2","label":"Date du mariage","type":"text","required":true,"placeholder":"Select a date","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":1}],"vase-fleurs-bois-personnalise":[{"id":"field_1","label":"Texte 1","type":"text","required":true,"placeholder":"ex : Bonne fête mamie","help":"Entrez le texte qui apparaîtra au-dessus du coeur.","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":0},{"id":"field_2","label":"Texte 2","type":"text","required":true,"placeholder":"ex : Victor","help":"Entrez le texte qui apparaîtra en dessous du coeur.","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":1}],"magnet-vase-fleuri-personnalise":[{"id":"field_1","label":"Texte 1","type":"text","required":false,"placeholder":"ex : Lya","help":"Entrez le texte qui apparaîtra au-dessus du vase.","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":0},{"id":"field_2","label":"Texte 2","type":"text","required":false,"placeholder":"ex : Je t'aime mamie d'amour","help":"Entrez le texte qui apparaîtra au-dessus du vase.","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":1}],"arbre-de-vie-en-bois-facon-scrabble":[{"id":"field_1","label":"Texte","type":"text","required":true,"placeholder":"ex : Le bonheur de Mamie ","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":0},{"id":"field_2","label":"Prénoms","type":"textarea","required":true,"placeholder":"Ex: Hugo, Melyo, Meliah, Pauline, Gabriel, Mael","help":"Remplissez ce champ avec les prénoms qui seront réalisés sous forme de Scrabble (8 prénoms maximum) et veillez à bien séparer chaque prénom par une virgule.","maxlength":0,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":1}],"calendrier-grave-en-bois-personnalise":[{"id":"field_1","label":"Prénom(s)","type":"text","required":true,"placeholder":"","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":0},{"id":"field_2","label":"Date","type":"text","required":true,"placeholder":"Select a date","help":"","maxlength":80,"options":[],"group_max":5,"price_modifier":{"type":"none","amount":"0","label":""},"weight":1}]}
JSON;

  $templates_by_handle = json_decode($json, TRUE, 512, JSON_THROW_ON_ERROR);
  foreach ($templates_by_handle as $handle => $fields) {
    $template_id = 'tck170_' . substr(md5((string) $handle), 0, 8);
    odesign_personalization_upsert_template(
      $template_id,
      odesign_personalization_template_label_from_fields($fields),
      'Aligné sur Shopify live par TCK-170 (labels, aides, placeholders).',
      $fields,
      'deploy_004'
    );
    odesign_personalization_assign_template_by_handle((string) $handle, $template_id, 'deploy_004');
  }
}

/**
 * Résout un produit commerce à partir de son alias Shopify /products/{handle}.
 */
function odesign_personalization_load_product_by_handle(string $handle): ?Product {
  $alias = '/products/' . trim($handle);

  $paths = \Drupal::database()->select('path_alias', 'pa')
    ->fields('pa', ['path'])
    ->condition('alias', $alias)
    ->execute()
    ->fetchCol();

  foreach ($paths as $path) {
    if (preg_match('#^/product/(\d+)$#', (string) $path, $matches)) {
      $product = Product::load((int) $matches[1]);
      if ($product) {
        return $product;
      }
    }
  }

  return NULL;
}

/**
 * Affecte un modèle à un produit résolu par handle Shopify.
 */
function odesign_personalization_assign_template_by_handle(string $handle, string $template_id, string $context): void {
  $template_storage = \Drupal::entityTypeManager()->getStorage('personalization_template');
  $template = $template_storage->load($template_id);
  if (!$template) {
    throw new \RuntimeException(sprintf('Template de personnalisation introuvable: %s', $template_id));
  }

  $product = odesign_personalization_load_product_by_handle($handle);
  if (!$product) {
    throw new \RuntimeException(sprintf('Produit introuvable via le handle Shopify: %s', $handle));
  }

  if (!$product->hasField('field_personalization_template')) {
    throw new \RuntimeException(sprintf('Le produit %s (%s) ne porte pas field_personalization_template.', (string) $product->label(), $handle));
  }

  $current_template_id = $product->get('field_personalization_template')->target_id;
  if ($current_template_id === $template_id) {
    return;
  }

  $product->set('field_personalization_template', ['target_id' => $template_id]);
  $product->save();

  \Drupal::logger('odesign_personalization')->notice(
    '@context : modèle @template affecté au produit @product via @handle (avant : @before).',
    [
      '@context' => $context,
      '@template' => $template_id,
      '@product' => (string) $product->label(),
      '@handle' => $handle,
      '@before' => $current_template_id ?: 'none',
    ]
  );
}

/**
 * Crée ou met à jour un modèle de personnalisation.
 */
function odesign_personalization_upsert_template(string $template_id, string $label, string $description, array $fields, string $context): void {
  $storage = \Drupal::entityTypeManager()->getStorage('personalization_template');
  $template = $storage->load($template_id);

  if (!$template instanceof PersonalizationTemplate) {
    $template = PersonalizationTemplate::create([
      'id' => $template_id,
      'status' => TRUE,
    ]);
  }

  $template->set('label', $label);
  $template->set('description', $description);
  $template->set('fields', $fields);
  $template->save();

  \Drupal::logger('odesign_personalization')->notice(
    '@context : modèle @template créé ou mis à jour.',
    [
      '@context' => $context,
      '@template' => $template_id,
    ]
  );
}

/**
 * Génère un libellé court depuis les champs d'un modèle.
 */
function odesign_personalization_template_label_from_fields(array $fields): string {
  $labels = [];
  foreach ($fields as $field) {
    $label = trim((string) ($field['label'] ?? ''));
    if ($label !== '') {
      $labels[] = $label;
    }
  }
  $label = implode(' + ', $labels);
  return mb_strlen($label) > 60 ? mb_substr($label, 0, 57) . '...' : $label;
}

/**
 * Remplace un extrait exact dans le body d'un produit résolu par handle Shopify.
 */
function odesign_personalization_replace_body_text_by_handle(string $handle, string $search, string $replace, string $context): void {
  $product = odesign_personalization_load_product_by_handle($handle);
  if (!$product) {
    throw new \RuntimeException(sprintf('Produit introuvable via le handle Shopify: %s', $handle));
  }

  if (!$product->hasField('body')) {
    throw new \RuntimeException(sprintf('Le produit %s (%s) ne porte pas le champ body.', (string) $product->label(), $handle));
  }

  $body = $product->get('body')->first();
  $current_value = (string) ($body?->value ?? '');
  if (!str_contains($current_value, $search)) {
    return;
  }

  $product->set('body', [
    'value' => str_replace($search, $replace, $current_value),
    'summary' => (string) ($body?->summary ?? ''),
    'format' => (string) ($body?->format ?: 'full_html'),
  ]);
  $product->save();

  \Drupal::logger('odesign_personalization')->notice(
    '@context : body corrigé pour le produit @product via @handle.',
    [
      '@context' => $context,
      '@product' => (string) $product->label(),
      '@handle' => $handle,
    ]
  );
}
