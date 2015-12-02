<?php

/**
 * @file
 * Contains \Drupal\bootstrap\PluginManager.
 */

namespace Drupal\bootstrap;

use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Base class for Bootstrap plugin managers.
 *
 * @ingroup plugin_api
 */
class PluginManager extends DefaultPluginManager {

  /**
   * The current theme.
   *
   * @var \Drupal\bootstrap\Theme
   */
  protected $theme;

  /**
   * The theme handler to check if theme exists.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * The theme manager to invoke alter hooks.
   *
   * @var \Drupal\Core\Theme\ThemeManager
   */
  protected $themeManager;

  /**
   * Creates the discovery object.
   *
   * @param \Drupal\bootstrap\Theme $theme
   *   The theme to use for discovery.
   * @param string|bool $subdir
   *   The plugin's subdirectory, for example Plugin/views/filter.
   * @param string|null $plugin_interface
   *   (optional) The interface each plugin should implement.
   * @param string $plugin_definition_annotation_name
   *   (optional) Name of the annotation that contains the plugin definition.
   *   Defaults to 'Drupal\Component\Annotation\Plugin'.
   */
  public function __construct(Theme $theme, $subdir, $plugin_interface = NULL, $plugin_definition_annotation_name = 'Drupal\Component\Annotation\Plugin') {
    // Get the active theme.
    $this->theme = $theme;

    // Determine the namespaces to search for.
    $namespaces = [];
    foreach ($theme->getAncestry() as $ancestor) {
      $namespaces['Drupal\\' . $ancestor->getName()] = [DRUPAL_ROOT . '/' . $ancestor->getPath() . '/src'];
    }
    $this->namespaces = new \ArrayObject($namespaces);

    $this->subdir = $subdir;
    $this->pluginDefinitionAnnotationName = $plugin_definition_annotation_name;
    $this->pluginInterface = $plugin_interface;
    $this->themeHandler = \Drupal::service('theme_handler');
    $this->themeManager = \Drupal::service('theme.manager');
  }

  /**
   * {@inheritdoc}
   */
  protected function alterDefinitions(&$definitions) {
    if ($this->alterHook) {
      $this->themeManager->alter($this->alterHook, $definitions);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function providerExists($provider) {
    return $this->themeHandler->themeExists($provider);
  }

}
