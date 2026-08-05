<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\ApiClient;
use App\Http\Respond;
use App\View;

/**
 * Base dos controllers: cliente da API, leitura do corpo e atalhos de resposta.
 */
abstract class Controller
{
    protected ApiClient $api;

    public function __construct()
    {
        $this->api = ApiClient::criar();
    }

    /**
     * Corpo JSON da requisicao do jQuery.
     *
     * @return array<string,mixed>
     */
    protected function corpo(): array
    {
        $bruto = file_get_contents('php://input') ?: '';
        $dados = json_decode($bruto, true);

        if (is_array($dados)) {
            return $dados;
        }

        // Formulario tradicional (application/x-www-form-urlencoded) tambem passa por aqui.
        return $_POST;
    }

    protected function campo(string $nome, string $padrao = ''): string
    {
        $valor = $this->corpo()[$nome] ?? $padrao;

        return is_scalar($valor) ? trim((string) $valor) : $padrao;
    }

    protected function query(string $nome, string $padrao = ''): string
    {
        $valor = $_GET[$nome] ?? $padrao;

        return is_scalar($valor) ? trim((string) $valor) : $padrao;
    }

    /** @param array<string,mixed> $dados */
    protected function json(array $dados, int $status = 200): never
    {
        Respond::json($dados, $status);
    }

    /** @param array<int,mixed> $lista */
    protected function jsonLista(array $lista): never
    {
        // Encapsulado em 'dados' de proposito: um array JSON na raiz limita o dia em que
        // for preciso mandar total, pagina ou aviso junto.
        Respond::json(['dados' => $lista]);
    }

    /** @param array<string,mixed> $dados */
    protected function ver(string $pagina, array $dados = [], string $layout = 'app'): never
    {
        Respond::html(View::render($pagina, $dados, $layout));
    }
}
