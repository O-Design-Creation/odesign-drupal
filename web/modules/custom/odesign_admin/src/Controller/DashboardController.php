<?php

namespace Drupal\odesign_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tableau de bord du gestionnaire boutique.
 */
class DashboardController extends ControllerBase {

  protected Connection $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'));
  }

  /**
   * Page principale du tableau de bord.
   */
  public function page(): array {
    $build = [];

    // Statistiques rapides.
    $stats = $this->getStats();

    // Vue des commandes récentes.
    $orders_view = NULL;
    $view = Views::getView('commerce_orders');
    if ($view && $view->access('default')) {
      $view->setDisplay('default');
      $view->setItemsPerPage(10);
      $view->execute();
      $orders_view = $view->buildRenderable('default', [], FALSE);
    }

    $build['dashboard'] = [
      '#theme' => 'odesign_admin_dashboard',
      '#stats' => $stats,
      '#pending_orders_url' => Url::fromRoute('entity.commerce_order.collection', [], [
        'query' => ['state' => ['pending', 'processing']],
      ])->toString(),
      '#orders_url' => Url::fromRoute('entity.commerce_order.collection')->toString(),
      '#orders_view' => $orders_view,
      '#recent_articles' => $this->getRecentArticles(),
      '#quick_links' => $this->getQuickLinks(),
      '#cache' => ['max-age' => 0],
      '#attached' => [
        'html_head' => [
          [
            [
              '#type' => 'html_tag',
              '#tag' => 'link',
              '#attributes' => [
                'rel' => 'stylesheet',
                'href' => '/modules/custom/odesign_admin/css/dashboard.css',
                'media' => 'all',
              ],
            ],
            'odesign_admin_dashboard_css',
          ],
        ],
      ],
    ];

    return $build;
  }

  /**
   * Guide marketing opérationnel pour la boutique.
   */
  public function marketingGuide(): array {
    $resource_links = [
      [
        'title' => 'Gérer les univers',
        'description' => 'Modifier les pages Mariage, Naissance et cadeaux personnalisés.',
        'url' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => 'life_occasion'])->toString(),
      ],
      [
        'title' => 'Mes produits',
        'description' => 'Mettre en avant les produits à pousser en ce moment.',
        'url' => Url::fromRoute('entity.commerce_product.collection')->toString(),
      ],
      [
        'title' => 'Gérer le blog',
        'description' => 'Publier un article utile pour un temps fort ou une catégorie.',
        'url' => Url::fromRoute('system.admin_content')->toString(),
      ],
      [
        'title' => 'Ajouter une image',
        'description' => 'Mettre en ligne une photo produit ou une photo atelier.',
        'url' => Url::fromRoute('entity.media.add_form', ['media_type' => 'product_image'])->toString(),
      ],
      [
        'title' => 'Configuration boutique',
        'description' => 'Vérifier les informations de contact, de paiement et d’expédition.',
        'url' => Url::fromRoute('odesign_admin.config')->toString(),
      ],
    ];

    $sections = [
      [
        'eyebrow' => 'Priorités du moment',
        'title' => 'Les 3 familles à mettre en avant',
        'intro' => 'Le site doit d’abord aider les visiteurs à trouver rapidement l’occasion qui leur ressemble.',
        'items' => [
          'Mariage : c’est la famille la plus naturelle à pousser en premier avec les cadeaux, la décoration et les commandes personnalisées.',
          'Naissance et bébé : c’est une famille rassurante, émotionnelle et facile à offrir.',
          'Cadeaux personnalisés, famille et maison : elle permet de toucher un public plus large toute l’année.',
        ],
      ],
      [
        'eyebrow' => 'Ce que le site doit faire',
        'title' => '3 missions simples',
        'intro' => 'Chaque page importante doit aider le visiteur à comprendre vite ce que vous faites et pourquoi il peut vous faire confiance.',
        'items' => [
          'Montrer des créations personnalisées claires, belles et faciles à comprendre.',
          'Rassurer avec des avis, des photos réelles, des délais expliqués et la preuve que vous avez un vrai atelier.',
          'Orienter vers les bonnes catégories et les bons produits sans noyer la personne sous trop de choix.',
        ],
      ],
      [
        'eyebrow' => 'Actions concrètes',
        'title' => 'Ce que vous pouvez faire chaque semaine',
        'intro' => 'Le but n’est pas de tout faire, mais d’avancer régulièrement avec quelques actions simples et utiles.',
        'items' => [
          'Choisir 1 produit à mettre en avant sur la page d’accueil et dans vos publications.',
          'Ajouter ou améliorer 3 photos sur un produit important.',
          'Demander 1 ou 2 avis à des clients satisfaits.',
          'Vérifier qu’une catégorie importante a bien une introduction claire et rassurante.',
          'Publier un contenu simple lié à un temps fort : mariage, naissance, fête des mères, Noël.',
        ],
      ],
      [
        'eyebrow' => 'Photos et vidéos',
        'title' => 'Les bons réflexes visuels',
        'intro' => 'Les visuels servent à vendre, mais aussi à rassurer. Ils doivent montrer la qualité, la matière et le soin.',
        'items' => [
          'Photographier les produits sur fond propre, à la lumière du jour, avec un plan large et un détail rapproché.',
          'Montrer aussi l’atelier, la machine, la préparation ou l’emballage pour prouver le travail réel.',
          'Réutiliser les mêmes bons visuels sur le site, Google et les réseaux sociaux au lieu de refaire du contenu partout.',
        ],
      ],
      [
        'eyebrow' => 'Avis et confiance',
        'title' => 'Ce qui rassure le plus',
        'intro' => 'Les visiteurs doivent sentir qu’ils commandent à une vraie personne soigneuse, pas à une boutique anonyme.',
        'items' => [
          'Afficher les meilleurs avis sur la page d’accueil et sur les catégories importantes.',
          'Faire apparaître clairement que vous avez un atelier physique et un vrai savoir-faire.',
          'Montrer des exemples de personnalisation terminée pour aider les gens à se projeter.',
        ],
      ],
      [
        'eyebrow' => 'Temps forts',
        'title' => 'Les moments à préparer en avance',
        'intro' => 'Certains temps forts peuvent devenir des rendez-vous réguliers pour la boutique.',
        'items' => [
          'Mariage : cadeaux témoins, décoration, pancartes, petits détails personnalisés.',
          'Naissance et bébé : cadeaux de naissance, décoration de chambre, souvenirs.',
          'Fête des mères, fête des pères, maîtresse ou nounou, Noël : préparer une petite sélection dédiée chaque année.',
        ],
      ],
      [
        'eyebrow' => 'Après achat',
        'title' => 'Ne pas perdre le lien',
        'intro' => 'Un client satisfait peut revenir ou recommander la boutique si vous gardez un contact simple et naturel.',
        'items' => [
          'Envoyer un message simple pour remercier et demander un avis.',
          'Réutiliser les commandes réussies pour montrer votre savoir-faire avec l’accord du client.',
          'Proposer plus tard une autre idée cadeau liée à un moment de vie proche.',
        ],
      ],
      [
        'eyebrow' => 'Développement local',
        'title' => 'Petites actions locales utiles',
        'intro' => 'Le local peut nourrir les ventes atelier et les ventes en ligne en même temps.',
        'items' => [
          'Soigner la fiche Google avec de nouvelles photos et des avis réguliers.',
          'Créer quelques liens avec des partenaires locaux : mariage, cadeaux, événements, décoration.',
          'Tester de petites commandes groupées ou séries limitées sans alourdir l’atelier.',
        ],
      ],
    ];

    $sections_markup = '';
    foreach ($sections as $section) {
      $items_markup = '';
      foreach ($section['items'] as $item) {
        $items_markup .= '<li>' . $item . '</li>';
      }

      $sections_markup .= '<article class="odesign-marketing-guide__card">'
        . '<p class="odesign-marketing-guide__eyebrow">' . $section['eyebrow'] . '</p>'
        . '<h2>' . $section['title'] . '</h2>'
        . '<p class="odesign-marketing-guide__intro">' . $section['intro'] . '</p>'
        . '<ul>' . $items_markup . '</ul>'
        . '</article>';
    }

    $resources_markup = '';
    foreach ($resource_links as $link) {
      $resources_markup .= '<a class="odesign-marketing-guide__resource" href="' . $link['url'] . '">'
        . '<span class="odesign-marketing-guide__resource-title">' . $link['title'] . '</span>'
        . '<span class="odesign-marketing-guide__resource-desc">' . $link['description'] . '</span>'
        . '</a>';
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['odesign-dashboard', 'odesign-marketing-guide']],
      'hero' => [
        '#markup' => '
          <section class="odesign-marketing-guide__hero">
            <div class="odesign-marketing-guide__hero-copy">
              <p class="odesign-marketing-guide__hero-label">Guide marketing boutique</p>
              <h1>Une feuille de route simple pour faire grandir la boutique</h1>
              <p>
                Cette page résume la stratégie marketing utile au quotidien. Elle aide à savoir
                quoi montrer, quoi améliorer et quelles actions lancer sans se disperser.
              </p>
            </div>
            <div class="odesign-marketing-guide__hero-note">
              <strong>À retenir</strong>
              <p>Le site doit vendre, rassurer et faire rayonner l’atelier. Les actions les plus utiles sont souvent les plus simples : belles photos, bons avis, catégories claires, produits bien choisis.</p>
            </div>
          </section>',
      ],
      'sections' => [
        '#markup' => '<section class="odesign-marketing-guide__grid">' . $sections_markup . '</section>',
      ],
      'resources' => [
        '#markup' => '
          <section class="odesign-marketing-guide__resources-wrap">
            <div class="odesign-marketing-guide__resources-head">
              <p class="odesign-marketing-guide__eyebrow">Accès utiles</p>
              <h2>Les pages à utiliser le plus souvent</h2>
              <p>Ces raccourcis permettent de passer de la stratégie à l’action.</p>
            </div>
            <div class="odesign-marketing-guide__resources">' . $resources_markup . '</div>
          </section>',
      ],
      'footer' => [
        '#markup' => '
          <section class="odesign-marketing-guide__footer">
            <p>Ce guide est une version opérationnelle du document stratégique du projet. Il sert surtout à garder le cap dans la gestion quotidienne de la boutique.</p>
          </section>',
      ],
      '#cache' => ['max-age' => 0],
      '#attached' => [
        'html_head' => [
          [
            [
              '#type' => 'html_tag',
              '#tag' => 'link',
              '#attributes' => [
                'rel' => 'stylesheet',
                'href' => '/modules/custom/odesign_admin/css/dashboard.css',
                'media' => 'all',
              ],
            ],
            'odesign_admin_dashboard_css',
          ],
        ],
      ],
    ];
  }

  /**
   * Statistiques clés pour les compteurs du dashboard.
   */
  protected function getStats(): array {
    $stats = [];

    // Commandes en attente de traitement.
    try {
      $pending = $this->database->select('commerce_order', 'o')
        ->condition('o.state', ['pending', 'processing'], 'IN')
        ->countQuery()->execute()->fetchField();
      $stats['pending_orders'] = (int) $pending;
    }
    catch (\Exception $e) {
      $stats['pending_orders'] = 0;
    }

    // Total commandes.
    try {
      $total_orders = $this->database->select('commerce_order', 'o')
        ->condition('o.state', 'draft', '<>')
        ->countQuery()->execute()->fetchField();
      $stats['total_orders'] = (int) $total_orders;
    }
    catch (\Exception $e) {
      $stats['total_orders'] = 0;
    }

    // Produits publiés.
    try {
      $products = $this->database->select('commerce_product_field_data', 'p')
        ->condition('p.status', 1)
        ->countQuery()->execute()->fetchField();
      $stats['total_products'] = (int) $products;
    }
    catch (\Exception $e) {
      $stats['total_products'] = 0;
    }

    // Articles de blog publiés.
    try {
      $articles = $this->database->select('node_field_data', 'n')
        ->condition('n.type', 'article')
        ->condition('n.status', 1)
        ->countQuery()->execute()->fetchField();
      $stats['total_articles'] = (int) $articles;
    }
    catch (\Exception $e) {
      $stats['total_articles'] = 0;
    }

    return $stats;
  }

  /**
   * Récupère les 5 articles de blog les plus récents.
   */
  protected function getRecentArticles(): array {
    $articles = [];
    try {
      $results = $this->database->select('node_field_data', 'n')
        ->fields('n', ['nid', 'title', 'status', 'changed'])
        ->condition('n.type', 'article')
        ->orderBy('n.changed', 'DESC')
        ->range(0, 5)
        ->execute()
        ->fetchAll();

      foreach ($results as $row) {
        $articles[] = [
          'title'    => $row->title,
          'status'   => (bool) $row->status,
          'date'     => date('d/m/Y', $row->changed),
          'edit_url' => '/node/' . $row->nid . '/edit',
          'view_url' => '/node/' . $row->nid,
        ];
      }
    }
    catch (\Exception $e) {
      // Silently fail if table doesn't exist.
    }
    return $articles;
  }

  /**
   * Raccourcis vers les actions fréquentes.
   */
  protected function getQuickLinks(): array {
    $links = [
      [
        'title'       => 'Promotions volume (simple)',
        'route_name'  => 'odesign_admin.config',
        'route_options' => ['fragment' => 'edit-promotions-volume'],
        'description' => 'Régler rapidement les offres type lots (-3€, -5€, -10€)',
        'icon'        => 'percent',
      ],
      [
        'title'       => 'Gérer les univers',
        'route_name'  => 'entity.taxonomy_vocabulary.overview_form',
        'route_params' => ['taxonomy_vocabulary' => 'life_occasion'],
        'description' => 'Modifier les univers visibles côté boutique',
        'icon'        => 'category',
      ],
      [
        'title'       => 'Créer un produit',
        'route_name'  => 'entity.commerce_product.add_form',
        'route_params' => ['commerce_product_type' => 'default'],
        'description' => 'Ajouter un nouveau produit à votre boutique',
        'icon'        => 'add_circle',
      ],
      [
        'title'       => 'Écrire un article',
        'route_name'  => 'node.add',
        'route_params' => ['node_type' => 'article'],
        'description' => 'Rédiger un nouvel article de blog',
        'icon'        => 'edit_note',
      ],
      [
        'title'       => 'Commandes à traiter',
        'route_name'  => 'entity.commerce_order.collection',
        'route_options' => ['query' => ['state' => ['pending', 'processing']]],
        'description' => 'Voir directement les commandes en attente / préparation',
        'icon'        => 'pending_actions',
      ],
      [
        'title'       => 'Toutes les commandes',
        'route_name'  => 'entity.commerce_order.collection',
        'description' => 'Gérer et suivre vos commandes',
        'icon'        => 'receipt_long',
      ],
      [
        'title'       => 'Expéditions',
        'route_name'  => 'entity.commerce_shipping_method.collection',
        'description' => 'Gérer les modes de livraison',
        'icon'        => 'local_shipping',
      ],
      [
        'title'       => 'Factures',
        'route_name'  => 'view.commerce_invoices.page_1',
        'description' => 'Consulter et télécharger les factures',
        'icon'        => 'description',
      ],
      [
        'title'       => 'Mes clients',
        'route_name'  => 'entity.user.collection',
        'description' => 'Consulter les comptes clients',
        'icon'        => 'group',
      ],
      [
        'title'       => 'Mes produits',
        'route_name'  => 'entity.commerce_product.collection',
        'description' => 'Modifier votre catalogue',
        'icon'        => 'inventory_2',
      ],
      [
        'title'       => 'Gérer le blog',
        'route_name'  => 'system.admin_content',
        'description' => 'Voir et modifier vos articles',
        'icon'        => 'article',
      ],
      [
        'title'       => 'Promotions',
        'route_name'  => 'entity.commerce_promotion.collection',
        'description' => 'Créer et gérer vos codes promo',
        'icon'        => 'local_offer',
      ],
      [
        'title'       => 'Ajouter une image',
        'route_name'  => 'entity.media.add_form',
        'route_params' => ['media_type' => 'product_image'],
        'description' => 'Télécharger une photo produit',
        'icon'        => 'add_photo_alternate',
      ],
      [
        'title'       => 'Configuration boutique',
        'route_name'  => 'odesign_admin.config',
        'description' => 'Mailgun, Stripe, informations boutique',
        'icon'        => 'settings',
      ],
      [
        'title'       => 'Guide marketing',
        'route_name'  => 'odesign_admin.marketing_guide',
        'description' => 'Retrouver les priorités et actions utiles pour la boutique',
        'icon'        => 'campaign',
      ],
    ];

    $account = $this->currentUser();
    $access_manager = \Drupal::service('access_manager');
    $quick_links = [];

    foreach ($links as $link) {
      $route_name = $link['route_name'];
      $route_params = $link['route_params'] ?? [];
      $route_options = $link['route_options'] ?? [];
      $access = $access_manager->checkNamedRoute($route_name, $route_params, $account, TRUE);
      if (!$access->isAllowed()) {
        continue;
      }

      $link['url'] = Url::fromRoute($route_name, $route_params, $route_options)->toString();
      unset($link['route_name'], $link['route_params'], $link['route_options']);
      $quick_links[] = $link;
    }

    return $quick_links;
  }

}
