<?php
/**
 * Plugin Name: MC Optimizer · Tutor LMS (Complementario)
 * Description: Separa las funciones avanzadas de Tutor LMS del plugin principal MC Optimizer.
 * Author: MC
 * Version: 1.0.3
 */

if (!defined('ABSPATH')) exit;

// Requiere que el plugin principal esté presente para integrarse bien, pero puede funcionar aislado
// Activa el módulo Tutor en el plugin principal mediante una constante
if (!defined('MC_TUTOR_FEATURE_ENABLED')) {
	define('MC_TUTOR_FEATURE_ENABLED', true);
}

// Visibilidad sólo para desarrolladores
function mc_opt_tutor_is_dev(): bool {
	if (function_exists('mc_is_dev_user')) return (bool) mc_is_dev_user();
	return current_user_can('manage_options');
}

// Cargar el archivo original del módulo Tutor
add_action('plugins_loaded', function(){
	if (!mc_opt_tutor_is_dev()) return; // sólo devs
	$path = WP_CONTENT_DIR . '/plugins/mc-optimizer/contact/funciones_adicionales_tutor.php';
	if (file_exists($path)) require_once $path;

	// Registrar shortcode buscador_cursos completo (migrado del principal)
	add_shortcode('buscador_cursos', function(){
		if (!(class_exists('TUTOR\\Tutor') || function_exists('tutor'))){
			return '<div style="padding:20px;background:#fff3cd;border:1px solid #ffeaa7;border-radius:8px;color:#856404;text-align:center;"><strong>⚠️ Plugin requerido:</strong> Tutor LMS.</div>';
		}
		
		// Obtener opciones de personalización
		$o = function_exists('mc_tutor_get_options') ? mc_tutor_get_options() : [];
		$bg               = $o['buscador_bg'] ?? '#f8f9fa';
		$border_radius    = $o['buscador_border_radius'] ?? 12;
		$padding          = $o['buscador_padding'] ?? 20;
		$gap              = $o['buscador_gap'] ?? 15;
		$input_bg         = $o['buscador_input_bg'] ?? '#ffffff';
		$input_border     = $o['buscador_input_border'] ?? '#e0e0e0';
		$input_text       = $o['buscador_input_text'] ?? '#333333';
		$input_radius     = $o['buscador_input_radius'] ?? 8;
		$input_padding    = $o['buscador_input_padding'] ?? 12;
		$button_start     = $o['buscador_button_gradient_start'] ?? '#015f6a';
		$button_end       = $o['buscador_button_gradient_end'] ?? '#E66638';
		$button_text      = $o['buscador_button_text'] ?? '#ffffff';
		$button_radius    = $o['buscador_button_radius'] ?? 8;
		$button_padding   = $o['buscador_button_padding'] ?? '12px 24px';
		$icon_select      = $o['buscador_icon_select'] ?? '📚';
		$icon_search      = $o['buscador_icon_search'] ?? '🔍';
		$icon_button      = $o['buscador_icon_button'] ?? '🚀';
		$filtro_border    = $o['buscador_filtro_border'] ?? '#015f6a';
		$filtro_text      = $o['buscador_filtro_text'] ?? '#015f6a';
		$filtro_bg_active = $o['buscador_filtro_bg_active'] ?? '#015f6a';
		$filtro_text_active = $o['buscador_filtro_text_active'] ?? '#ffffff';
		
		$categorias = get_terms(array('taxonomy'=>'course-category','hide_empty'=>false,'orderby'=>'name','order'=>'ASC'));
		$courses_page_url = $o['courses_page_url'] ?? site_url('/todos-los-cursos/');
		ob_start(); ?>
		<div class="buscador-cursos-container" style="max-width: 100%; margin: 20px 0;">
			<form action="<?php echo esc_url($courses_page_url); ?>" method="get" class="buscador-cursos-form" style="display: flex; gap: <?php echo $gap; ?>px; flex-wrap: wrap; margin-bottom: 30px; background: <?php echo $bg; ?>; padding: <?php echo $padding; ?>px; border-radius: <?php echo $border_radius; ?>px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
				<select name="course-category" class="buscador-cursos-select" style="padding: <?php echo $input_padding; ?>px; border-radius: <?php echo $input_radius; ?>px; border: 2px solid <?php echo $input_border; ?>; min-width: 200px; flex: 1; background: <?php echo $input_bg; ?>; color: <?php echo $input_text; ?>; font-size: 14px; transition: border-color 0.3s;">
					<option value=""><?php echo $icon_select; ?> Todas las categorías</option>
					<?php if (!empty($categorias) && !is_wp_error($categorias)): foreach ($categorias as $categoria): ?>
						<option value="<?php echo esc_attr($categoria->slug); ?>"><?php echo esc_html($categoria->name); ?> (<?php echo (int)$categoria->count; ?>)</option>
					<?php endforeach; endif; ?>
				</select>
				<input type="text" name="keyword" class="buscador-cursos-input" placeholder="<?php echo $icon_search; ?> ¿Qué deseas aprender...?" style="flex: 3; padding: <?php echo $input_padding; ?>px; border-radius: <?php echo $input_radius; ?>px; border: 2px solid <?php echo $input_border; ?>; background: <?php echo $input_bg; ?>; color: <?php echo $input_text; ?>; font-size: 14px; transition: border-color 0.3s;">
				<input type="hidden" name="course_filter" value="true">
				<input type="hidden" name="loop_content_only" value="false">
				<input type="hidden" name="column_per_row" value="3">
				<input type="hidden" name="course_per_page" value="12">
				<input type="hidden" name="show_pagination" value="false">
				<input type="hidden" name="current_page" value="1">
				<input type="hidden" name="action" value="tutor_course_filter_ajax">
				<button type="submit" class="buscador-cursos-boton" style="background: linear-gradient(135deg, <?php echo $button_start; ?> 0%, <?php echo $button_end; ?> 100%); color: <?php echo $button_text; ?>; border: none; padding: <?php echo $button_padding; ?>; border-radius: <?php echo $button_radius; ?>px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.3s; box-shadow: 0 2px 8px rgba(1,95,106,0.3);"><?php echo $icon_button; ?> Buscar curso</button>
			</form>
			<div class="buscador-cursos-filtros" style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px;">
				<button type="button" class="filtro-rapido" data-categoria="" style="padding: 8px 16px; border: 1px solid <?php echo $filtro_border; ?>; background: white; color: <?php echo $filtro_text; ?>; border-radius: 20px; cursor: pointer; font-size: 12px; transition: all 0.3s;">✨ Todos</button>
				<button type="button" class="filtro-rapido" data-categoria="gratis" style="padding: 8px 16px; border: 1px solid #28a745; background: white; color: #28a745; border-radius: 20px; cursor: pointer; font-size: 12px; transition: all 0.3s;">💰 Gratis</button>
				<button type="button" class="filtro-rapido" data-categoria="populares" style="padding: 8px 16px; border: 1px solid #ffc107; background: white; color: #ffc107; border-radius: 20px; cursor: pointer; font-size: 12px; transition: all 0.3s;">🔥 Populares</button>
			</div>
		</div>
		<style>.buscador-cursos-form:hover .buscador-cursos-select,.buscador-cursos-form:hover .buscador-cursos-input{border-color:<?php echo $filtro_border; ?>}.buscador-cursos-boton:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(1,95,106,0.4)}.filtro-rapido:hover{transform:translateY(-1px);box-shadow:0 2px 8px rgba(0,0,0,0.1)}.filtro-rapido.active{background:<?php echo $filtro_bg_active; ?>!important;color:<?php echo $filtro_text_active; ?>!important}@media (max-width:768px){.buscador-cursos-form{flex-direction:column}.buscador-cursos-select,.buscador-cursos-input{min-width:100%}}</style>
		<script>document.addEventListener('DOMContentLoaded',function(){const f=document.querySelector('.buscador-cursos-form'),s=document.querySelector('.buscador-cursos-select');document.querySelectorAll('.filtro-rapido').forEach(b=>b.addEventListener('click',function(){document.querySelectorAll('.filtro-rapido').forEach(x=>x.classList.remove('active'));this.classList.add('active');const c=this.getAttribute('data-categoria');if(s){s.value=c;}if(c===''){const i=document.querySelector('.buscador-cursos-input');if(i) i.value='';}}));});</script>
		<?php return ob_get_clean();
	});
});
