<?php

namespace Drupal\odesign_ai\Plugin\AiProvider;

use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\AiProviderClientBase;
use Drupal\ai\Exception\AiResponseErrorException;
use Drupal\ai\Exception\AiSetupFailureException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatInterface;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin AiProvider OpenAI pour O'Design Création.
 *
 * Ce plugin expose l'API OpenAI (GPT-4o-mini et GPT-4o) comme fournisseur
 * IA natif pour drupal/ai et ai_ckeditor. La clé API est stockée dans
 * \Drupal::state() et n'est jamais exportée dans config/sync/.
 */
#[AiProvider(
  id: 'openai',
  label: new TranslatableMarkup('OpenAI'),
)]
class OpenAiProvider extends AiProviderClientBase implements ChatInterface {

  /**
   * URL de base de l'API OpenAI.
   */
  protected const API_URL = 'https://api.openai.com/v1/chat/completions';

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    return $this->configFactory->get('odesign_ai.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool {
    $api_key = \Drupal::state()->get('odesign_ai.api_key', '');
    if (empty($api_key)) {
      return FALSE;
    }
    if ($operation_type) {
      return in_array($operation_type, $this->getSupportedOperationTypes(), TRUE);
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    return ['chat'];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    return [
      'gpt-4o-mini' => 'GPT-4o mini (économique)',
      'gpt-4o'      => 'GPT-4o (haute qualité)',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function setAuthentication(mixed $authentication): void {
    // Authentication is managed via Drupal state in loadApiKey().
  }

  /**
   * {@inheritdoc}
   */
  public function chat(array|string|ChatInput $input, string $model_id, array $tags = []): ChatOutput {
    $api_key = $this->loadApiKey();
    $messages = $this->buildMessages($input);

    try {
      $response = $this->httpClient->request('POST', self::API_URL, [
        'json'    => [
          'model'    => $model_id,
          'messages' => $messages,
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
      throw new AiResponseErrorException('Erreur de connexion à OpenAI : ' . $e->getMessage());
    }

    if (!isset($data['choices'][0]['message']['content'])) {
      $error = $data['error']['message'] ?? 'Réponse inattendue de l\'API OpenAI.';
      throw new AiResponseErrorException($error);
    }

    $text = $data['choices'][0]['message']['content'];
    return new ChatOutput(new ChatMessage('assistant', $text), $data, []);
  }

  /**
   * Construit le tableau de messages pour l'API OpenAI.
   *
   * @param array|string|\Drupal\ai\OperationType\Chat\ChatInput $input
   *   L'entrée utilisateur.
   *
   * @return array
   *   Tableau de messages au format OpenAI.
   */
  protected function buildMessages(array|string|ChatInput $input): array {
    $messages = [];

    if ($system = $this->getChatSystemRole()) {
      $messages[] = ['role' => 'system', 'content' => $system];
    }

    if ($input instanceof ChatInput) {
      foreach ($input->getMessages() as $message) {
        $messages[] = [
          'role'    => $message->getRole() ?: 'user',
          'content' => $message->getText(),
        ];
      }
    }
    elseif (is_string($input)) {
      $messages[] = ['role' => 'user', 'content' => $input];
    }
    else {
      $messages = array_merge($messages, $input);
    }

    return $messages;
  }

  /**
   * {@inheritdoc}
   *
   * Lit la clé API depuis \Drupal::state() plutôt que depuis le Key module.
   */
  protected function loadApiKey(): string {
    $api_key = \Drupal::state()->get('odesign_ai.api_key', '');
    if (empty($api_key)) {
      throw new AiSetupFailureException(
        'La clé API OpenAI n\'est pas configurée. '
        . 'Rendez-vous dans Administration → Boutique → Configuration → Intelligence artificielle.'
      );
    }
    return $api_key;
  }

}
