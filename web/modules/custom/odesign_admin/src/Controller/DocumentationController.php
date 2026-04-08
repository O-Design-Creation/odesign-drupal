<?php

namespace Drupal\odesign_admin\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Affiche la documentation utilisateur gestionnaire depuis les fichiers Markdown.
 */
class DocumentationController extends ControllerBase {

  /**
   * Chemin absolu vers le répertoire des fichiers Markdown.
   */
  private function docsPath(): string {
    return DRUPAL_ROOT . '/../docs/utilisateur';
  }

  /**
   * URL publique du répertoire des captures d'écran.
   */
  private function screenshotsUrl(): string {
    return base_path() . 'modules/custom/odesign_admin/images/doc-screenshots';
  }

  /**
   * Retourne la liste ordonnée des chapitres disponibles.
   *
   * @return array[] Liste de ['file' => ..., 'chapter' => ..., 'title' => ...]
   */
  private function chapters(): array {
    $docs_path = $this->docsPath();
    $pattern   = $docs_path . '/[0-9][0-9]-*.md';
    $files     = glob($pattern);
    sort($files);

    $chapters = [];
    foreach ($files as $file) {
      $basename = basename($file, '.md');
      // Extrait le titre depuis le premier heading H1 du fichier.
      $title = $this->extractTitle($file) ?? $basename;
      $chapters[] = [
        'file'    => $file,
        'chapter' => $basename,
        'title'   => $title,
      ];
    }

    return $chapters;
  }

  /**
   * Extrait le premier titre H1 d'un fichier Markdown.
   */
  private function extractTitle(string $file): ?string {
    $handle = fopen($file, 'r');
    if (!$handle) {
      return NULL;
    }
    while (($line = fgets($handle)) !== FALSE) {
      $line = trim($line);
      if (str_starts_with($line, '# ')) {
        fclose($handle);
        return ltrim($line, '# ');
      }
    }
    fclose($handle);
    return NULL;
  }

  /**
   * Page d'index — liste tous les chapitres.
   */
  public function index(): array {
    $chapters = $this->chapters();

    return [
      '#theme'    => 'odesign_admin_documentation',
      '#chapters' => $chapters,
      '#content'  => NULL,
      '#current'  => NULL,
      '#cache'    => ['max-age' => 0],
    ];
  }

  /**
   * Page d'un chapitre — rendu HTML du fichier Markdown.
   */
  public function chapter(string $chapter): array {
    $docs_path = $this->docsPath();
    $file      = $docs_path . '/' . $chapter . '.md';

    // Sécurité : on n'autorise que les fichiers du répertoire docs.
    $real = realpath($file);
    $base = realpath($docs_path);
    if (!$real || !str_starts_with($real, $base . '/')) {
      throw new NotFoundHttpException();
    }

    if (!file_exists($file)) {
      throw new NotFoundHttpException();
    }

    $markdown = file_get_contents($file);
    $html     = $this->renderMarkdown($markdown);
    $chapters = $this->chapters();

    return [
      '#theme'    => 'odesign_admin_documentation',
      '#chapters' => $chapters,
      '#content'  => $html,
      '#current'  => $chapter,
      '#cache'    => ['max-age' => 0],
    ];
  }

  /**
   * Convertit le Markdown en HTML via Parsedown et corrige les chemins d'images.
   */
  private function renderMarkdown(string $markdown): string {
    // Corrige les chemins d'images relatifs screenshots/* → URL web.
    $screenshots_url = $this->screenshotsUrl();
    $markdown = preg_replace(
      '/!\[([^\]]*)\]\(screenshots\/([^)]+)\)/',
      '![$1](' . $screenshots_url . '/$2)',
      $markdown
    );

    $parsedown = new \Parsedown();
    $parsedown->setSafeMode(FALSE);

    return $parsedown->text($markdown);
  }

}
