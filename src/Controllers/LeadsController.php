<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Guard;

/**
 * Leads -- o recurso central do CRM e o unico de NEGOCIO.
 *
 * Diferente dos administrativos, ele nao exige Admin nem master: o controller da API usa
 * [Authorize] puro, e cada action e decidida pelos perfis do usuario. Por isso a guarda
 * aqui e apenas exigeLogin -- um 403 por falta de permissao ainda pode chegar, e a tela
 * trata.
 */
final class LeadsController extends Controller
{
    public function index(): never
    {
        Guard::exigeLogin(false);

        $this->ver('leads', ['titulo' => 'Leads']);
    }

    public function listar(): never
    {
        Guard::exigeLogin(true);

        // A API ja devolve do mais recente para o mais antigo. Nao reordene.
        $this->jsonLista($this->api->get('/api/leads')->lista());
    }

    public function criar(): never
    {
        Guard::exigeLogin(true);

        $resposta = $this->api->post('/api/leads', [
            'name'  => $this->campo('name'),
            'email' => $this->campo('email'),
        ]);

        $this->json($resposta->corpo(), 201);
    }

    /** @param array<string,string> $parametros */
    public function atualizar(array $parametros): never
    {
        Guard::exigeLogin(true);

        $resposta = $this->api->put("/api/leads/{$parametros['uuid']}", [
            'name'  => $this->campo('name'),
            'email' => $this->campo('email'),
        ]);

        $this->json($resposta->corpo());
    }

    /**
     * Remocao FISICA na API -- nao ha desfazer. A confirmacao na tela nomeia o lead, e nao
     * pergunta "tem certeza?" no vazio.
     *
     * @param array<string,string> $parametros
     */
    public function excluir(array $parametros): never
    {
        Guard::exigeLogin(true);

        $this->api->delete("/api/leads/{$parametros['uuid']}");

        $this->json(['removido' => true]);
    }
}
