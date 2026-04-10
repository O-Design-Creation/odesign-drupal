<?php

namespace Drupal\odesign_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Affiche le journal des nouveautés (CHANGELOG-gestionnaire.md) dans le back-office.
 *
 * Le fichier source est à la racine du projet (un niveau au-dessus de DRUPAL_ROOT).
 * Il est embarqué dans l'image Docker — donc toujours synchronisé avec la version
 * déployée (TCK-133).
 */
class ChangelogController extends ControllerBase {

  /**
   * Chemin absolu vers le fichier changelog.
   */
  private function changelogPath(): string {
    return DRUPAL_ROOT . '/../CHANGELOG-gestionnaire.md';
  }

  /**
   * Page principale du changelog.
   */
  public function page(): array {
    $file = $this->changelogPath();

    // Sécurité : vérifier que le fichier résolu est bien dans le répertoire attendu.
    $real = realpath($file);
    $base = realpath(DRUPAL_ROOT . '/..');
    if (!$real || !$base || !str_starts_with($real, $base . '/')) {
      throw new NotFoundHttpException();
    }

    if (!is_file($real)) {
      throw new NotFoundHttpException();
    }

    $markdown = file_get_contents($real);
    $html     = $this->renderMarkdown($markdown);
    $version  = getenv('APP_VERSION') ?: NULL;

    return [
      '#theme'   => 'odesign_admin_changelog',
      '#content' => $html,
      '#version' => $version,
      '#cache'   => ['max-age' => 0],
    ];
  }

  /**
   * Convertit le Markdown en HTML via Parsedown.
   */
  private function renderMarkdown(string $markdown): string {
    $parsedown = new \Parsedown();
    $parsedown->setSafeMode(TRUE);
    return $parsedown->text($markdown);
  }

}
