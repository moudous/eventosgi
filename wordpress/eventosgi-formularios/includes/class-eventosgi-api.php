<?php
/**
 * Cliente HTTP da API pública de formulários do sistema Gestão de Eventos.
 */

defined( 'ABSPATH' ) || exit;

class EventosGI_Api {

	/** Prefixo dos transients usados no cache das estruturas de formulário. */
	const CACHE_PREFIXO = 'eventosgi_form_';

	/** @var string */
	private $url_base;

	/** @var string */
	private $token;

	/** @var int */
	private $cache_minutos;

	public function __construct( $opcoes = null ) {
		$opcoes              = null === $opcoes ? eventosgi_form_opcoes() : $opcoes;
		$this->url_base      = untrailingslashit( trim( $opcoes['url_base'] ) );
		$this->token         = trim( $opcoes['token'] );
		$this->cache_minutos = max( 0, (int) $opcoes['cache_minutos'] );
	}

	public function configurada() {
		return '' !== $this->url_base && '' !== $this->token;
	}

	/**
	 * Estrutura do formulário da atividade.
	 *
	 * @param int  $atividade_id ID da atividade no sistema de eventos.
	 * @param bool $ignorar_cache Força uma nova consulta à API.
	 * @return array|WP_Error
	 */
	public function formulario( $atividade_id, $ignorar_cache = false ) {
		$atividade_id = (int) $atividade_id;
		$chave        = self::CACHE_PREFIXO . md5( $this->url_base . '|' . $atividade_id );

		if ( ! $ignorar_cache && $this->cache_minutos > 0 ) {
			$cacheado = get_transient( $chave );
			if ( is_array( $cacheado ) ) {
				return $cacheado;
			}
		}

		$resposta = $this->requisitar( 'GET', "/api/v1/formularios/{$atividade_id}" );

		if ( is_wp_error( $resposta ) ) {
			return $resposta;
		}

		if ( $this->cache_minutos > 0 ) {
			set_transient( $chave, $resposta, $this->cache_minutos * MINUTE_IN_SECONDS );
		}

		return $resposta;
	}

	/**
	 * Envia uma inscrição. Os arquivos vêm no formato do $_FILES do WordPress.
	 *
	 * @param int   $atividade_id ID da atividade.
	 * @param array $campos       Pares nome => valor (valor pode ser array).
	 * @param array $arquivos     Pares nome => array( 'name', 'type', 'tmp_name' ) ou lista deles.
	 * @return array|WP_Error
	 */
	public function inscrever( $atividade_id, array $campos, array $arquivos = array() ) {
		$atividade_id = (int) $atividade_id;
		$fronteira    = 'eventosgi' . wp_generate_password( 24, false );
		$corpo        = $this->montar_multipart( $campos, $arquivos, $fronteira );

		if ( is_wp_error( $corpo ) ) {
			return $corpo;
		}

		return $this->requisitar(
			'POST',
			"/api/v1/formularios/{$atividade_id}/inscricoes",
			$corpo,
			array( 'Content-Type' => 'multipart/form-data; boundary=' . $fronteira )
		);
	}

	/**
	 * Monta o corpo multipart/form-data, único formato aceito pela validação de arquivos do Laravel.
	 *
	 * @return string|WP_Error
	 */
	private function montar_multipart( array $campos, array $arquivos, $fronteira ) {
		$corpo = '';

		foreach ( $campos as $nome => $valor ) {
			foreach ( is_array( $valor ) ? $valor : array( $valor ) as $item ) {
				$chave  = is_array( $valor ) ? $nome . '[]' : $nome;
				$corpo .= "--{$fronteira}\r\n";
				$corpo .= 'Content-Disposition: form-data; name="' . $chave . "\"\r\n\r\n";
				$corpo .= (string) $item . "\r\n";
			}
		}

		foreach ( $arquivos as $nome => $lista ) {
			$multiplo = count( $lista ) > 1;

			foreach ( $lista as $arquivo ) {
				if ( empty( $arquivo['tmp_name'] ) || ! is_readable( $arquivo['tmp_name'] ) ) {
					continue;
				}

				$conteudo = file_get_contents( $arquivo['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				if ( false === $conteudo ) {
					return new WP_Error( 'eventosgi_arquivo', __( 'Não foi possível ler um dos arquivos enviados.', 'eventosgi-formularios' ) );
				}

				$chave  = $multiplo ? $nome . '[]' : $nome;
				$corpo .= "--{$fronteira}\r\n";
				$corpo .= 'Content-Disposition: form-data; name="' . $chave . '"; filename="' . sanitize_file_name( $arquivo['name'] ) . "\"\r\n";
				$corpo .= 'Content-Type: ' . ( $arquivo['type'] ? $arquivo['type'] : 'application/octet-stream' ) . "\r\n\r\n";
				$corpo .= $conteudo . "\r\n";
			}
		}

		return $corpo . "--{$fronteira}--\r\n";
	}

	/**
	 * @return array|WP_Error Corpo JSON decodificado. Erros HTTP viram WP_Error com os dados em 'dados'.
	 */
	private function requisitar( $metodo, $caminho, $corpo = null, array $cabecalhos = array() ) {
		if ( ! $this->configurada() ) {
			return new WP_Error( 'eventosgi_sem_config', __( 'Informe a URL do sistema de eventos e o token da API nos ajustes do plugin.', 'eventosgi-formularios' ) );
		}

		$argumentos = array(
			'method'  => $metodo,
			'timeout' => 20,
			'headers' => array_merge(
				array(
					'X-Formulario-Token' => $this->token,
					'Accept'             => 'application/json',
				),
				$cabecalhos
			),
		);

		if ( null !== $corpo ) {
			$argumentos['body'] = $corpo;
		}

		$resposta = wp_remote_request( $this->url_base . $caminho, $argumentos );

		if ( is_wp_error( $resposta ) ) {
			return $resposta;
		}

		$status = (int) wp_remote_retrieve_response_code( $resposta );
		$dados  = json_decode( wp_remote_retrieve_body( $resposta ), true );

		// Inscrições recusadas por regra de negócio (vagas esgotadas, período fechado) chegam
		// como 422 com o mesmo corpo de um envio aceito; são resposta, não falha de comunicação.
		if ( ( $status >= 200 && $status < 300 ) || ( is_array( $dados ) && array_key_exists( 'sucesso', $dados ) ) ) {
			return is_array( $dados ) ? $dados : array();
		}

		$mensagem = is_array( $dados ) && ! empty( $dados['message'] )
			? $dados['message']
			: sprintf( /* translators: %d: código HTTP. */ __( 'O sistema de eventos respondeu com o código %d.', 'eventosgi-formularios' ), $status );

		return new WP_Error( 'eventosgi_http_' . $status, $mensagem, is_array( $dados ) ? $dados : array() );
	}

	/** Remove todos os formulários guardados em cache. */
	public static function limpar_cache() {
		global $wpdb;

		$nomes = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . self::CACHE_PREFIXO ) . '%'
			)
		);

		foreach ( (array) $nomes as $nome ) {
			delete_transient( substr( $nome, strlen( '_transient_' ) ) );
		}
	}
}
