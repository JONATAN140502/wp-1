<?php
/**
 * The main template file
 *
 * @package tutorbase
 */

defined( 'ABSPATH' ) || exit;

get_header();

?>
<main class="tutorbase-site-main">
	<?php
	if ( have_posts() ) :
		if ( is_home() && ! is_front_page() ) :
			?>
			<header>
				<h1 class="page-title screen-reader-text"><?php single_post_title(); ?></h1>
			</header>
			<?php
		endif;
		while ( have_posts() ) :
			the_post();
			the_content();
		endwhile;
		the_posts_navigation();
	else :
		the_content();
	endif;
	?>
</main><!-- #main -->
<?php

get_footer();
