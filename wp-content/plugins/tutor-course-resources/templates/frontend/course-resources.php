<?php
/**
 * Template: Recursos del curso en frontend
 *
 * @package TutorCourseResources
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$plugin = Tutor_Course_Resources::get_instance();
?>

<div class="tutor-course-resources-section">
	<div class="tutor-course-resources-header">
		<h3>
			<span class="dashicons dashicons-media-document"></span>
			<?php esc_html_e( 'Recursos del Curso', 'tutor-course-resources' ); ?>
		</h3>
	</div>
	
	<div class="tutor-course-resources-list">
		<?php foreach ( $accessible_resources as $resource ) : 
			$file_url = '';
			if ( $resource->resource_type === 'file' && $resource->file_id ) {
				$file_url = wp_get_attachment_url( $resource->file_id );
			} elseif ( $resource->resource_type === 'drive' && $resource->resource_url ) {
				$file_url = $resource->resource_url;
			}
			
			if ( empty( $file_url ) ) {
				continue;
			}
			
			$file_icon = 'dashicons-media-document';
			if ( $resource->resource_type === 'drive' ) {
				$file_icon = 'dashicons-google';
			} elseif ( $resource->file_id ) {
				$mime_type = get_post_mime_type( $resource->file_id );
				if ( strpos( $mime_type, 'image' ) !== false ) {
					$file_icon = 'dashicons-format-image';
				} elseif ( strpos( $mime_type, 'video' ) !== false ) {
					$file_icon = 'dashicons-format-video';
				} elseif ( strpos( $mime_type, 'audio' ) !== false ) {
					$file_icon = 'dashicons-format-audio';
				} elseif ( strpos( $mime_type, 'pdf' ) !== false ) {
					$file_icon = 'dashicons-media-text';
				} elseif ( strpos( $mime_type, 'zip' ) !== false || strpos( $mime_type, 'rar' ) !== false ) {
					$file_icon = 'dashicons-archive';
				}
			}
		?>
			<div class="tutor-resource-item">
				<div class="resource-icon">
					<span class="dashicons <?php echo esc_attr( $file_icon ); ?>"></span>
				</div>
				<div class="resource-content">
					<h4><?php echo esc_html( $resource->title ); ?></h4>
					<?php if ( ! empty( $resource->description ) ) : ?>
						<p class="resource-description"><?php echo esc_html( $resource->description ); ?></p>
					<?php endif; ?>
					<a href="<?php echo esc_url( $file_url ); ?>" target="_blank" class="tutor-btn tutor-btn-primary">
						<?php esc_html_e( 'Descargar/Ver', 'tutor-course-resources' ); ?>
					</a>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>

