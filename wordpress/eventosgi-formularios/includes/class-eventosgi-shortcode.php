<?php
/**
 * Shortcode [eventosgi_formulario] — renderiza o formulário e processa o envio.
 */

defined( 'ABSPATH' ) || exit;

class EventosGI_Shortcode {

	/** Prefixo dos transients que carregam o resultado de um envio entre o POST e o redirecionamento. */
	const RESULTADO_PREFIXO = 'eventosgi_res_';

	public function registrar() {
		add_shortcode( 'eventosgi_formulario', array( $this, 'renderizar' ) );
		add_action( 'template_redirect', array( $this, 'processar_envio' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'registrar_estilo' ) );
	}

	public function registrar_estilo() {
		wp_register_style( 'eventosgi-formularios', EVENTOSGI_FORM_URL . 'assets/eventosgi-formularios.css', array(), EVENTOSGI_FORM_VERSAO );
	}

	/**
	 * Recebe o POST do formulário, envia à API e redireciona (padrão POST/Redirect/GET)
	 * para que um recarregamento da página não repita a inscrição.
	 */
	public function processar_envio() {
		if ( ! isset( $_POST['eventosgi_form_acao'], $_POST['eventosgi_form_id'] ) ) {
			return;
		}

		$atividade_id = (int) $_POST['eventosgi_form_id'];
		if ( $atividade_id < 1 || ! isset( $_POST['eventosgi_form_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['eventosgi_form_nonce'] ) ), 'eventosgi_form_' . $atividade_id ) ) {
			return;
		}

		$api      = new EventosGI_Api();
		$estrutura = $api->formulario( $atividade_id, true );

		if ( is_wp_error( $estrutura ) ) {
			$this->redirecionar( $atividade_id, array( 'tipo' => 'erro', 'mensagem' => $estrutura->get_error_message() ) );
		}

		// Campo isca: preenchido apenas por robôs. Responde como sucesso para não dar pistas.
		if ( ! empty( $_POST['eventosgi_confirmacao'] ) ) {
			$this->redirecionar( $atividade_id, array( 'tipo' => 'sucesso', 'mensagem' => $estrutura['estado']['mensagem'] ? $estrutura['estado']['mensagem'] : __( 'Inscrição recebida.', 'eventosgi-formularios' ) ) );
		}

		list( $campos, $arquivos ) = $this->coletar_valores( $estrutura['campos'] );

		$resposta = $api->inscrever( $atividade_id, $campos, $arquivos );

		if ( is_wp_error( $resposta ) ) {
			$dados = $resposta->get_error_data();
			$erros = $this->agrupar_erros( isset( $dados['errors'] ) && is_array( $dados['errors'] ) ? $dados['errors'] : array() );

			$this->redirecionar(
				$atividade_id,
				array(
					'tipo'     => 'erro',
					'mensagem' => $erros
						? __( 'Confira os campos destacados abaixo e envie novamente.', 'eventosgi-formularios' )
						: $resposta->get_error_message(),
					'erros'    => $erros,
					'valores'  => $campos,
				)
			);
		}

		if ( empty( $resposta['sucesso'] ) ) {
			$this->redirecionar( $atividade_id, array( 'tipo' => 'aviso', 'mensagem' => $resposta['mensagem'], 'valores' => $campos ) );
		}

		$this->redirecionar( $atividade_id, array( 'tipo' => 'sucesso', 'mensagem' => $resposta['mensagem'] ) );
	}

	/**
	 * Separa o que veio no POST entre valores simples e arquivos, seguindo a definição de campos da atividade.
	 *
	 * @return array{0: array, 1: array}
	 */
	private function coletar_valores( array $definicao ) {
		$campos   = array();
		$arquivos = array();

		foreach ( $definicao as $campo ) {
			$nome = $campo['nome'];

			if ( 'file' === $campo['tipo'] ) {
				$enviados = $this->normalizar_arquivos( $nome );
				if ( $enviados ) {
					$arquivos[ $nome ] = array_slice( $enviados, 0, (int) $campo['max_arquivos'] );
				}
				continue;
			}

			if ( ! isset( $_POST[ $nome ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				continue;
			}

			$valor = wp_unslash( $_POST[ $nome ] ); // phpcs:ignore WordPress.Security

			if ( is_array( $valor ) ) {
				$limpos = array_values( array_filter( array_map( 'sanitize_text_field', $valor ), 'strlen' ) );
				if ( $limpos ) {
					$campos[ $nome ] = $limpos;
				}
				continue;
			}

			$valor = 'textarea' === $campo['tipo'] ? sanitize_textarea_field( $valor ) : sanitize_text_field( $valor );
			if ( '' !== $valor ) {
				$campos[ $nome ] = $valor;
			}
		}

		return array( $campos, $arquivos );
	}

	/**
	 * Converte o $_FILES do campo — que muda de formato entre envio único e múltiplo — em uma lista uniforme.
	 */
	private function normalizar_arquivos( $nome ) {
		if ( empty( $_FILES[ $nome ] ) ) {
			return array();
		}

		$origem = $_FILES[ $nome ]; // phpcs:ignore WordPress.Security
		$lista  = array();

		if ( is_array( $origem['name'] ) ) {
			foreach ( array_keys( $origem['name'] ) as $indice ) {
				$lista[] = array(
					'name'     => $origem['name'][ $indice ],
					'type'     => $origem['type'][ $indice ],
					'tmp_name' => $origem['tmp_name'][ $indice ],
					'error'    => $origem['error'][ $indice ],
				);
			}
		} else {
			$lista[] = $origem;
		}

		return array_values(
			array_filter(
				$lista,
				function ( $arquivo ) {
					return UPLOAD_ERR_OK === (int) $arquivo['error'] && is_uploaded_file( $arquivo['tmp_name'] );
				}
			)
		);
	}

	/**
	 * O Laravel reporta erros de itens de lista como "arquivos.0"; aqui eles voltam
	 * para o campo de origem, que é como o formulário os exibe.
	 */
	private function agrupar_erros( array $erros ) {
		$agrupados = array();

		foreach ( $erros as $chave => $mensagens ) {
			$campo = strtok( (string) $chave, '.' );
			$agrupados[ $campo ] = array_values( array_unique( array_merge( isset( $agrupados[ $campo ] ) ? $agrupados[ $campo ] : array(), (array) $mensagens ) ) );
		}

		return $agrupados;
	}

	/** Guarda o resultado do envio e redireciona de volta para a página do formulário. */
	private function redirecionar( $atividade_id, array $resultado ) {
		$chave = wp_generate_password( 20, false );
		set_transient( self::RESULTADO_PREFIXO . $chave, $resultado, 5 * MINUTE_IN_SECONDS );

		// Limpa um resultado anterior da URL antes de anexar o novo, senao o parametro ficaria duplicado.
		$base = remove_query_arg( array( 'egi_form', 'egi_resultado' ), home_url( add_query_arg( array() ) ) );

		$destino = add_query_arg(
			array(
				'egi_form'      => $atividade_id,
				'egi_resultado' => $chave,
			),
			$base
		);

		wp_safe_redirect( $destino . '#eventosgi-formulario-' . $atividade_id );
		exit;
	}

	/** Resultado do último envio desta atividade, se o visitante acabou de ser redirecionado. */
	private function resultado( $atividade_id ) {
		if ( empty( $_GET['egi_resultado'] ) || (int) ( isset( $_GET['egi_form'] ) ? $_GET['egi_form'] : 0 ) !== (int) $atividade_id ) { // phpcs:ignore WordPress.Security.NonceVerification
			return null;
		}

		$chave     = sanitize_text_field( wp_unslash( $_GET['egi_resultado'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$resultado = get_transient( self::RESULTADO_PREFIXO . $chave );

		return is_array( $resultado ) ? $resultado : null;
	}

	/**
	 * @param array $atributos Atributos do shortcode.
	 * @return string
	 */
	public function renderizar( $atributos ) {
		$atributos = shortcode_atts(
			array(
				'id'        => 0,
				'atividade' => 0,
				'titulo'    => 'sim',
				'conteudo'  => 'sim',
			),
			$atributos,
			'eventosgi_formulario'
		);

		$atividade_id = (int) ( $atributos['id'] ? $atributos['id'] : $atributos['atividade'] );

		if ( $atividade_id < 1 ) {
			return $this->aviso( __( 'Informe o ID da atividade: [eventosgi_formulario id="1"].', 'eventosgi-formularios' ) );
		}

		$estrutura = ( new EventosGI_Api() )->formulario( $atividade_id );

		if ( is_wp_error( $estrutura ) ) {
			return $this->aviso(
				current_user_can( 'manage_options' )
					? $estrutura->get_error_message()
					: __( 'O formulário de inscrição não está disponível no momento.', 'eventosgi-formularios' )
			);
		}

		wp_enqueue_style( 'eventosgi-formularios' );

		$resultado = $this->resultado( $atividade_id );
		$erros     = ( $resultado && ! empty( $resultado['erros'] ) ) ? $resultado['erros'] : array();
		$valores   = ( $resultado && ! empty( $resultado['valores'] ) ) ? $resultado['valores'] : array();
		$posicao   = $estrutura['editor']['posicao'];
		$conteudo  = 'sim' === $atributos['conteudo'] ? $estrutura['editor']['conteudo'] : '';
		$aberto    = ! empty( $estrutura['estado']['aberto'] );

		// Depois de uma inscricao concluida so resta a mensagem de sucesso; o formulario sai da tela.
		$mostrar_form = $aberto && ! ( $resultado && 'sucesso' === $resultado['tipo'] );

		ob_start();
		?>
		<div class="eventosgi-formulario" id="eventosgi-formulario-<?php echo esc_attr( $atividade_id ); ?>">
			<?php if ( 'sim' === $atributos['titulo'] ) : ?>
				<h2 class="eventosgi-titulo"><?php echo esc_html( $estrutura['titulo'] ); ?></h2>
				<?php if ( '' !== $estrutura['subtitulo'] ) : ?>
					<p class="eventosgi-subtitulo"><?php echo esc_html( $estrutura['subtitulo'] ); ?></p>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( $resultado ) : ?>
				<div class="eventosgi-alerta eventosgi-alerta--<?php echo esc_attr( $resultado['tipo'] ); ?>">
					<?php echo esc_html( $resultado['mensagem'] ); ?>
				</div>
			<?php endif; ?>

			<?php if ( ! $aberto ) : ?>
				<div class="eventosgi-alerta eventosgi-alerta--aviso"><?php echo esc_html( $estrutura['estado']['mensagem'] ); ?></div>
			<?php endif; ?>

			<?php if ( $mostrar_form ) : ?>
			<form method="post" enctype="multipart/form-data" class="eventosgi-form" action="<?php echo esc_url( remove_query_arg( array( 'egi_form', 'egi_resultado' ) ) ); ?>">
				<?php wp_nonce_field( 'eventosgi_form_' . $atividade_id, 'eventosgi_form_nonce' ); ?>
				<input type="hidden" name="eventosgi_form_acao" value="inscrever">
				<input type="hidden" name="eventosgi_form_id" value="<?php echo esc_attr( $atividade_id ); ?>">
				<div class="eventosgi-isca" aria-hidden="true">
					<label><?php esc_html_e( 'Deixe este campo em branco', 'eventosgi-formularios' ); ?>
						<input type="text" name="eventosgi_confirmacao" value="" tabindex="-1" autocomplete="off">
					</label>
				</div>

				<?php if ( $conteudo && in_array( $posicao, array( 'acima', 'esquerda' ), true ) ) : ?>
					<div class="eventosgi-conteudo"><?php echo wp_kses_post( $conteudo ); ?></div>
				<?php endif; ?>

				<div class="eventosgi-campos">
					<?php foreach ( $estrutura['campos'] as $campo ) : ?>
						<?php $this->campo( $campo, isset( $valores[ $campo['nome'] ] ) ? $valores[ $campo['nome'] ] : null, isset( $erros[ $campo['nome'] ] ) ? $erros[ $campo['nome'] ] : array() ); ?>
					<?php endforeach; ?>
				</div>

				<?php if ( $conteudo && in_array( $posicao, array( 'abaixo', 'direita' ), true ) ) : ?>
					<div class="eventosgi-conteudo"><?php echo wp_kses_post( $conteudo ); ?></div>
				<?php endif; ?>

				<button type="submit" class="eventosgi-botao"><?php esc_html_e( 'Enviar inscrição', 'eventosgi-formularios' ); ?></button>
			</form>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/** Renderiza um campo do formulário conforme o tipo configurado na atividade. */
	private function campo( array $campo, $valor, array $erros ) {
		$id          = 'eventosgi-' . sanitize_html_class( $campo['nome'] );
		$obrigatorio = ! empty( $campo['obrigatorio'] );
		$tipo        = $campo['tipo'];
		$multiplo    = 'file' === $tipo && $campo['max_arquivos'] > 1;
		$nome        = $campo['nome'] . ( ( $multiplo || 'multiselect' === $tipo || 'checkbox' === $tipo ) ? '[]' : '' );
		$classe      = 'eventosgi-campo eventosgi-campo--' . sanitize_html_class( $tipo ) . ( $erros ? ' eventosgi-campo--erro' : '' );
		?>
		<div class="<?php echo esc_attr( $classe ); ?>">
			<label class="eventosgi-label" for="<?php echo esc_attr( $id ); ?>">
				<?php echo esc_html( $campo['label'] ); ?>
				<?php if ( $obrigatorio ) : ?><span class="eventosgi-obrigatorio" aria-hidden="true">*</span><?php endif; ?>
			</label>

			<?php if ( 'textarea' === $tipo ) : ?>
				<textarea class="eventosgi-controle" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $nome ); ?>" rows="4"
					placeholder="<?php echo esc_attr( $campo['placeholder'] ); ?>" <?php echo $obrigatorio ? 'required' : ''; ?>><?php echo esc_textarea( is_string( $valor ) ? $valor : '' ); ?></textarea>

			<?php elseif ( in_array( $tipo, array( 'select', 'multiselect' ), true ) ) : ?>
				<select class="eventosgi-controle" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $nome ); ?>"
					<?php echo 'multiselect' === $tipo ? 'multiple' : ''; ?> <?php echo $obrigatorio ? 'required' : ''; ?>>
					<?php if ( 'select' === $tipo ) : ?>
						<option value=""><?php echo esc_html( $campo['placeholder'] ? $campo['placeholder'] : __( 'Selecione…', 'eventosgi-formularios' ) ); ?></option>
					<?php endif; ?>
					<?php foreach ( $campo['opcoes'] as $opcao ) : ?>
						<option value="<?php echo esc_attr( $opcao ); ?>" <?php selected( $this->marcado( $valor, $opcao ) ); ?>><?php echo esc_html( $opcao ); ?></option>
					<?php endforeach; ?>
				</select>

			<?php elseif ( in_array( $tipo, array( 'radio', 'checkbox' ), true ) ) : ?>
				<div class="eventosgi-opcoes" role="group">
					<?php foreach ( $campo['opcoes'] as $indice => $opcao ) : ?>
						<label class="eventosgi-opcao">
							<input type="<?php echo esc_attr( $tipo ); ?>" name="<?php echo esc_attr( $nome ); ?>" value="<?php echo esc_attr( $opcao ); ?>"
								<?php checked( $this->marcado( $valor, $opcao ) ); ?>
								<?php echo ( $obrigatorio && 'radio' === $tipo ) ? 'required' : ''; ?>
								id="<?php echo esc_attr( $id . '-' . $indice ); ?>">
							<span><?php echo esc_html( $opcao ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>

			<?php elseif ( 'file' === $tipo ) : ?>
				<input class="eventosgi-controle" type="file" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $nome ); ?>"
					<?php echo $multiplo ? 'multiple' : ''; ?> <?php echo $obrigatorio ? 'required' : ''; ?>
					<?php if ( $campo['aceitos'] ) : ?>accept="<?php echo esc_attr( '.' . implode( ',.', $campo['aceitos'] ) ); ?>"<?php endif; ?>>
				<?php if ( $campo['aceitos'] || $multiplo ) : ?>
					<small class="eventosgi-ajuda">
						<?php
						$partes = array();
						if ( $campo['aceitos'] ) {
							$partes[] = sprintf( /* translators: %s: lista de extensões. */ __( 'Formatos aceitos: %s.', 'eventosgi-formularios' ), strtoupper( implode( ', ', $campo['aceitos'] ) ) );
						}
						if ( $multiplo ) {
							$partes[] = sprintf( /* translators: %d: quantidade de arquivos. */ __( 'Até %d arquivos.', 'eventosgi-formularios' ), (int) $campo['max_arquivos'] );
						}
						echo esc_html( implode( ' ', $partes ) );
						?>
					</small>
				<?php endif; ?>

			<?php else : ?>
				<input class="eventosgi-controle" type="<?php echo esc_attr( $this->tipo_html( $campo ) ); ?>" id="<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $nome ); ?>" value="<?php echo esc_attr( is_string( $valor ) ? $valor : '' ); ?>"
					placeholder="<?php echo esc_attr( $campo['placeholder'] ); ?>" <?php echo $obrigatorio ? 'required' : ''; ?>
					<?php echo 'cpf' === $campo['validacao'] ? 'inputmode="numeric" pattern="\d{11}"' : ''; ?>>
			<?php endif; ?>

			<?php foreach ( $erros as $erro ) : ?>
				<small class="eventosgi-erro"><?php echo esc_html( $erro ); ?></small>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/** Traduz a validação configurada na atividade para o tipo de input mais adequado. */
	private function tipo_html( array $campo ) {
		if ( 'email' === $campo['validacao'] ) {
			return 'email';
		}
		if ( 'telefone' === $campo['validacao'] ) {
			return 'tel';
		}

		return in_array( $campo['tipo'], array( 'text', 'email', 'tel', 'number', 'date', 'time', 'datetime-local', 'url', 'password' ), true )
			? $campo['tipo']
			: 'text';
	}

	private function marcado( $valor, $opcao ) {
		return is_array( $valor ) ? in_array( (string) $opcao, array_map( 'strval', $valor ), true ) : (string) $valor === (string) $opcao;
	}

	private function aviso( $mensagem ) {
		return '<div class="eventosgi-alerta eventosgi-alerta--aviso">' . esc_html( $mensagem ) . '</div>';
	}
}
