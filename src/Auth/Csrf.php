<?php

declare(strict_types=1);

namespace App\Auth;

/**
 * Token anti-CSRF por sessao.
 *
 * A API esta protegida por Bearer e nao precisa disto. O FRONT precisa: a sessao do PHP
 * viaja num cookie, e cookie o navegador envia sozinho -- inclusive numa requisicao
 * disparada por outro site. Sem este token, uma pagina qualquer conseguiria fazer o
 * navegador do usuario logado excluir um lead.
 */
final class Csrf
{
    private const CHAVE = '_csrf';
    private const CABECALHO = 'HTTP_X_CSRF_TOKEN';

    public static function token(): string
    {
        Session::iniciar();

        if (empty($_SESSION[self::CHAVE])) {
            $_SESSION[self::CHAVE] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::CHAVE];
    }

    public static function valido(): bool
    {
        Session::iniciar();

        $esperado = $_SESSION[self::CHAVE] ?? null;

        if (!is_string($esperado) || $esperado === '') {
            return false;
        }

        $recebido = $_SERVER[self::CABECALHO] ?? ($_POST[self::CHAVE] ?? '');

        if (!is_string($recebido) || $recebido === '') {
            return false;
        }

        // hash_equals, e nao ==: a comparacao normal para no primeiro byte diferente, e o
        // tempo que ela leva revela quantos bytes iniciais estavam certos.
        return hash_equals($esperado, $recebido);
    }

    /** Campo oculto para formulario que nao passa por AJAX. */
    public static function campo(): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s">',
            self::CHAVE,
            htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8'),
        );
    }
}
