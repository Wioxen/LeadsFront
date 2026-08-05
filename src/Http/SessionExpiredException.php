<?php

declare(strict_types=1);

namespace App\Http;

/**
 * O token da sessao expirou ANTES da chamada.
 *
 * Nao e um erro da API -- a requisicao sequer sai. O ApiClient compara expiresAtUtc com o
 * relogio antes de cada chamada, em vez de esperar o 401: quando o 401 chega, o usuario ja
 * perdeu o que estava preenchendo.
 *
 * Nao existe refresh token nesta API. Sessao expirada significa login de novo.
 */
final class SessionExpiredException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Sessao expirada.', 401);
    }
}
