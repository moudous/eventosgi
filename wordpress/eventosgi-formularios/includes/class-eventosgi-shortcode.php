<?php
/**
 * Shortcode [eventosgi_formulario] — renderiza o formulário e processa o envio.
 */

defined( 'ABSPATH' ) || exit;

class EventosGI_Shortcode {

	/** Prefixo dos transients que carregam o resultado de um envio entre o POST e o redirecionamento. */
	const RESULTADO_PREFIXO = 'eventosgi_res_';

	/** Prefixo dos transients que guardam a identificação do visitante. */
	const SESSAO_PREFIXO = 'eventosgi_ident_';

	/** Prefixo do cookie que aponta para o transient da identificação. */
	const SESSAO_COOKIE = 'eventosgi_sessao_';

	/** Campos de participantes aceitos no bloco "Seus dados". */
	const CAMPOS_PARTICIPANTE = array( 'nome', 'cpf', 'sexo', 'instituicao_ensino', 'email2', 'email_institucional', 'grupo' );

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

		$acao = sanitize_key( wp_unslash( $_POST['eventosgi_form_acao'] ) );
		$api  = new EventosGI_Api();

		if ( 'solicitar_codigo' === $acao ) {
			$this->processar_codigo( $api, $atividade_id );
		}

		if ( 'identificar' === $acao ) {
			$this->processar_identificacao( $api, $atividade_id );
		}

		if ( 'trocar_email' === $acao ) {
			$sessao = $this->sessao( $atividade_id );
			if ( $sessao ) {
				$api->encerrar_identificacao( $atividade_id, $sessao['token'] );
			}
			$this->limpar_sessao( $atividade_id );
			$this->redirecionar( $atividade_id, array() );
		}

		$this->processar_inscricao( $api, $atividade_id );
	}

	/** Pede o envio do código para o e-mail digitado na etapa de identificação. */
	private function processar_codigo( EventosGI_Api $api, $atividade_id ) {
		$email = isset( $_POST['eventosgi_email'] ) ? sanitize_text_field( wp_unslash( $_POST['eventosgi_email'] ) ) : '';
		$isca  = isset( $_POST['eventosgi_isca'] ) ? trim( (string) wp_unslash( $_POST['eventosgi_isca'] ) ) : '';

		// Campo isca preenchido: responde como se tivesse enviado, sem chamar a API.
		if ( '' !== $isca ) {
			$this->redirecionar(
				$atividade_id,
				array(
					'tipo'     => 'sucesso',
					'mensagem' => sprintf(
						/* translators: %s: endereço de e-mail. */
						__( 'Enviamos o código para %s. Ele vale por poucos minutos.', 'eventosgi-formularios' ),
						$email
					),
					'email'    => $email,
					'etapa'    => 'identificacao',
				)
			);
		}

		$resposta = $api->solicitar_codigo( $atividade_id, $email );

		if ( is_wp_error( $resposta ) ) {
			$this->redirecionar(
				$atividade_id,
				array(
					'tipo'     => 'erro',
					'mensagem' => $this->mensagem_de_erro( $resposta, __( 'Não foi possível enviar o código. Confira o e-mail informado.', 'eventosgi-formularios' ) ),
					'email'    => $email,
				)
			);
		}

		$this->redirecionar(
			$atividade_id,
			array(
				'tipo'     => 'sucesso',
				'mensagem' => sprintf(
					/* translators: %s: endereço de e-mail. */
					__( 'Enviamos o código para %s. Ele vale por poucos minutos.', 'eventosgi-formularios' ),
					isset( $resposta['email'] ) ? $resposta['email'] : $email
				),
				'email'    => isset( $resposta['email'] ) ? $resposta['email'] : $email,
				'etapa'    => 'identificacao',
			)
		);
	}

	/** Confere o código digitado e guarda a identificação devolvida pela API. */
	private function processar_identificacao( EventosGI_Api $api, $atividade_id ) {
		$email    = isset( $_POST['eventosgi_email'] ) ? sanitize_text_field( wp_unslash( $_POST['eventosgi_email'] ) ) : '';
		$codigo   = isset( $_POST['eventosgi_codigo'] ) ? sanitize_text_field( wp_unslash( $_POST['eventosgi_codigo'] ) ) : '';
		$resposta = $api->identificar( $atividade_id, $email, $codigo );

		if ( is_wp_error( $resposta ) ) {
			$this->redirecionar(
				$atividade_id,
				array(
					'tipo'     => 'erro',
					'mensagem' => $this->mensagem_de_erro( $resposta, __( 'Não foi possível confirmar o código.', 'eventosgi-formularios' ) ),
					'email'    => $email,
					'etapa'    => 'identificacao',
				)
			);
		}

		$this->guardar_sessao(
			$atividade_id,
			array(
				'token'        => $resposta['token'],
				'participante' => isset( $resposta['participante'] ) ? (array) $resposta['participante'] : array(),
				'ja_inscrito'  => ! empty( $resposta['ja_inscrito'] ),
			),
			$resposta['expira_em']
		);

		// Uma inscrição por participante: quem já se inscreveu não chega a ver o formulário.
		// O aviso sai do bloco permanente na renderização, então aqui não vai mensagem —
		// repetir os dois deixaria o texto duplicado na tela.
		if ( ! empty( $resposta['ja_inscrito'] ) ) {
			$this->redirecionar( $atividade_id, array() );
		}

		$unificados = isset( $resposta['unificados'] ) ? (int) $resposta['unificados'] : 0;

		if ( $unificados > 0 ) {
			$mensagem = sprintf(
				/* translators: %d: quantidade de cadastros. */
				__( 'Encontramos %d cadastros com este e-mail e eles foram unificados. Confira os dados abaixo.', 'eventosgi-formularios' ),
				$unificados + 1
			);
		} elseif ( ! empty( $resposta['criado'] ) ) {
			$mensagem = __( 'E-mail confirmado. Complete o seu cadastro abaixo.', 'eventosgi-formularios' );
		} else {
			$mensagem = __( 'E-mail confirmado. Confira e complete os seus dados abaixo.', 'eventosgi-formularios' );
		}

		$this->redirecionar( $atividade_id, array( 'tipo' => 'info', 'mensagem' => $mensagem ) );
	}

	/**
	 * Recebe o POST do formulário, envia à API e redireciona (padrão POST/Redirect/GET)
	 * para que um recarregamento da página não repita a inscrição.
	 */
	private function processar_inscricao( EventosGI_Api $api, $atividade_id ) {
		$estrutura = $api->formulario( $atividade_id, true );

		if ( is_wp_error( $estrutura ) ) {
			$this->redirecionar( $atividade_id, array( 'tipo' => 'erro', 'mensagem' => $estrutura->get_error_message() ) );
		}

		// Campo isca: preenchido apenas por robôs. Responde como sucesso para não dar pistas.
		if ( ! empty( $_POST['eventosgi_confirmacao'] ) ) {
			$this->redirecionar( $atividade_id, array( 'tipo' => 'sucesso', 'mensagem' => $estrutura['estado']['mensagem'] ? $estrutura['estado']['mensagem'] : __( 'Inscrição recebida.', 'eventosgi-formularios' ) ) );
		}

		$sessao = $this->sessao( $atividade_id );

		if ( ! $sessao ) {
			$this->limpar_sessao( $atividade_id );
			$this->redirecionar(
				$atividade_id,
				array( 'tipo' => 'aviso', 'mensagem' => __( 'Confirme o seu e-mail novamente para enviar a inscrição.', 'eventosgi-formularios' ) )
			);
		}

		list( $campos, $arquivos ) = $this->coletar_valores( $estrutura['campos'] );
		$campos                    = array_merge( $campos, $this->coletar_participante() );

		$resposta = $api->inscrever( $atividade_id, $campos, $arquivos, $sessao['token'] );

		if ( is_wp_error( $resposta ) ) {
			$dados = $resposta->get_error_data();
			$erros = $this->agrupar_erros( isset( $dados['errors'] ) && is_array( $dados['errors'] ) ? $dados['errors'] : array() );

			// 401: o token venceu ou já foi usado; a identificação precisa recomeçar.
			if ( 'eventosgi_http_401' === $resposta->get_error_code() ) {
				$this->limpar_sessao( $atividade_id );
			}

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
			// Duplicada: guarda na sessão para o formulário sumir da tela e deixa o aviso
			// a cargo do bloco permanente, senão a mesma frase apareceria duas vezes.
			if ( isset( $resposta['motivo'] ) && 'duplicada' === $resposta['motivo'] ) {
				$sessao['ja_inscrito'] = true;
				$this->guardar_sessao( $atividade_id, $sessao, '' );
				$this->redirecionar( $atividade_id, array() );
			}

			$this->redirecionar( $atividade_id, array( 'tipo' => 'aviso', 'mensagem' => $resposta['mensagem'], 'valores' => $campos ) );
		}

		// O token é consumido pela inscrição; a próxima começa da identificação.
		$this->limpar_sessao( $atividade_id );

		$this->redirecionar( $atividade_id, array( 'tipo' => 'sucesso', 'mensagem' => $resposta['mensagem'] ) );
	}

	/**
	 * Valores do bloco "Seus dados", já no formato participante[campo] que a API espera.
	 *
	 * @return array<string, string>
	 */
	private function coletar_participante() {
		$enviado = isset( $_POST['participante'] ) ? wp_unslash( $_POST['participante'] ) : array(); // phpcs:ignore WordPress.Security
		$campos  = array();

		if ( ! is_array( $enviado ) ) {
			return $campos;
		}

		foreach ( self::CAMPOS_PARTICIPANTE as $nome ) {
			if ( ! isset( $enviado[ $nome ] ) || is_array( $enviado[ $nome ] ) ) {
				continue;
			}

			// Campo vazio segue mesmo assim: é o servidor que decide o que é obrigatório.
			$campos[ 'participante[' . $nome . ']' ] = sanitize_text_field( $enviado[ $nome ] );
		}

		return $campos;
	}

	/** Mensagem de erro da API, com um texto de reserva quando ela não devolve nada legível. */
	private function mensagem_de_erro( WP_Error $erro, $reserva ) {
		$dados = $erro->get_error_data();

		if ( is_array( $dados ) && ! empty( $dados['errors'] ) && is_array( $dados['errors'] ) ) {
			$primeiro = reset( $dados['errors'] );
			if ( is_array( $primeiro ) && ! empty( $primeiro[0] ) ) {
				return (string) $primeiro[0];
			}
		}

		$mensagem = $erro->get_error_message();

		return '' !== $mensagem ? $mensagem : $reserva;
	}

	/**
	 * Identificação em curso desta atividade.
	 *
	 * O token fica em um transient no servidor; o navegador guarda apenas a chave que aponta
	 * para ele, então o token nunca chega ao visitante.
	 *
	 * @return array{token: string, participante: array}|null
	 */
	private function sessao( $atividade_id ) {
		$cookie = self::SESSAO_COOKIE . (int) $atividade_id;

		if ( empty( $_COOKIE[ $cookie ] ) ) {
			return null;
		}

		$chave  = sanitize_key( wp_unslash( $_COOKIE[ $cookie ] ) );
		$sessao = get_transient( self::SESSAO_PREFIXO . $chave );

		return ( is_array( $sessao ) && ! empty( $sessao['token'] ) ) ? $sessao : null;
	}

	/**
	 * @param array  $sessao    Token e dados do participante.
	 * @param string $expira_em Data ISO 8601 devolvida pela API.
	 */
	private function guardar_sessao( $atividade_id, array $sessao, $expira_em ) {
		// Sem data (regravação de uma sessão existente), vale o mínimo de 5 minutos.
		$segundos = max( 300, (int) strtotime( (string) $expira_em ) - time() );
		$chave    = wp_generate_password( 24, false );

		set_transient( self::SESSAO_PREFIXO . $chave, $sessao, $segundos );

		setcookie(
			self::SESSAO_COOKIE . (int) $atividade_id,
			$chave,
			array(
				'expires'  => time() + $segundos,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		$_COOKIE[ self::SESSAO_COOKIE . (int) $atividade_id ] = $chave;
	}

	private function limpar_sessao( $atividade_id ) {
		$cookie = self::SESSAO_COOKIE . (int) $atividade_id;

		if ( ! empty( $_COOKIE[ $cookie ] ) ) {
			delete_transient( self::SESSAO_PREFIXO . sanitize_key( wp_unslash( $_COOKIE[ $cookie ] ) ) );
		}

		setcookie(
			$cookie,
			'',
			array(
				'expires'  => time() - 3600,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		unset( $_COOKIE[ $cookie ] );
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
	 *
	 * Só o índice numérico do fim é removido: "participante.nome" precisa continuar
	 * inteiro para o erro cair no campo certo do bloco "Seus dados".
	 */
	private function agrupar_erros( array $erros ) {
		$agrupados = array();

		foreach ( $erros as $chave => $mensagens ) {
			$campo               = preg_replace( '/\.\d+$/', '', (string) $chave );
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
		$concluida = $resultado && 'sucesso' === $resultado['tipo'] && empty( $resultado['etapa'] );

		$sessao        = $this->sessao( $atividade_id );
		$identificado  = null !== $sessao;
		$participante  = $identificado && ! empty( $sessao['participante'] ) ? (array) $sessao['participante'] : array();
		$bloco_pessoal = isset( $estrutura['identificacao']['campos_participante'] )
			? (array) $estrutura['identificacao']['campos_participante']
			: array();

		$ja_inscrito = $identificado && ! empty( $sessao['ja_inscrito'] );

		// Enquanto o visitante nao confirma o e-mail, os campos da atividade nem aparecem;
		// e quem ja tem inscricao nesta atividade tambem nao os ve.
		$mostrar_identificacao = $aberto && ! $concluida && ! $identificado;
		$mostrar_form          = $aberto && ! $concluida && $identificado && ! $ja_inscrito;

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

			<?php if ( $mostrar_identificacao ) : ?>
			<form method="post" class="eventosgi-form eventosgi-identificacao" action="<?php echo esc_url( remove_query_arg( array( 'egi_form', 'egi_resultado' ) ) ); ?>">
				<?php wp_nonce_field( 'eventosgi_form_' . $atividade_id, 'eventosgi_form_nonce' ); ?>
				<input type="hidden" name="eventosgi_form_id" value="<?php echo esc_attr( $atividade_id ); ?>">

				<div class="eventosgi-isca" aria-hidden="true">
					<label><?php esc_html_e( 'Deixe este campo em branco', 'eventosgi-formularios' ); ?>
						<input type="text" name="eventosgi_isca" value="" tabindex="-1" autocomplete="off">
					</label>
				</div>

				<h3 class="eventosgi-secao"><?php esc_html_e( 'Identifique-se para se inscrever', 'eventosgi-formularios' ); ?></h3>
				<p class="eventosgi-ajuda-secao">
					<?php
					// Texto configurado no construtor de formulários do sistema de eventos; a
					// tradução local só entra quando a API não informa nada.
					echo esc_html(
						isset( $estrutura['identificacao']['mensagem'] ) && '' !== $estrutura['identificacao']['mensagem']
							? $estrutura['identificacao']['mensagem']
							: __( 'Informe o seu e-mail e confirme o código que enviaremos para ele. Assim conseguimos localizar o seu cadastro e emitir o certificado no nome certo.', 'eventosgi-formularios' )
					);
					?>
				</p>

				<div class="eventosgi-campo">
					<label class="eventosgi-label" for="eventosgi-email-<?php echo esc_attr( $atividade_id ); ?>">
						<?php esc_html_e( 'E-mail', 'eventosgi-formularios' ); ?>
						<span class="eventosgi-obrigatorio" aria-hidden="true">*</span>
					</label>
					<div class="eventosgi-linha">
						<input class="eventosgi-controle" type="email" id="eventosgi-email-<?php echo esc_attr( $atividade_id ); ?>"
							name="eventosgi_email" maxlength="150" required autocomplete="email" placeholder="voce@exemplo.com"
							value="<?php echo esc_attr( $resultado && ! empty( $resultado['email'] ) ? $resultado['email'] : '' ); ?>">
						<button type="submit" name="eventosgi_form_acao" value="solicitar_codigo" class="eventosgi-botao eventosgi-botao--secundario">
							<?php esc_html_e( 'Enviar código', 'eventosgi-formularios' ); ?>
						</button>
					</div>
				</div>

				<div class="eventosgi-campo">
					<label class="eventosgi-label" for="eventosgi-codigo-<?php echo esc_attr( $atividade_id ); ?>">
						<?php esc_html_e( 'Código de inscrição', 'eventosgi-formularios' ); ?>
						<span class="eventosgi-obrigatorio" aria-hidden="true">*</span>
					</label>
					<div class="eventosgi-linha">
						<input class="eventosgi-controle" type="text" id="eventosgi-codigo-<?php echo esc_attr( $atividade_id ); ?>"
							name="eventosgi_codigo" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000">
						<button type="submit" name="eventosgi_form_acao" value="identificar" class="eventosgi-botao">
							<?php esc_html_e( 'Confirmar', 'eventosgi-formularios' ); ?>
						</button>
					</div>
					<small class="eventosgi-ajuda"><?php esc_html_e( 'O código de inscrição foi enviado para seu email.', 'eventosgi-formularios' ); ?></small>
				</div>
			</form>
			<script>
			// Enter dentro de um formulário aciona o primeiro botão de envio — aqui, "Enviar código".
			// Quem está digitando o código espera confirmar, não pedir outro.
			( function () {
				var codigo = document.getElementById( 'eventosgi-codigo-<?php echo (int) $atividade_id; ?>' );
				if ( ! codigo ) { return; }
				codigo.addEventListener( 'keydown', function ( evento ) {
					if ( 'Enter' !== evento.key ) { return; }
					evento.preventDefault();
					codigo.form.querySelector( '[value="identificar"]' ).click();
				} );
			}() );
			</script>
			<?php endif; ?>

			<?php if ( $identificado && $ja_inscrito && ! $concluida ) : ?>
				<div class="eventosgi-alerta eventosgi-alerta--aviso">
					<?php
					echo esc_html(
						isset( $estrutura['identificacao']['mensagem_ja_inscrito'] )
							? $estrutura['identificacao']['mensagem_ja_inscrito']
							: __( 'Você já está inscrito nesta atividade.', 'eventosgi-formularios' )
					);
					?>
				</div>
			<?php endif; ?>

			<?php if ( $mostrar_form || ( $identificado && $ja_inscrito && ! $concluida ) ) : ?>
			<div class="eventosgi-identificado">
				<span><?php
					printf(
						/* translators: %s: endereço de e-mail. */
						esc_html__( 'Inscrevendo com %s', 'eventosgi-formularios' ),
						'<strong>' . esc_html( isset( $participante['email'] ) ? $participante['email'] : '' ) . '</strong>'
					);
				?></span>
				<form method="post" action="<?php echo esc_url( remove_query_arg( array( 'egi_form', 'egi_resultado' ) ) ); ?>">
					<?php wp_nonce_field( 'eventosgi_form_' . $atividade_id, 'eventosgi_form_nonce' ); ?>
					<input type="hidden" name="eventosgi_form_id" value="<?php echo esc_attr( $atividade_id ); ?>">
					<button type="submit" name="eventosgi_form_acao" value="trocar_email" class="eventosgi-link">
						<?php esc_html_e( 'Usar outro e-mail', 'eventosgi-formularios' ); ?>
					</button>
				</form>
			</div>
			<?php endif; ?>

			<?php if ( $mostrar_form ) : ?>
			<form method="post" enctype="multipart/form-data" class="eventosgi-form" action="<?php echo esc_url( remove_query_arg( array( 'egi_form', 'egi_resultado' ) ) ); ?>">
				<?php wp_nonce_field( 'eventosgi_form_' . $atividade_id, 'eventosgi_form_nonce' ); ?>
				<input type="hidden" name="eventosgi_form_acao" value="inscrever">
				<input type="hidden" name="eventosgi_form_id" value="<?php echo esc_attr( $atividade_id ); ?>">
				<input type="hidden" name="participante_email" value="<?php echo esc_attr( isset( $participante['email'] ) ? $participante['email'] : '' ); ?>">
				<div class="eventosgi-isca" aria-hidden="true">
					<label><?php esc_html_e( 'Deixe este campo em branco', 'eventosgi-formularios' ); ?>
						<input type="text" name="eventosgi_confirmacao" value="" tabindex="-1" autocomplete="off">
					</label>
				</div>

				<?php if ( $bloco_pessoal ) : ?>
					<h3 class="eventosgi-secao"><?php esc_html_e( 'Seus dados', 'eventosgi-formularios' ); ?></h3>
					<div class="eventosgi-campos">
						<div class="eventosgi-campo eventosgi-campo--col-4">
							<label class="eventosgi-label"><?php esc_html_e( 'E-mail', 'eventosgi-formularios' ); ?></label>
							<input class="eventosgi-controle" type="email" value="<?php echo esc_attr( isset( $participante['email'] ) ? $participante['email'] : '' ); ?>" readonly>
						</div>
						<?php foreach ( $bloco_pessoal as $campo ) : ?>
							<?php
							$chave = 'participante[' . $campo['nome'] . ']';
							$valor = isset( $valores[ $chave ] ) ? $valores[ $chave ] : ( isset( $participante[ $campo['nome'] ] ) ? $participante[ $campo['nome'] ] : '' );
							$this->campo_participante( $campo, $valor, isset( $erros[ 'participante.' . $campo['nome'] ] ) ? $erros[ 'participante.' . $campo['nome'] ] : array() );
							?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<?php if ( $conteudo && in_array( $posicao, array( 'acima', 'esquerda' ), true ) ) : ?>
					<div class="eventosgi-conteudo"><?php echo wp_kses_post( $conteudo ); ?></div>
				<?php endif; ?>

				<?php if ( $bloco_pessoal && $estrutura['campos'] ) : ?>
					<h3 class="eventosgi-secao"><?php esc_html_e( 'Inscrição', 'eventosgi-formularios' ); ?></h3>
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

	/**
	 * Campo do bloco "Seus dados". A definição vem da API, então rótulo e obrigatoriedade
	 * acompanham o que o sistema de eventos valida, sem repetir a regra aqui.
	 */
	private function campo_participante( array $campo, $valor, array $erros ) {
		$id          = 'eventosgi-participante-' . sanitize_html_class( $campo['nome'] );
		$nome        = 'participante[' . $campo['nome'] . ']';
		$obrigatorio = ! empty( $campo['obrigatorio'] );
		$colunas     = isset( $campo['colunas'] ) ? (int) $campo['colunas'] : 6;
		$classe      = 'eventosgi-campo eventosgi-campo--col-' . $colunas . ( $erros ? ' eventosgi-campo--erro' : '' );
		?>
		<div class="<?php echo esc_attr( $classe ); ?>">
			<label class="eventosgi-label" for="<?php echo esc_attr( $id ); ?>">
				<?php echo esc_html( $campo['label'] ); ?>
				<?php if ( $obrigatorio ) : ?><span class="eventosgi-obrigatorio" aria-hidden="true">*</span><?php endif; ?>
			</label>

			<?php if ( 'select' === $campo['tipo'] ) : ?>
				<select class="eventosgi-controle" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $nome ); ?>" <?php echo $obrigatorio ? 'required' : ''; ?>>
					<option value=""><?php echo esc_html( $campo['placeholder'] ? $campo['placeholder'] : __( 'Selecione…', 'eventosgi-formularios' ) ); ?></option>
					<?php foreach ( (array) $campo['opcoes'] as $chave => $rotulo ) : ?>
						<option value="<?php echo esc_attr( $chave ); ?>" <?php selected( (string) $valor, (string) $chave ); ?>><?php echo esc_html( $rotulo ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php else : ?>
				<input class="eventosgi-controle" type="<?php echo esc_attr( $campo['tipo'] ); ?>" id="<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $nome ); ?>" value="<?php echo esc_attr( is_scalar( $valor ) ? $valor : '' ); ?>"
					maxlength="<?php echo esc_attr( isset( $campo['maxlength'] ) ? (int) $campo['maxlength'] : 150 ); ?>"
					placeholder="<?php echo esc_attr( $campo['placeholder'] ); ?>" <?php echo $obrigatorio ? 'required' : ''; ?>
					<?php echo 'cpf' === $campo['nome'] ? 'inputmode="numeric" autocomplete="off"' : ''; ?>>
			<?php endif; ?>

			<?php if ( ! empty( $campo['ajuda'] ) ) : ?>
				<small class="eventosgi-ajuda"><?php echo esc_html( $campo['ajuda'] ); ?></small>
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
