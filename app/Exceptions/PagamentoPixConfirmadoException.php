<?php

namespace App\Exceptions;

use RuntimeException;

class PagamentoPixConfirmadoException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Esta inscrição possui pagamento PIX confirmado e não pode ser cancelada.');
    }
}
