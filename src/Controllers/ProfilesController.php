<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Guard;

/**
 * Perfis -- conjuntos de permissoes, recurso administrativo.
 *
 * Perfil de sistema (isSystem) nao pode ser excluido nem desativado: a API responde 409.
 * A tela desabilita os controles em vez de deixar o usuario descobrir pelo erro.
 */
final class ProfilesController extends Controller
{
    public function index(): never
    {
        Guard::exigeAdministrador(false);

        $this->ver('perfis', ['titulo' => 'Perfis de acesso']);
    }

    public function listar(): never
    {
        Guard::exigeAdministrador(true);

        $this->jsonLista($this->api->get('/api/profiles')->lista());
    }

    /** @param array<string,string> $parametros */
    public function obter(array $parametros): never
    {
        Guard::exigeAdministrador(true);

        $this->json($this->api->get("/api/profiles/{$parametros['uuid']}")->corpo());
    }

    public function criar(): never
    {
        Guard::exigeAdministrador(true);

        $resposta = $this->api->post('/api/profiles', $this->dadosDoFormulario());

        $this->json($resposta->corpo(), 201);
    }

    /** @param array<string,string> $parametros */
    public function atualizar(array $parametros): never
    {
        Guard::exigeAdministrador(true);

        $resposta = $this->api->put("/api/profiles/{$parametros['uuid']}", $this->dadosDoFormulario());

        $this->json($resposta->corpo());
    }

    /** @param array<string,string> $parametros */
    public function excluir(array $parametros): never
    {
        Guard::exigeAdministrador(true);

        $this->api->delete("/api/profiles/{$parametros['uuid']}");

        $this->json(['removido' => true]);
    }

    /** @return array<string,mixed> */
    private function dadosDoFormulario(): array
    {
        $corpo = $this->corpo();
        $ids = $corpo['permissionIds'] ?? [];

        return [
            'name'        => $this->campo('name'),
            'description' => $this->campo('description'),
            'status'      => (int) ($corpo['status'] ?? 1),

            /*
             * permissionIds SUBSTITUI o conjunto. A tela devolve tambem os ids INATIVOS
             * que o perfil ja tinha -- a API os preserva de proposito, e omiti-los aqui os
             * removeria sem ninguem perceber, so porque a action correspondente saiu do
             * codigo num refactor do servidor.
             */
            'permissionIds' => array_values(array_map('intval', is_array($ids) ? $ids : [])),
        ];
    }
}
