<?php
/**
 * Tela de ajustes do plugin e teste de conexão com o sistema de eventos.
 */

defined( 'ABSPATH' ) || exit;

class EventosGI_Admin {

	public function registrar() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'campos' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( EVENTOSGI_FORM_ARQUIVO ), array( $this, 'link_ajustes' ) );
	}

	public function link_ajustes( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=eventosgi-formularios' ) ) . '">' . esc_html__( 'Ajustes', 'eventosgi-formularios' ) . '</a>' );

		return $links;
	}

	public function menu() {
		add_options_page(
			__( 'EventosGI — Formulários', 'eventosgi-formularios' ),
			__( 'EventosGI', 'eventosgi-formularios' ),
			'manage_options',
			'eventosgi-formularios',
			array( $this, 'pagina' )
		);
	}

	public function campos() {
		register_setting(
			'eventosgi_formularios',
			EVENTOSGI_FORM_OPCOES,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitizar' ),
				'default'           => array(),
			)
		);

		add_settings_section( 'eventosgi_conexao', __( 'Conexão', 'eventosgi-formularios' ), function () {
			echo '<p>' . esc_html__( 'Dados de acesso à API pública de formulários do sistema Gestão de Eventos.', 'eventosgi-formularios' ) . '</p>';
		}, 'eventosgi-formularios' );

		add_settings_field( 'url_base', __( 'URL do sistema', 'eventosgi-formularios' ), array( $this, 'campo_url' ), 'eventosgi-formularios', 'eventosgi_conexao' );
		add_settings_field( 'token', __( 'Token da API', 'eventosgi-formularios' ), array( $this, 'campo_token' ), 'eventosgi-formularios', 'eventosgi_conexao' );
		add_settings_field( 'cache_minutos', __( 'Cache do formulário', 'eventosgi-formularios' ), array( $this, 'campo_cache' ), 'eventosgi-formularios', 'eventosgi_conexao' );
	}

	public function sanitizar( $entrada ) {
		EventosGI_Api::limpar_cache();

		return array(
			'url_base'      => untrailingslashit( esc_url_raw( trim( (string) ( isset( $entrada['url_base'] ) ? $entrada['url_base'] : '' ) ) ) ),
			'token'         => sanitize_text_field( trim( (string) ( isset( $entrada['token'] ) ? $entrada['token'] : '' ) ) ),
			'cache_minutos' => max( 0, min( 1440, (int) ( isset( $entrada['cache_minutos'] ) ? $entrada['cache_minutos'] : 5 ) ) ),
		);
	}

	public function campo_url() {
		$opcoes = eventosgi_form_opcoes();
		?>
		<input type="url" class="regular-text code" name="<?php echo esc_attr( EVENTOSGI_FORM_OPCOES ); ?>[url_base]"
			value="<?php echo esc_attr( $opcoes['url_base'] ); ?>" placeholder="https://eventos.exemplo.com.br">
		<p class="description"><?php esc_html_e( 'Endereço do sistema, sem barra no final e sem /api.', 'eventosgi-formularios' ); ?></p>
		<?php
	}

	public function campo_token() {
		$opcoes = eventosgi_form_opcoes();
		?>
		<input type="password" class="regular-text code" name="<?php echo esc_attr( EVENTOSGI_FORM_OPCOES ); ?>[token]"
			value="<?php echo esc_attr( $opcoes['token'] ); ?>" autocomplete="off">
		<p class="description"><?php esc_html_e( 'Valor de FORMULARIOS_API_TOKEN no arquivo .env do sistema de eventos.', 'eventosgi-formularios' ); ?></p>
		<?php
	}

	public function campo_cache() {
		$opcoes = eventosgi_form_opcoes();
		?>
		<input type="number" class="small-text" min="0" max="1440" name="<?php echo esc_attr( EVENTOSGI_FORM_OPCOES ); ?>[cache_minutos]"
			value="<?php echo esc_attr( $opcoes['cache_minutos'] ); ?>">
		<?php esc_html_e( 'minutos', 'eventosgi-formularios' ); ?>
		<p class="description"><?php esc_html_e( 'Por quanto tempo a estrutura do formulário fica guardada. Use 0 para sempre consultar o sistema.', 'eventosgi-formularios' ); ?></p>
		<?php
	}

	public function pagina() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$teste = $this->testar();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'EventosGI — Formulários', 'eventosgi-formularios' ); ?></h1>

			<?php if ( $teste ) : ?>
				<div class="notice notice-<?php echo esc_attr( $teste['tipo'] ); ?>"><p><?php echo esc_html( $teste['mensagem'] ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'eventosgi_formularios' );
				do_settings_sections( 'eventosgi-formularios' );
				submit_button();
				?>
			</form>

			<h2><?php esc_html_e( 'Como usar', 'eventosgi-formularios' ); ?></h2>
			<p><?php esc_html_e( 'Adicione o shortcode abaixo em qualquer post ou página, trocando o ID pelo da atividade desejada:', 'eventosgi-formularios' ); ?></p>
			<p><code>[eventosgi_formulario id="1"]</code></p>
			<p><?php esc_html_e( 'Atributos opcionais: titulo="nao" oculta o título e o subtítulo; conteudo="nao" oculta o texto livre configurado na atividade.', 'eventosgi-formularios' ); ?></p>

			<h2><?php esc_html_e( 'Testar uma atividade', 'eventosgi-formularios' ); ?></h2>
			<form method="get">
				<input type="hidden" name="page" value="eventosgi-formularios">
				<?php wp_nonce_field( 'eventosgi_testar', 'eventosgi_teste_nonce' ); ?>
				<input type="number" min="1" name="eventosgi_testar" class="small-text"
					value="<?php echo esc_attr( isset( $_GET['eventosgi_testar'] ) ? (int) $_GET['eventosgi_testar'] : '' ); // phpcs:ignore WordPress.Security.NonceVerification ?>">
				<?php submit_button( __( 'Testar', 'eventosgi-formularios' ), 'secondary', '', false ); ?>
			</form>
		</div>
		<?php
	}

	/** Consulta uma atividade informada pelo administrador para conferir a configuração. */
	private function testar() {
		if ( empty( $_GET['eventosgi_testar'] ) || empty( $_GET['eventosgi_teste_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['eventosgi_teste_nonce'] ) ), 'eventosgi_testar' ) ) {
			return null;
		}

		$id        = (int) $_GET['eventosgi_testar'];
		$estrutura = ( new EventosGI_Api() )->formulario( $id, true );

		if ( is_wp_error( $estrutura ) ) {
			return array( 'tipo' => 'error', 'mensagem' => sprintf( /* translators: 1: ID da atividade, 2: mensagem de erro. */ __( 'Atividade %1$d: %2$s', 'eventosgi-formularios' ), $id, $estrutura->get_error_message() ) );
		}

		return array(
			'tipo'     => 'success',
			'mensagem' => sprintf(
				/* translators: 1: título do formulário, 2: quantidade de campos, 3: situação. */
				__( 'Conexão bem-sucedida. Formulário "%1$s" com %2$d campo(s). Situação: %3$s', 'eventosgi-formularios' ),
				$estrutura['titulo'],
				count( $estrutura['campos'] ),
				empty( $estrutura['estado']['aberto'] ) ? $estrutura['estado']['mensagem'] : __( 'aberto para inscrições.', 'eventosgi-formularios' )
			),
		);
	}
}
