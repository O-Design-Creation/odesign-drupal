<?php

namespace Drupal\odesign_ai\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Endpoint AJAX de génération de texte par l'IA.
 *
 * Route : POST /admin/boutique/ai/generate
 * Paramètres JSON : context_type (newsletter|product|article), title, content (optionnel)
 * Réponse : {"success": true, "text": "..."} ou {"success": false, "error": "..."}
 */
class AiGenerateController extends ControllerBase {

  /**
   * URL de base de l'API OpenAI.
   */
  protected const API_URL = 'https://api.openai.com/v1/chat/completions';

  /**
   * Le client HTTP.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * Constructeur.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   Le client HTTP.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   La factory de logger.
   */
  public function __construct(
    ClientInterface $http_client,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->httpClient     = $http_client;
    $this->loggerFactory  = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('http_client'),
      $container->get('logger.factory'),
    );
  }

  /**
   * Génère un texte via l'API OpenAI selon le contexte demandé.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   La requête HTTP entrante (JSON body).
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Réponse JSON : {success, text} ou {success, error}.
   */
  public function generate(Request $request): JsonResponse {
    // Lire la clé API depuis state().
    $api_key = \Drupal::state()->get('odesign_ai.api_key', '');
    if (empty($api_key)) {
      return new JsonResponse([
        'success' => FALSE,
        'error'   => 'La clé API OpenAI n\'est pas configurée. Rendez-vous dans Boutique → Configuration → Intelligence artificielle.',
      ], 503);
    }

    // Décoder le body JSON.
    $body = json_decode($request->getContent(), TRUE);
    if (!$body) {
      $body = $request->request->all();
    }

    $context_type = $body['context_type'] ?? 'product';
    $title        = trim($body['title'] ?? '');
    $content      = trim($body['content'] ?? '');

    if (empty($title)) {
      return new JsonResponse(['success' => FALSE, 'error' => 'Le titre est obligatoire.'], 400);
    }

    $config = \Drupal::configFactory()->get('odesign_ai.settings');
    $model  = $config->get('model') ?? 'gpt-4o-mini';

    [$system_prompt, $user_prompt] = $this->buildPrompts($context_type, $title, $content);

    try {
      $response = $this->httpClient->request('POST', self::API_URL, [
        'json'    => [
          'model'    => $model,
          'messages' => [
            ['role' => 'system', 'content' => $system_prompt],
            ['role' => 'user',   'content' => $user_prompt],
          ],
        ],
        'headers' => [
          'Authorization' => 'Bearer ' . $api_key,
          'Content-Type'  => 'application/json',
        ],
        'timeout' => 60,
      ]);

      $data = json_decode($response->getBody()->getContents(), TRUE);
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('odesign_ai')
        ->error('Erreur appel OpenAI : @msg', ['@msg' => $e->getMessage()]);
      return new JsonResponse(['success' => FALSE, 'error' => 'Erreur de connexion à l\'API IA : ' . $e->getMessage()], 502);
    }

    $text = $data['choices'][0]['message']['content'] ?? NULL;
    if ($text === NULL) {
      $error = $data['error']['message'] ?? 'Réponse inattendue de l\'API OpenAI.';
      return new JsonResponse(['success' => FALSE, 'error' => $error], 502);
    }

    return new JsonResponse(['success' => TRUE, 'text' => $text]);
  }

  /**
   * Construit les prompts système et utilisateur selon le contexte.
   *
   * @param string $context_type
   *   Le type de contenu : 'newsletter', 'product' ou 'article'.
   * @param string $title
   *   Le titre ou thème fourni par le gestionnaire.
   * @param string $content
   *   Le contenu existant (optionnel, pour amélioration).
   *
   * @return array
   *   Tableau [system_prompt, user_prompt].
   */
  protected function buildPrompts(string $context_type, string $title, string $content): array {
    switch ($context_type) {

      case 'newsletter':
        $system = "Tu es l'assistant éditorial d'O'Design Création, boutique artisanale française "
          . "spécialisée dans les créations personnalisées en bois gravé. "
          . "Ton : chaleureux, artisanal, personnel. Utilise le vouvoiement.";
        $user   = "Rédige un email newsletter avec le thème : {$title}. "
          . "Format : 1 objet email accrocheur (préfixé 'OBJET:'), puis le corps de la newsletter "
          . "(150-200 mots, intro + contenu + un seul CTA).";
        break;

      case 'product':
        $system = "Tu es l'assistant éditorial d'O'Design Création. "
          . "Rédige des descriptions de fiches produit artisanales, chaleureuses, "
          . "qui valorisent le fait-main et la personnalisation. Utilise le vouvoiement.";
        $user   = "Rédige une description de fiche produit pour : {$title}. "
          . "Maximum 100 mots. Mets en valeur le côté artisanal, personnalisable et émotionnel.";
        if ($content) {
          $user .= "\n\nContenu existant à améliorer :\n{$content}";
        }
        break;

      case 'article':
      default:
        $system = "Tu es l'assistant éditorial d'O'Design Création. "
          . "Rédige des articles de blog chaleureux sur l'artisanat, "
          . "les occasions de vie, les cadeaux personnalisés. Utilise le vouvoiement.";
        $user   = "Rédige une introduction d'article de blog (150 mots) sur le thème : {$title}.";
        if ($content) {
          $user .= "\n\nContenu existant à améliorer :\n{$content}";
        }
        break;
    }

    return [$system, $user];
  }

}
