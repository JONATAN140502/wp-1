<?php
/**
 * Handles all the class initialization
 *
 * @package tutorbase
 */

namespace TUTORBASE;

defined( 'ABSPATH' ) || exit;

/**
 * Init class
 *
 * @package tutorbase
 */
final class Init {
	/**
	 * Initiate all the classes inside constructor
	 *
	 * @return void
	 */
	public function __construct() {
		new Setup();
		new Enqueue();
	}
}
