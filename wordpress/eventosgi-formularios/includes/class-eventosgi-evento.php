<?php
/**
 * Shortcode [eventosgi_evento] — exibe a página publicada de um evento em um iframe isolado.
 */

defined( 'ABSPATH' ) || exit;

class EventosGI_Evento {

	public function registrar() {
		add_shortcode( 'eventosgi_evento', array( $this, 'renderizar' ) );
	}

	/**
	 * @param array $atributos Atributos do shortcode.
	 * @return string
	 */
	public function renderizar( $atributos ) {
		$atributos = shortcode_atts(
			array(
				'id'     => 0,
				'evento' => 0,
				'altura' => 900,
			),
			$atributos,
			'eventosgi_evento'
		);

		$evento_id = (int) ( $atributos['id'] ? $atributos['id'] : $atributos['evento'] );
		if ( $evento_id < 1 ) {
			return '<p class="eventosgi-alerta eventosgi-alerta--aviso">' . esc_html__( 'Informe o ID do evento: [eventosgi_evento id="1"].', 'eventosgi-formularios' ) . '</p>';
		}

		$altura = max( 300, min( 3000, (int) $atributos['altura'] ) );
		$origem = ( new EventosGI_Api() )->url_pagina_evento( $evento_id );
		if ( is_wp_error( $origem ) ) {
			return '<p class="eventosgi-alerta eventosgi-alerta--aviso">' . esc_html( $origem->get_error_message() ) . '</p>';
		}

		wp_enqueue_style( 'eventosgi-formularios' );

		return sprintf(
			'<iframe class="eventosgi-evento" src="%1$s" title="%2$s" height="%3$d" loading="lazy" sandbox="allow-forms allow-popups allow-popups-to-escape-sandbox allow-scripts allow-top-navigation-by-user-activation"></iframe>',
			esc_url( $origem ),
			esc_attr__( 'Página do evento', 'eventosgi-formularios' ),
			$altura
		);
	}
}
