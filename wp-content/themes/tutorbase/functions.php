<?php
/**
 * Handles loading all the necessary files
 *
 * @package tutorbase
 */

defined( 'ABSPATH' ) || exit;

/**
 * Require all constants variable
 *
 * @package tutorbase
 */
require_once __DIR__ . '/constants.php';

/**
 * Tutorbase spl_autoloader
 *
 * @param String $class_name description.
 *
 * @return void
 */
function tutorbase_spl_autoloader( $class_name ) {
	if ( ! class_exists( $class_name ) ) {
		$class_name = preg_replace(
			array( '/([a-z])([A-Z])/', '/\\\/' ),
			array( '$1$2', DIRECTORY_SEPARATOR ),
			$class_name
		);
		$class_name = str_replace( 'TUTORBASE' . DIRECTORY_SEPARATOR, '/inc' . DIRECTORY_SEPARATOR, $class_name );
		$file_name  = TUTORBASE_PATH . $class_name . '.php';

		if ( file_exists( $file_name ) ) {
			require_once $file_name;
		}
	}
}

spl_autoload_register( 'tutorbase_spl_autoloader' );

new TUTORBASE\Init();

add_filter( 'pre_set_site_transient_update_themes', 'tutorbase_check_for_update' );

/**
 * Tutorbase check for update function
 *
 * @param @mixed $transient transient.
 *
 * @return @mixed return
 */
function tutorbase_check_for_update( $transient ) {
	if ( empty( $transient->checked ) ) {
		return $transient;
	}

	$theme_slug      = 'tutorbase';
	$current_version = wp_get_theme( $theme_slug )->get( 'Version' );

	$tutor_license = get_option( 'tutor_license_info' );

	$args = array(
		'headers' => array(
			'secret-key'   => 't344d5d71sae7dcb546b8cf55e594808',
			'Content-Type' => 'application/json',
		),
		'body'    => wp_json_encode(
			array(
				'product_slug' => 'tutor-base',
				'license_key'  => $tutor_license['license_key'] ?? '',
			)
		),
		'method'  => 'POST',
	);

	$endpoint = 'https://tutorlms.com//wp-json/themeum-products/v1/check-update';

	$product_request = wp_remote_post( $endpoint, $args );

	if ( ! is_wp_error( $product_request ) ) {
		$product_request_body = json_decode( wp_remote_retrieve_body( $product_request ) );
		$tutor_base           = $product_request_body->body_response;

		if ( version_compare( $current_version, $tutor_base->version, '<' ) && isset( $tutor_base->download_url ) ) {
			$transient->response[ $theme_slug ] = array(
				'theme'       => $theme_slug,
				'new_version' => $tutor_base->version,
				'url'         => $tutor_base->url ?? '',
				'package'     => $tutor_base->download_url,
			);
		}
	}

	return $transient;
}
