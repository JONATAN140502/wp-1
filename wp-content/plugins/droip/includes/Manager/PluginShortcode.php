<?php

/**
 * Plugin Shortcode handler
 *
 * @package droip
 */

namespace Droip\Manager;

use Droip\API\ContentManager\ContentManagerHelper;

if (! defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

/**
 * Do some task with shortcode
 */

class PluginShortcode
{
  /**
   * Initilize the class
   *
   * @return void
   */
  public function __construct()
  {
    // Droip shortcode
    add_shortcode(DROIP_APP_PREFIX, [$this, 'droip_shortcode_handler']);

    // Content manager shortcode
    add_shortcode(DROIP_APP_PREFIX . '_cm', [$this, 'droip_cm_shortcode_handler']);
  }

  private function clean_shortcode_value($val)
  {
    if (!is_string($val)) {
      return $val;
    }

    // Decode entities like &#8220; or &#8221;
    $val = html_entity_decode($val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $val = trim($val);

    // Strip wrapping quotes repeatedly (any combination)
    while (
      preg_match(
        '/^([\'"“”‘’]+)(.*?)([\'"“”‘’]+)$/u',
        $val,
        $matches
      )
    ) {
      // Only strip if first and last char are quote-like
      $first = mb_substr($val, 0, 1);
      $last = mb_substr($val, -1);
      if (preg_match('/[\'"“”‘’]/u', $first) && preg_match('/[\'"“”‘’]/u', $last)) {
        $val = trim($matches[2]);
      } else {
        break;
      }
    }

    return $val;
  }

  /**
   * Render the shortcode
   *
   * @param array $atts Shortcode attributes.
   * @return string
   */
  public function droip_cm_shortcode_handler($attributes)
  {
    try {
      if (!is_array($attributes)) {
        $attributes = [];
      }


      $defaults_attrs = array(
        'slug' => '',
        'data' => '',
      );

      $atts = shortcode_atts($defaults_attrs, $attributes);

      $slug = $this->clean_shortcode_value(sanitize_text_field($atts['slug']));
      $data = $this->clean_shortcode_value(sanitize_text_field($atts['data']));


      if (empty($slug) || empty($data)) {
        return '<strong>[Slug and Data attributes are required]</strong>';
      }

      $cm_post = get_page_by_path($slug, OBJECT, DROIP_CONTENT_MANAGER_PREFIX);

      if (!$cm_post) {
        return '<strong>[No Data found]</strong>';
      }

      $data = strtolower(trim($data));

      switch ($data) {
        case 'count': {
            return ContentManagerHelper::get_child_post_count($cm_post->ID, ['publish']);
          }

        default:
          return '<strong>[No Data found]</strong>';
      }

      return '<strong>[No Data found]</strong>';
    } catch (\Exception $e) {
      return '<strong>[No Data found]</strong>';
    }
  }

  /**
   * Droip shortcode handler
   *
   * @param array $atts Shortcode attributes.
   * @return string
   */
  public function droip_shortcode_handler($attributes)
  {
    try {
      if (!is_array($attributes)) {
        $attributes = [];
      }

      $defaults_attrs = array(
        'data' => '',
      );

      $atts = shortcode_atts($defaults_attrs, $attributes);

      $data = $this->clean_shortcode_value(sanitize_text_field($atts['data']));

      if (empty($data)) {
        return '<strong>[Data attribute is required]</strong>';
      }

      $data = strtolower(trim($data));

      switch ($data) {
        case 'current_year': {
            return date('Y');
          }

        case 'current_date': {
            return date('F j, Y');
          }

        default:
          return '<strong>[No Data found]</strong>';
      }

      return '<strong>[No Data found]</strong>';
    } catch (\Exception $e) {
      return '<strong>[No Data found]</strong>';
    }
  }
}
