<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Saida HTTP: JSON, redirecionamento e HTML.
 *
 * Todos os metodos ENCERRAM a requisicao. E deliberado -- uma guarda que responde 403 e
 * continua executando a linha seguinte nao e guarda nenhuma, e esse e o erro classico de
 * quem escreve `Respond::json(...)` esperando que ele volte.
 */
final class Respond
{
    /** @param array<string,mixed> $dados */
    public static function json(array $dados, int $status = 200): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }

        echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        exit;
    }

    public static function semConteudo(): never
    {
        if (!headers_sent()) {
            http_response_code(204);
        }

        exit;
    }

    public static function redirecionar(string $para): never
    {
        if (!headers_sent()) {
            header('Location: ' . $para, true, 302);
        }

        exit;
    }

    public static function html(string $conteudo, int $status = 200): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=utf-8');
        }

        echo $conteudo;

        exit;
    }
}
