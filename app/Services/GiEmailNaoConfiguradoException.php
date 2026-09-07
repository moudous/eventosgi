<?php

namespace App\Services;

use RuntimeException;

/**
 * O disparo de e-mail pelo GI nao esta configurado nesta instalacao.
 *
 * Separada das demais falhas porque nao adianta o visitante tentar de novo:
 * quem resolve e o administrador, preenchendo as chaves no .env.
 */
class GiEmailNaoConfiguradoException extends RuntimeException
{
}
