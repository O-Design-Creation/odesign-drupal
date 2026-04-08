<?php

namespace Drupal\odesign_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\odesign_admin\Service\BoutiqueNavRegistry;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Tableau de bord du gestionnaire boutique.
 *
 * Les statistiques (cards) sont collectées via hook_odesign_boutique_dashboard_cards_alter().
 * La navigation est gérée par BoutiqueNavRegistry (injectée dans hook_page_top).
 */
class DashboardController extends ControllerBase {

  public function __construct(
    protected Connection $database,
    protected BoutiqueNavRegistry $navRegistry,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('odesign_admin.boutique_nav_registry'),
    );
  }

  /**
   * Page principale du tableau de bord.
   */
  public function page(): array {
    // Cards de statistiques via hook (chaque module déclare les siennes).
    $cards = [];
    $this->moduleHandler()->alter('odesign_boutique_dashboard_cards', $cards);
    uasort($cards, fn($a, $b) => ($a['weight'] ?? 50) <=> ($b['weight'] ?? 50));

    // Bandeaux d'alerte si des sections de config critiques sont incomplètes.
    $alert_messages = $this->getAlertMessages();

    // Vue des commandes récentes embarquée dans le dashboard.
    $orders_view = NULL;
    $view = Views::getView('commerce_orders');
    if ($view && $view->access('default')) {
      $view->setDisplay('default');
      $view->setItemsPerPage(5);
      $view->execute();
      $orders_view = $view->buildRenderable('default', [], FALSE);
    }

    return [
      'dashboard' => [
        '#theme'           => 'odesign_admin_dashboard',
        '#cards'           => array_values($cards),
        '#alert_messages'  => $alert_messages,
        '#orders_url'      => Url::fromRoute('entity.commerce_order.collection')->toString(),
        '#orders_view'     => $orders_view,
        '#recent_articles' => $this->getRecentArticles(),
        '#top_products'    => $this->getTopProducts(),
        '#cache'           => ['max-age' => 0],
        '#attached'        => [
          'html_head' => [[
            [
              '#type'       => 'html_tag',
              '#tag'        => 'link',
              '#attributes' => [
                'rel'   => 'stylesheet',
                'href'  => '/modules/custom/odesign_admin/css/dashboard.css',
                'media' => 'all',
              ],
            ],
            'odesign_admin_dashboard_css',
          ]],
        ],
      ],
    ];
  }

  /**
   * Guide marketing opérationnel pour la boutique.
   *
   * Charge le dernier nœud de type `guide` publié via le workflow éditorial.
   * Si aucun guide n'est publié, affiche un CTA de création.
   */
  public function marketingGuide(): array {
    // Chercher le guide le plus récent publié (moderation_state = published).
    $nids = $this->entityTypeManager()
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'guide')
      ->condition('status', 1)
      ->sort('changed', 'DESC')
      ->range(0, 1)
      ->execute();

    if (!empty($nids)) {
      $node = $this->entityTypeManager()->getStorage('node')->load(reset($nids));
      if ($node) {
        $view_builder = $this->entityTypeManager()->getViewBuilder('node');
        $rendered = $view_builder->view($node, 'full');
        $is_admin = $this->currentUser()->hasRole('administrator');

        $build = [
          '#type' => 'container',
          '#attributes' => ['class' => ['odesign-dashboard', 'odesign-marketing-guide']],
          'guide_content' => $rendered,
        ];
        if ($is_admin) {
          $build['actions'] = [
            '#type' => 'container',
            '#attributes' => ['class' => ['odesign-marketing-guide__actions']],
            '#weight' => -10,
            'edit' => [
              '#type'       => 'link',
              '#title'      => $this->t('Modifier ce guide'),
              '#url'        => Url::fromRoute('entity.node.edit_form', ['node' => $node->id()]),
              '#attributes' => ['class' => ['button', 'button--primary']],
              '#prefix'     => '<i class="ph ph-pencil-simple" aria-hidden="true"></i> ',
            ],
            'create' => [
              '#type'       => 'link',
              '#title'      => $this->t('Créer un nouveau guide'),
              '#url'        => Url::fromRoute('node.add', ['node_type' => 'guide']),
              '#attributes' => ['class' => ['button']],
              '#prefix'     => '<i class="ph ph-plus" aria-hidden="true"></i> ',
            ],
          ];
        }
        $build['#cache'] = ['max-age' => 0];
        $build['#attached'] = [
          'html_head' => [[
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
          ]],
        ];
        return $build;
      }
    }

    // Aucun guide publié — CTA de création.
    $create_url = Url::fromRoute('node.add', ['node_type' => 'guide'])->toString();
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['odesign-dashboard', 'odesign-marketing-guide']],
      'empty' => [
        '#markup' => '<div class="odesign-marketing-guide__empty">'
          . '<i class="ph ph-book-open" aria-hidden="true" style="font-size:3rem;opacity:.4;display:block;margin-bottom:1rem"></i>'
          . '<h2>Aucun guide marketing publié</h2>'
          . '<p>Créez votre premier guide pour avoir une feuille de route visible ici.</p>'
          . '<a class="button button--primary" href="' . $create_url . '">'
          . '<i class="ph ph-plus" aria-hidden="true"></i> Créer un guide marketing'
          . '</a>'
          . '</div>',
      ],
      '#cache' => ['max-age' => 0],
      '#attached' => [
        'html_head' => [[
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
        ]],
      ],
    ];
  }

  /**
   * Guide marketing opérationnel (fallback statique — conservé pour référence).
   *
   * @deprecated Remplacé par la version dynamique (guide node type — Lot 4).
   */
  protected function marketingGuideStatic(): array {
    $resource_links = [
      [
        'title'       => 'Gérer les univers',
        'description' => 'Modifier les pages Mariage, Naissance et cadeaux personnalisés.',
        'url'         => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => 'life_occasion'])->toString(),
      ],
      [
        'title'       => 'Mes produits',
        'description' => 'Mettre en avant les produits à pousser en ce moment.',
        'url'         => Url::fromRoute('entity.commerce_product.collection')->toString(),
      ],
      [
        'title'       => 'Gérer le blog',
        'description' => 'Publier un article utile pour un temps fort ou une catégorie.',
        'url'         => Url::fromRoute('system.admin_content')->toString(),
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
   * Bandeaux d'alerte si des clés critiques (SMTP, Stripe) ne sont pas configurées.
   */
  protected function getAlertMessages(): array {
    $messages = [];
    $config = $this->config('odesign_admin.settings');

    if (!\Drupal::config('smtp.settings')->get('smtp_username')) {
      $messages[] = [
        'type'    => 'warning',
        'message' => "L'envoi d'e-mails n'est pas configuré. Ajoutez vos identifiants Brevo dans la <a href=\"/admin/boutique/configuration#emails\">configuration boutique</a>.",
      ];
    }

    if (!$config->get('stripe_secret_key')) {
      $messages[] = [
        'type'    => 'warning',
        'message' => "Le paiement Stripe n'est pas encore activé. Configurez vos clés Stripe dans la <a href=\"/admin/boutique/configuration#stripe\">configuration boutique</a>.",
      ];
    }

    return $messages;
  }

  /**
   * Récupère le top 5 des produits les plus vendus (commandes complétées).
   *
   * Retourne un tableau de ['title' => string, 'cnt' => int, 'product_url' => string].
   * GROUP BY purchased_entity uniquement pour éviter la fragmentation en cas de
   * changement de titre.
   */
  protected function getTopProducts(): array {
    try {
      $rows = $this->database->query(
        'SELECT oi.purchased_entity, oi.title, SUM(oi.quantity) as cnt
         FROM {commerce_order_item} oi
         JOIN {commerce_order} co ON co.order_id = oi.order_id
         WHERE co.state = :state
         GROUP BY oi.purchased_entity
         ORDER BY cnt DESC
         LIMIT 5',
        [':state' => 'completed']
      )->fetchAll();
    }
    catch (\Exception) {
      return [];
    }

    $products = [];
    foreach ($rows as $row) {
      $products[] = [
        'title'       => $row->title,
        'cnt'         => (int) $row->cnt,
        'product_url' => $row->purchased_entity
          ? '/admin/commerce/products?title=&status=All'
          : '',
      ];
    }
    return $products;
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
    catch (\Exception) {
      // Silently fail if table doesn't exist.
    }
    return $articles;
  }

}
