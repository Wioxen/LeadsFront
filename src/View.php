<?php

declare(strict_types=1);

namespace App;

use App\Auth\Csrf;
use App\Auth\Session;

/**
 * Renderizacao das telas. Sem engine de template: PHP ja e uma, e a unica disciplina que
 * ela exige e a da funcao e() abaixo.
 */
final class View
{
    /**
     * Escapa para HTML. USE EM TUDO que venha da API.
     *
     * Nome de lead e texto livre digitado por terceiros -- e o caminho classico de XSS
     * armazenado: alguem cadastra um lead chamado <script>, e a tela de quem abrir a
     * listagem executa.
     */
    public static function e(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Converte um instante UTC da API para o fuso de exibicao.
     *
     * Toda data da API termina em 'Utc' e chega em UTC. Exibir sem converter mostra ao
     * usuario um horario que nao e o dele -- e em CRM isso vira "esse lead entrou de
     * madrugada?".
     */
    public static function data(?string $utc, string $formato = 'd/m/Y H:i'): string
    {
        if ($utc === null || $utc === '') {
            return '--';
        }

        try {
            $data = new \DateTimeImmutable($utc, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return '--';
        }

        return $data->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format($formato);
    }

    /**
     * @param array<string,mixed> $dados
     */
    public static function render(string $pagina, array $dados = [], string $layout = 'app'): string
    {
        $raiz = dirname(__DIR__);

        $arquivoPagina = "{$raiz}/src/Views/pages/{$pagina}.php";
        $arquivoLayout = "{$raiz}/src/Views/layouts/{$layout}.php";

        if (!is_file($arquivoPagina)) {
            throw new \RuntimeException("View '{$pagina}' nao encontrada em {$arquivoPagina}.");
        }

        // Disponiveis em toda view, sem cada controller ter de passar.
        $dados += [
            'titulo'      => 'CRM',
            'pagina'      => $pagina,
            'csrf'        => Csrf::token(),
            'usuarioNome' => Session::autenticado() ? Session::nomeExibicao() : '',
            'usuarioEmail'=> Session::email() ?? '',
            'papel'       => Session::papel() ?? '',
            'ehMaster'    => Session::master(),
            'administra'  => Session::autenticado() && Session::administra(),
        ];

        extract($dados, EXTR_SKIP);

        ob_start();
        require $arquivoPagina;
        $conteudo = (string) ob_get_clean();

        ob_start();
        require $arquivoLayout;

        return (string) ob_get_clean();
    }
}
