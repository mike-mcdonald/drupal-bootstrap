<?php
/**
 * @file
 * Contains \Drupal\bootstrap\Alter\IconBundleList.
 */

namespace Drupal\bootstrap\Alter;

use Drupal\bootstrap\Bootstrap;

/**
 * Implements hook_icon_bundle_list_alter().
 *
 * @BootstrapAlter(
 *   id = "icon_bundle_list"
 * )
 */
class IconBundleList implements AlterInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(&$data, &$context1 = NULL, &$context2 = NULL) {
    if (Bootstrap::getTheme()->getSetting('tooltip_enabled')) {
      foreach ($data as &$icon) {
        $icon['#attributes']['data-toggle'] = 'tooltip';
        $icon['#attributes']['data-placement'] = 'bottom';
      }
    }
  }

}
