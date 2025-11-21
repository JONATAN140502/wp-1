<?php
/**
 * Template for my courses bundle filter.
 *
 * @package TutorPro\CourseBundle
 * @subpackage Templates
 * @author Themeum <support@themeum.com>
 * @link https://themeum.com
 * @since 3.5.0
 */

$options  = isset( $options ) ? $options : array();
$selected = isset( $selected ) ? $selected : '';
$classes  = isset( $classes ) ? $classes : '';
?>

<li class="<?php echo esc_attr( $classes ); ?>">
	<span class="tutor-fs-6"><?php esc_html_e( 'Type:', 'tutor-pro' ); ?></span>
	<select name="type" class="tutor-form-control tutor-form-select tutor-filter-select">
		<?php foreach ( $options as $key => $value ) : ?>
		<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $selected ); ?>>
			<?php echo esc_html( $value ); ?>
		</option>
		<?php endforeach; ?>
	</select>
</li>
