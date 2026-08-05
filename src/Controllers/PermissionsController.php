<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Guard;

/**
 * Catalogo de permissoes -- Controller + Action, gerado pelo CODIGO da API.
 *
 * Nao ha POST nem DELETE: uma linha criada a mao nao corresponderia a action alguma, e
 * seria concedivel a um perfil sem efeito nenhum. O PUT altera so os rotulos amigaveis e a
 * visibilidade; controller, action e isActive vem do servidor.
 */
final class PermissionsController extends Controller
{
    public function index(): never
    {
        Guard::exigeAdministrador(false);

        $this->ver('permissoes', ['titulo' => 'Permissoes']);
    }

    public function listar(): never
    {
        Guard::exigeAdministrador(true);

        // Esta e a tela de ADMINISTRACAO do catalogo, entao ela pede as ocultas tambem --
        // do contrario nao haveria como tornar visivel de novo o que foi ocultado.
        $query = $this->query('incluirOcultas') === 'true'
            ? ['incluirOcultas' => 'true']
            : [];

        $this->jsonLista($this->api->get('/api/permissions', $query)->lista());
    }

    /** @param array<string,string> $parametros */
    public function atualizar(array $parametros): never
    {
        Guard::exigeAdministrador(true);

        $corpo = $this->corpo();

        $resposta = $this->api->put("/api/permissions/{$parametros['uuid']}", [
            'controllerDescription' => $this->campo('controllerDescription'),
            'actionDescription'     => $this->campo('actionDescription'),
            'isVisible'             => (bool) ($corpo['isVisible'] ?? true),
        ]);

        $this->json($resposta->corpo());
    }
}
