<?php
/**
 * Plugin Name:       EventosGI — Eventos e Formulários
 * Plugin URI:        https://github.com/nossafco/eventosgi
 * Description:       Exibe páginas de eventos e formulários de atividades através dos shortcodes [eventosgi_evento] e [eventosgi_formulario].
 * Version:           1.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Nossa FCO
 * License:           GPL-2.0-or-later
 * Text Domain:       eventosgi-formularios
 */

defined( 'ABSPATH' ) || exit;

define( 'EVENTOSGI_FORM_VERSAO', '1.2.0' );
define( 'EVENTOSGI_FORM_ARQUIVO', __FILE__ );
define( 'EVENTOSGI_FORM_DIR', plugin_dir_path( __FILE__ ) );
define( 'EVENTOSGI_FORM_URL', plugin_dir_url( __FILE__ ) );
define( 'EVENTOSGI_FORM_OPCOES', 'eventosgi_formularios_opcoes' );

require_once EVENTOSGI_FORM_DIR . 'includes/class-eventosgi-api.php';
require_once EVENTOSGI_FORM_DIR . 'includes/class-eventosgi-shortcode.php';
require_once EVENTOSGI_FORM_DIR . 'includes/class-eventosgi-evento.php';
require_once EVENTOSGI_FORM_DIR . 'includes/class-eventosgi-admin.php';

/**
 * Configuração salva na tela de ajustes, já com os valores padrão.
 */
function eventosgi_form_opcoes() {
	$padrao = array(
		'url_base'      => '',
		'token'         => '',
		'cache_minutos' => 5,
	);

	return wp_parse_args( (array) get_option( EVENTOSGI_FORM_OPCOES, array() ), $padrao );
}

add_action(
	'plugins_loaded',
	function () {
		( new EventosGI_Shortcode() )->registrar();
		( new EventosGI_Evento() )->registrar();

		if ( is_admin() ) {
			( new EventosGI_Admin() )->registrar();
		}
	}
);

register_deactivation_hook(
	__FILE__,
	function () {
		EventosGI_Api::limpar_cache();
	}
);
