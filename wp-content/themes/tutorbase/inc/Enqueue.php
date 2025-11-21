<?php
/**
 * Handles enqueueing all scripts and styles
 *
 * @package tutorbase
 */

namespace TUTORBASE;

defined( 'ABSPATH' ) || exit;

/**
 * Enqueue class
 */
class Enqueue {
	/**
	 * Register default hooks and actions for WordPress
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue general scripts.
	 */
	public function enqueue_scripts() {
		// css.
		wp_enqueue_style( 'tutorbase-main', get_template_directory_uri() . '/assets/css/style.min.css', array(), filemtime( TUTORBASE_ASSETS_PATH . '/css/style.min.css' ), 'all' );

		if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
			wp_enqueue_script( 'comment-reply' );
		}
	}
}
