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

    /**
     * Recebe a foto do navegador e repassa para a API.
     *
     * A validacao seria de VERDADE aqui: quem decide o que e imagem e a API, pelos primeiros
     * bytes do arquivo. O que se faz neste ponto e recusar o que nem chegou inteiro -- erro
     * de upload do proprio PHP --, porque nesse caso nao ha arquivo para repassar.
     *
     * @param array<string,string> $parametros
     */
    public function enviarFoto(array $parametros): never
    {
        Guard::exigeLogin(true);

        $arquivo = $_FILES['file'] ?? null;

        if (!is_array($arquivo) || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->json([
                'status' => 400,
                'title'  => 'Falha de validacao',
                'detail' => $this->mensagemDeUpload(
                    is_array($arquivo) ? (int) $arquivo['error'] : UPLOAD_ERR_NO_FILE
                ),
            ], 400);
        }

        /*
         * is_uploaded_file confere que o caminho veio MESMO de um upload desta requisicao.
         * Sem essa checagem, um valor forjado em $_FILES apontaria para qualquer arquivo do
         * servidor e o BFF o enviaria para a API por conta propria.
         */
        if (!is_uploaded_file((string) $arquivo['tmp_name'])) {
            $this->json([
                'status' => 400,
                'title'  => 'Falha de validacao',
                'detail' => 'Arquivo invalido.',
            ], 400);
        }

        $resposta = $this->api->enviarArquivo(
            "/api/users/{$parametros['uuid']}/photo",
            'file',
            [
                'tmp_name' => (string) $arquivo['tmp_name'],
                'name'     => (string) ($arquivo['name'] ?? 'foto'),
                'type'     => (string) ($arquivo['type'] ?? ''),
            ],
        );

        $this->json($resposta->corpo());
    }

    /**
     * Serve a foto do usuario.
     *
     * Rota AUTENTICADA, como as demais: a imagem passa pelo BFF em vez de o navegador falar
     * com a API. Publicar a URL da API para o `<img>` buscar direto exigiria expor o token
     * ao cliente ou abrir o endpoint -- as duas coisas que o BFF existe para evitar.
     *
     * @param array<string,string> $parametros
     */
    public function foto(array $parametros): never
    {
        // Basta estar autenticado: na API a foto saiu do recurso administrativo e vale para
        // qualquer usuario da organizacao. Exigir papel aqui deixaria avatar quebrado em
        // tela comum, recusando algo que o servidor entregaria.
        Guard::exigeLogin(true);

        $foto = $this->api->baixar("/api/users/{$parametros['uuid']}/photo");

        header('Content-Type: ' . $foto['tipo']);
        header('Content-Length: ' . strlen($foto['conteudo']));

        /*
         * Cache PRIVADO e curto. A foto muda pouco, mas a URL nao carrega versao -- o mesmo
         * endereco passa a devolver outra imagem quando alguem troca a foto. Cinco minutos
         * poupam as repeticoes da mesma tela sem deixar a foto antiga presa no navegador.
         *
         * 'private' porque a resposta e autenticada: um cache compartilhado no caminho
         * serviria a foto de um tenant para quem pedisse a mesma URL.
         */
        header('Cache-Control: private, max-age=300');

        echo $foto['conteudo'];

        exit;
    }

    /** @param array<string,string> $parametros */
    public function removerFoto(array $parametros): never
    {
        Guard::exigeLogin(true);

        $resposta = $this->api->delete("/api/users/{$parametros['uuid']}/photo");

        $this->json($resposta->corpo());
    }

    /**
     * Traduz o codigo de erro do upload do PHP.
     *
     * O limite mais comum nao e o da aplicacao: `upload_max_filesize` e `post_max_size` do
     * proprio PHP barram antes, e o erro generico "falha ao enviar" mandaria o operador
     * procurar problema no lugar errado.
     */
    private function mensagemDeUpload(int $erro): string
    {
        return match ($erro) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                'A imagem excede o tamanho maximo aceito pelo servidor.',
            UPLOAD_ERR_PARTIAL   => 'O envio foi interrompido. Tente novamente.',
            UPLOAD_ERR_NO_FILE   => 'Selecione uma imagem.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE =>
                'O servidor nao conseguiu gravar o arquivo temporario.',
            default              => 'Nao foi possivel enviar a imagem.',
        };
    }

    /**
     * Reenvia o email de verificacao de um usuario especifico.
     *
     * O endereco NAO vem do navegador: e resolvido aqui, a partir do uuid, por uma consulta
     * que passa pelo query filter da API. Aceitar o email do corpo transformaria esta rota
     * autenticada num disparador de email para qualquer endereco -- o mesmo efeito da tela
     * publica, so que sem o proposito dela.
     *
     * A API responde 202 exista a conta pendente ou nao. Aqui a mensagem pode ser direta:
     * quem chama e administrador do tenant e acabou de ver o usuario na listagem, entao nao
     * ha informacao nova a proteger.
     *
     * @param array<string,string> $parametros
     */
    public function reenviarVerificacao(array $parametros): never
    {
        Guard::exigeAdministrador(true);

        $usuario = $this->api->get("/api/users/{$parametros['uuid']}")->corpo();

        $email = (string) ($usuario['email'] ?? '');

        if ($email === '') {
            $this->json([
                'status' => 404,
                'title'  => 'Usuario nao encontrado',
                'detail' => 'Nao foi possivel localizar o usuario para reenviar o email.',
            ], 404);
        }

        // Rota anonima na API, entao vai sem o Bearer -- mandar o token aqui nao teria
        // efeito e so ampliaria o alcance de um vazamento de log.
        $this->api->post('/api/auth/resend-verification', ['email' => $email], exigeToken: false);

        $this->json([
            'mensagem' => "Email de verificacao reenviado para {$email}.",
        ], 202);
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

            // Sempre enviado, inclusive false. O PUT da API e total: omitir aqui desligaria
            // o segundo fator de quem o usa a cada edicao de cadastro.
            'twoFactorEnabled' => (bool) ($corpo['twoFactorEnabled'] ?? false),
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
