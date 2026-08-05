<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Guard;

/**
 * Registros de log. Somente leitura nesta interface.
 *
 * A tela avisa que EVENTOS ANONIMOS NAO APARECEM aqui -- login recusado, recuperacao de
 * senha e jobs ficam so no console do servidor, porque a tabela exige tenant_id e user_id.
 * Sem esse aviso, alguem procura uma tentativa de login falha e conclui que o log quebrou.
 */
final class LoggersController extends Controller
{
    public function index(): never
    {
        Guard::exigeAdministrador(false);

        $this->ver('logs', ['titulo' => 'Registros de log']);
    }

    public function listar(): never
    {
        Guard::exigeAdministrador(true);

        $this->jsonLista($this->api->get('/api/loggers')->lista());
    }
}
