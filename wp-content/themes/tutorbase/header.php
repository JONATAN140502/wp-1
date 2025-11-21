<?php
/**
 * The header for the theme
 *
 * This is the template that displays all of the <head> section and everything up until <body>
 *
 * @package tutorbase
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link href="<?php echo esc_url( get_site_icon_url() ); ?>" rel="icon" type="image/vnd.microsoft.icon">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>

	<?php wp_body_open(); ?>