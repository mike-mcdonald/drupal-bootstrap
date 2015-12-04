<?php
/**
 * @file
 * Contains \Drupal\bootstrap\Plugin\Provider\JsDelivr.
 */

namespace Drupal\bootstrap\Plugin\Provider;

use Drupal\bootstrap\Annotation\BootstrapProvider;
use Drupal\bootstrap\Bootstrap;
use Drupal\bootstrap\Utility\Element;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Form\FormStateInterface;

/**
 * The "jsdelivr" CDN provider plugin.
 *
 * @BootstrapProvider(
 *   id = "jsdelivr",
 *   label = @Translation("jsDelivr"),
 *   api = "http://api.jsdelivr.com/v1/bootstrap/libraries",
 *   themes = { },
 *   versions = { },
 * )
 */
class JsDelivr extends ProviderBase {

  /**
   * Extracts theme information from files provided by the jsDelivr API.
   *
   * This will place the raw files into proper "css", "js" and "min" arrays
   * (if they exist) and prepends them with a base URL provided.
   *
   * @param array $files
   *   An array of files to process.
   * @param string $base_url
   *   The base URL each one of the $files are relative to, this usually
   *   should also include the version path prefix as well.
   *
   * @return array
   *   An associative array containing the following keys, if there were
   *   matching files found:
   *   - css
   *   - js
   *   - min:
   *     - css
   *     - js
   */
  protected function extractThemes(array $files, $base_url = '') {
    $themes = [];
    foreach ($files as $file) {
      preg_match('`([^/]*)/bootstrap(-theme)?(\.min)?\.(js|css)$`', $file, $matches);
      if (!empty($matches[1]) && !empty($matches[4])) {
        $path = $matches[1];
        $min = $matches[3];
        $filetype = $matches[4];

        // Determine the "theme" name.
        if ($path === 'css' || $path === 'js') {
          $theme = 'bootstrap';
          $title = (string) t('Bootstrap');
        }
        else {
          $theme = $path;
          $title = ucfirst($path);
        }
        if ($matches[2]) {
          $theme = 'bootstrap_theme';
          $title = (string) t('Bootstrap Theme');
        }

        $themes[$theme]['title'] = $title;
        if ($min) {
          $themes[$theme]['min'][$filetype][] = "$base_url/$path/bootstrap{$matches[2]}$min.$filetype";
        }
        else {
          $themes[$theme][$filetype][] = "$base_url/$path/bootstrap{$matches[2]}$min.$filetype";
        }
      }
    }
    return $themes;
  }

  /**
   * {@inheritdoc}
   */
  public function getAssets($types = NULL) {
    $this->assets = [];
    $error = !empty($provider['error']);
    $version = $error ? Bootstrap::FRAMEWORK_VERSION : $this->theme->getSetting('cdn_jsdelivr_version');
    $theme = $error ? 'bootstrap' : $this->theme->getSetting('cdn_jsdelivr_theme');
    if (isset($this->pluginDefinition['themes'][$version][$theme])) {
      $this->assets = $this->pluginDefinition['themes'][$version][$theme];
    }
    return parent::getAssets($types);
  }

  /**
   * {@inheritdoc}
   */
  public function processApi(array $json, array &$definition) {
    $definition['description'] = t('<p style="background:#EB4C36"><a href=":jsdelivr" target="_blank"><img src="http://www.jsdelivr.com/img/logo-34.png" alt="jsDelivr Logo"/></a></p><p><a href=":jsdelivr" target="_blank">jsDelivr</a> is a free multi-CDN infrastructure that uses <a href=":maxcdn" target="_blank">MaxCDN</a>, <a href=":cloudflare" target="_blank">Cloudflare</a> and many others to combine their powers for the good of the open source community... <a href=":jsdelivr_about" target="_blank">read more</a></p>', [
      ':jsdelivr' => 'http://www.jsdelivr.com',
      ':jsdelivr_about' => 'http://www.jsdelivr.com/about',
      ':maxcdn' => 'http://www.maxcdn.com',
      ':cloudflare' => 'http://www.cloudflare.com',
    ]);

    // Expected library names from jsDelivr API v1. Must use "twitter-bootstrap"
    // instead of "bootstrap" (which is just a folder alias).
    // @see https://www.drupal.org/node/2504343
    // @see https://github.com/jsdelivr/api/issues/94
    $bootstrap = 'twitter-bootstrap';
    $bootswatch = 'bootswatch';

    // Extract the raw asset files from the JSON data for each framework.
    $libraries = [];
    if ($json) {
      foreach ($json as $data) {
        if ($data['name'] === $bootstrap || $data['name'] === $bootswatch) {
          foreach ($data['assets'] as $asset) {
            if (preg_match('/^' . substr(Bootstrap::FRAMEWORK_VERSION, 0, 1) . '\.\d\.\d$/', $asset['version'])) {
              $libraries[$data['name']][$asset['version']] = $asset['files'];
            }
          }
        }
      }
    }

    // If the main bootstrap library could not be found, then provide defaults.
    if (!isset($libraries[$bootstrap])) {
      $definition['error'] = TRUE;
      $definition['versions'][Bootstrap::FRAMEWORK_VERSION] = Bootstrap::FRAMEWORK_VERSION;
      $definition['themes'][Bootstrap::FRAMEWORK_VERSION] = [
        'bootstrap' => [
          'title' => (string) t('Bootstrap'),
          'css' => ['//cdn.jsdelivr.net/bootstrap/' . Bootstrap::FRAMEWORK_VERSION . '/css/bootstrap.css'],
          'js' => ['//cdn.jsdelivr.net/bootstrap/' . Bootstrap::FRAMEWORK_VERSION . '/js/bootstrap.js'],
          'min' => [
            'css' => ['//cdn.jsdelivr.net/bootstrap/' . Bootstrap::FRAMEWORK_VERSION . '/css/bootstrap.min.css'],
            'js' => ['//cdn.jsdelivr.net/bootstrap/' . Bootstrap::FRAMEWORK_VERSION . '/js/bootstrap.min.js'],
          ],
        ],
      ];
      return;
    }

    // Populate the provider array with the versions and themes available.
    foreach (array_keys($libraries[$bootstrap]) as $version) {
      $definition['versions'][$version] = $version;

      if (!isset($definition['themes'][$version])) {
        $definition['themes'][$version] = [];
      }

      // Extract Bootstrap themes.
      $definition['themes'][$version] = NestedArray::mergeDeep($definition['themes'][$version], $this->extractThemes($libraries[$bootstrap][$version], "//cdn.jsdelivr.net/bootstrap/$version"));

      // Extract Bootswatch themes.
      if (isset($libraries[$bootswatch][$version])) {
        $definition['themes'][$version] = NestedArray::mergeDeep($definition['themes'][$version], $this->extractThemes($libraries[$bootswatch][$version], "//cdn.jsdelivr.net/bootswatch/$version"));
      }
    }

    // Post process the themes to fill in any missing assets.
    foreach (array_keys($definition['themes']) as $version) {
      foreach (array_keys($definition['themes'][$version]) as $theme) {
        // Some themes actually require Bootstrap framework assets to still
        // function properly.
        if ($theme !== 'bootstrap') {
          foreach (['css', 'js'] as $type) {
            // Bootswatch themes include the Bootstrap framework in their CSS.
            // Skip the CSS portions.
            if ($theme !== 'bootstrap_theme' && $type === 'css') {
              continue;
            }
            if (!isset($definition['themes'][$version][$theme][$type]) && !empty($definition['themes'][$version]['bootstrap'][$type])) {
              $definition['themes'][$version][$theme][$type] = [];
            }
            $definition['themes'][$version][$theme][$type] = NestedArray::mergeDeep($definition['themes'][$version]['bootstrap'][$type], $definition['themes'][$version][$theme][$type]);
            if (!isset($definition['themes'][$version][$theme]['min'][$type]) && !empty($definition['themes'][$version]['bootstrap']['min'][$type])) {
              $definition['themes'][$version][$theme]['min'][$type] = [];
            }
            $definition['themes'][$version][$theme]['min'][$type] = NestedArray::mergeDeep($definition['themes'][$version]['bootstrap']['min'][$type], $definition['themes'][$version][$theme]['min'][$type]);
          }
        }
        // Some themes do not have a non-minified version, clone them to the
        // "normal" css/js arrays to ensure that the theme still loads if
        // aggregation (minification) is disabled.
        foreach (['css', 'js'] as $type) {
          if (!isset($definition['themes'][$version][$theme][$type]) && isset($definition['themes'][$version][$theme]['min'][$type])) {
            $definition['themes'][$version][$theme][$type] = $definition['themes'][$version][$theme]['min'][$type];
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(Element $settings, FormStateInterface $form_state) {
    $version = $form_state->getValue('cdn_jsdelivr_version', $this->theme->getSetting('cdn_jsdelivr_version'));
    $settings->cdn_jsdelivr_version = [
      '#type' => 'select',
      '#title' => t('Version'),
      '#options' => $this->pluginDefinition['versions'],
      '#default_value' => $version,
      '#ajax' => [
        'callback' => 'Drupal\bootstrap\Provider\JsDelivr::cdnProviderAjax',
        'wrapper' => 'bootstrap-cdn-provider-jsdelivr',
      ],
    ];
    if (!$this->hasError() && !$this->isImported()) {
      $settings->cdn_jsdelivr_version->setProperty('description', t('These versions are automatically populated by the @provider API upon cache clear and newer versions may appear over time. It is highly recommended the version that the site was built with stays at that version. Until a newer version has been properly tested for updatability by the site maintainer, you should not arbitrarily "update" just because there is a newer version. This can cause many inconsistencies and undesired effects with an existing site.', [
        '@provider' => $this->getLabel(),
      ]));
    }

    // Bootswatch.
    $themes = [];
    if (isset($this->pluginDefinition['themes'][$version])) {
      foreach ($this->pluginDefinition['themes'][$version] as $theme => $data) {
        $themes[$theme] = $data['title'];
      }
    }
    $settings->cdn_jsdelivr_theme = [
      '#type' => 'select',
      '#title' => t('Theme'),
      '#description' => t('Choose the example <a href=":bootstrap_theme" target="_blank">Bootstrap Theme</a> provided by Bootstrap or one of the many, many <a href=":bootswatch" target="_blank">Bootswatch</a> themes!', [
        ':bootswatch' => 'https://bootswatch.com',
        ':bootstrap_theme' => 'http://getbootstrap.com/examples/theme/',
      ]),
      '#default_value' => $this->theme->getSetting('cdn_jsdelivr_theme'),
      '#options' => $themes,
      '#empty_option' => t('Bootstrap (default)'),
      '#empty_value' => 'bootstrap',
      '#suffix' => '<div id="bootstrap-theme-preview"></div>',
    ];
  }

  /**
   * AJAX callback for reloading CDN provider elements.
   *
   * @param array $form
   *   Nested array of form elements that comprise the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function cdnProviderAjax(array $form, FormStateInterface $form_state) {
    return $form['advanced']['cdn']['provider'][$form_state->getValue('cdn_provider')];
  }

}