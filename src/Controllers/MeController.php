<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Guard;

/**
 * Meus dados -- o que o proprio usuario ve e altera sobre si mesmo.
 *
 * Separado de UsersController de proposito. Aquele e administrativo, e toda acao dele comeca
 * com `Guard::exigeAdministrador`; aqui basta estar autenticado. Misturar os dois num
 * controller so tornaria cada guarda uma decisao a lembrar, e a que se esquece e a que abre.
 *
 * Nenhuma rota daqui envia identificador de usuario: a API resolve o alvo pelo token.
 */
final class MeController extends Controller
{
    public function index(): never
    {
        Guard::exigeLogin(false);

        $this->ver('meus-dados', ['titulo' => 'Meus dados']);
    }

    public function obter(): never
    {
        Guard::exigeLogin(true);

        $this->json($this->api->get('/api/me')->corpo());
    }

    /**
     * O BFF NAO decide o que pode ser alterado -- ele repassa os campos que o formulario
     * tem, e a API ignora qualquer outro. Filtrar aqui daria a impressao de que esta e a
     * barreira; ela nao e, e depender dela seria depender do lado errado.
     *
     * `senhaAtual` so importa quando o pedido DESLIGA o segundo fator. Vai sempre, e a API
     * decide se precisa.
     */
    public function atualizar(): never
    {
        Guard::exigeLogin(true);

        $corpo = $this->corpo();

        $resposta = $this->api->put('/api/me', [
            'firstName'        => $this->campo('firstName'),
            'lastName'         => $this->campo('lastName'),
            'phone'            => $this->campo('phone'),
            'phoneWhats'       => (bool) ($corpo['phoneWhats'] ?? false),
            'twoFactorEnabled' => (bool) ($corpo['twoFactorEnabled'] ?? false),
            'senhaAtual'       => $this->campo('senhaAtual'),
        ]);

        $this->json($resposta->corpo());
    }

    public function enviarFoto(): never
    {
        Guard::exigeLogin(true);

        $arquivo = $_FILES['file'] ?? null;

        if (!is_array($arquivo) || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->json([
                'status' => 400,
                'title'  => 'Falha de validacao',
                'detail' => 'Nao foi possivel enviar a imagem. Verifique o arquivo e o tamanho.',
            ], 400);
        }

        // Confere que o caminho veio MESMO de um upload desta requisicao: um valor forjado
        // em $_FILES apontaria para qualquer arquivo do servidor.
        if (!is_uploaded_file((string) $arquivo['tmp_name'])) {
            $this->json([
                'status' => 400,
                'title'  => 'Falha de validacao',
                'detail' => 'Arquivo invalido.',
            ], 400);
        }

        $resposta = $this->api->enviarArquivo('/api/me/photo', 'file', [
            'tmp_name' => (string) $arquivo['tmp_name'],
            'name'     => (string) ($arquivo['name'] ?? 'foto'),
            'type'     => (string) ($arquivo['type'] ?? ''),
        ]);

        $this->json($resposta->corpo());
    }

    public function removerFoto(): never
    {
        Guard::exigeLogin(true);

        $this->json($this->api->delete('/api/me/photo')->corpo());
    }
}
