<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Guard;
use App\Auth\Session;

/**
 * Usuarios -- recurso administrativo, politica AdminOrMaster.
 *
 * Duas regras da API que a tela precisa respeitar para nao mentir para quem preenche:
 *
 *  - NAO existe campo de senha. A aplicacao gera uma provisoria que nunca e revelada e
 *    dispara o email de verificacao.
 *  - NAO existe campo de papel. Todo usuario criado nasce Usuario; Admin so e atribuido
 *    pela aplicacao ao primeiro usuario do tenant.
 */
final class UsersController extends Controller
{
    public function index(): never
    {
        Guard::exigeAdministrador(false);

        $this->ver('usuarios', [
            'titulo' => 'Usuarios',

            // So o papel Admin concede a marca de master. Enviado por um master, o campo e
            // IGNORADO em silencio pela API -- entao a caixa nem aparece, para a tela nao
            // exibir um controle que nao faz nada.
            'podeConcederMaster' => Session::papel() === 'Admin',
        ]);
    }

    public function listar(): never
    {
        Guard::exigeAdministrador(true);

        $this->jsonLista($this->api->get('/api/users')->lista());
    }

    public function criar(): never
    {
        Guard::exigeAdministrador(true);

        $resposta = $this->api->post('/api/users', $this->dadosDoFormulario());

        $this->json($resposta->corpo(), 201);
    }

    /** @param array<string,string> $parametros */
    public function atualizar(array $parametros): never
    {
        Guard::exigeAdministrador(true);

        $resposta = $this->api->put("/api/users/{$parametros['uuid']}", $this->dadosDoFormulario());

        $this->json($resposta->corpo());
    }

    /** @param array<string,string> $parametros */
    public function excluir(array $parametros): never
    {
        Guard::exigeAdministrador(true);

        $this->api->delete("/api/users/{$parametros['uuid']}");

        $this->json(['removido' => true]);
    }

    /** @param array<string,string> $parametros */
    public function perfis(array $parametros): never
    {
        Guard::exigeAdministrador(true);

        $this->jsonLista($this->api->get("/api/users/{$parametros['uuid']}/profiles")->lista());
    }

    /**
     * SUBSTITUI o conjunto inteiro. Lista vazia remove todos os vinculos -- e e tambem o
     * caminho de limpeza que precede promover alguem a master, ja que master e perfil sao
     * excludentes e a API recusa a combinacao com 409.
     *
     * @param array<string,string> $parametros
     */
    public function substituirPerfis(array $parametros): never
    {
        Guard::exigeAdministrador(true);

        $ids = $this->corpo()['profileIds'] ?? [];

        $resposta = $this->api->put("/api/users/{$parametros['uuid']}/profiles", [
            'profileIds' => array_values(array_map('intval', is_array($ids) ? $ids : [])),
        ]);

        $this->json(['dados' => $resposta->lista()]);
    }

    /** @return array<string,mixed> */
    private function dadosDoFormulario(): array
    {
        $corpo = $this->corpo();

        $dados = [
            'firstName'  => $this->campo('firstName'),
            'lastName'   => $this->campo('lastName'),
            'email'      => $this->campo('email'),
            'phone'      => $this->campo('phone'),
            'phoneWhats' => (bool) ($corpo['phoneWhats'] ?? false),
            'level'      => (int) ($corpo['level'] ?? 0),
            'status'     => (int) ($corpo['status'] ?? 1),
        ];

        // Só envia 'master' quem pode concede-lo. Mandar o campo de um master seria
        // inofensivo -- a API ignora --, mas o front deixaria de refletir a regra e o
        // proximo leitor acharia que funciona.
        if (Session::papel() === 'Admin') {
            $dados['master'] = (bool) ($corpo['master'] ?? false);
        }

        return $dados;
    }
}
