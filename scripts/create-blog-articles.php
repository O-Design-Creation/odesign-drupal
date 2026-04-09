<?php
/**
 * Créer 3 articles de blog de démonstration.
 * Usage: drush php:script scripts/create-blog-articles.php
 */

use Drupal\node\Entity\Node;

$articles = [
  [
    'title' => 'Comment personnaliser une carte de naissance en bois',
    'body' => '<p>La <strong>carte de naissance en bois personnalisée</strong> est l\'un de nos créations les plus appréciées chez O'Design Création. Fabriquée à la main en France à partir de contreplaqué de peuplier 3 mm, elle constitue un souvenir précieux pour immortaliser les premiers instants de votre enfant.</p>

<h2>Choisir le bon design</h2>
<p>Nous proposons plusieurs formes : ovale, rectangulaire, et en forme de prénom. Chaque pièce est découpée et gravée au laser avec soin. La gravure peut inclure le prénom du bébé, sa date et heure de naissance, son poids et sa taille.</p>

<h2>Les options de personnalisation</h2>
<ul>
<li>Prénom et date de naissance</li>
<li>Poids et taille à la naissance</li>
<li>Message personnalisé des parents</li>
<li>Ajout d\'une photo gravée</li>
</ul>

<h2>Délai de fabrication</h2>
<p>Chaque pièce est fabriquée sur commande. Comptez 3 à 5 jours ouvrés pour la fabrication, puis 2 à 3 jours pour la livraison. Nous proposons également une option livraison express en 24h.</p>',
    'summary' => 'Découvrez comment personnaliser une carte de naissance en bois gravée au laser, fabriquée à la main en France chez O'Design Création.',
  ],
  [
    'title' => 'Idées cadeaux de naissance originaux en bois',
    'body' => '<p>Vous cherchez un <strong>cadeau de naissance original</strong> qui sortira du lot ? Chez O'Design Création, nous créons des souvenirs uniques en bois gravé, fabriqués à la main en France. Voici notre sélection d\'idées pour marquer ce moment précieux.</p>

<h2>Le magnet photo personnalisé</h2>
<p>Un petit format délicat, parfait comme cadeau de naissance. Le magnet en bois de peuplier 3 mm est gravé avec le prénom et la date de naissance du bébé. Il peut orner le réfrigérateur ou un tableau magnétique dans la chambre.</p>

<h2>La pancarte décorative</h2>
<p>La pancarte "Bienvenue" personnalisée avec le prénom du bébé est idéale pour décorer la chambre. Elle peut être suspendue au mur ou posée sur une étagère.</p>

<h2>Le support bracelet de naissance</h2>
<p>Un cadre en bois spécialement conçu pour conserver le bracelet hospitalier du bébé. Un souvenir émouvant que les parents chérissent longtemps.</p>

<h2>Pourquoi choisir le bois ?</h2>
<p>Le bois est un matériau naturel, chaleureux et durable. La gravure laser y est particulièrement précise, permettant de reproduire des détails fins. C\'est aussi un choix éco-responsable, avec des approvisionnements locaux.</p>',
    'summary' => 'Sélection d\'idées cadeaux de naissance originaux en bois gravé, fabriqués à la main en France par O'Design Création.',
  ],
  [
    'title' => 'Nos techniques de gravure laser sur bois',
    'body' => '<p>La <strong>gravure laser sur bois</strong> est au cœur de notre savoir-faire chez O'Design Création. Cette technique de précision nous permet de créer des décors fins et durables sur tous nos produits. Voici comment nous travaillons.</p>

<h2>Le matériau : contreplaqué de peuplier</h2>
<p>Nous utilisons principalement du contreplaqué de peuplier de 3 mm. Ce matériau offre un excellent rapport légèreté/solidité et permet une gravure nette avec un beau contraste. Le veinage naturel du bois apporte une touche chaleureuse unique à chaque pièce.</p>

<h2>La technologie laser CO2</h2>
<p>Notre machine laser CO2 vaporise précisément la surface du bois pour créer la gravure. La profondeur et l\'intensité de la gravure sont paramétrables, permettant d\'obtenir des rendus allant du beige clair au brun foncé.</p>

<h2>La découpe laser</h2>
<p>En plus de la gravure, nous utilisons le laser pour découper les formes de nos créations. C\'est ce qui permet d\'obtenir des contours précis pour nos cartes de naissance ovales, nos pancartes et nos supports.</p>

<h2>La finition</h2>
<p>Après gravure et découpe, chaque pièce est soigneusement poncée et nettoyée. Sur demande, nous proposons une finition à l\'huile naturelle qui protège le bois et accentue le contraste de la gravure.</p>',
    'summary' => 'Découvrez les techniques de gravure laser sur bois utilisées par O'Design Création pour créer des pièces uniques et durables.',
  ],
];

foreach ($articles as $i => $data) {
  $existing = \Drupal::entityTypeManager()
    ->getStorage('node')
    ->loadByProperties([
      'type' => 'article',
      'title' => $data['title'],
    ]);

  $node = $existing ? reset($existing) : Node::create([
    'type' => 'article',
    'title' => $data['title'],
    'uid' => 1,
  ]);

  $node->set('body', [
    'value' => $data['body'],
    'summary' => $data['summary'],
    'format' => 'full_html',
  ]);
  $node->set('status', 1);
  $node->setNewRevision(FALSE);
  $node->save();

  $action = $existing ? 'mis à jour' : 'créé';
  echo "Article " . ($i + 1) . " $action : nid=" . $node->id() . " — " . $data['title'] . "\n";
}
echo "Done.\n";
