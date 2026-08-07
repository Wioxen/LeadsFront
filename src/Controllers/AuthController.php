<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Guard;
use App\Auth\Session;
use App\Http\ApiException;
use App\Http\ApiResponse;
use App\Http\Respond;

/**
 * Login e os fluxos de conta conduzidos pelo proprio dono.
 *
 * Nao existe cadastro publico: usuario nasce por convite de um Admin ou master.
 *
 * Sao TRES os caminhos para definir senha, e cada um prova uma coisa diferente:
 * verify-account e reset-password provam posse do ENDERECO, por link de email, e sao
 * anonimos; change-password prova posse da SENHA ATUAL, e e o unico autenticado.
 */
final class AuthController extends Controller
{
    public function formularioLogin(): never
    {
        Guard::exigeAnonimo();

        $motivo = $this->query('motivo');

        $aviso = match ($motivo) {
            'expirado'        => 'Sua sessao expirou. Entre novamente.',
            'csrf'            => 'Sua sessao ficou invalida. Entre novamente.',
            'saiu'            => 'Voce saiu com seguranca.',
            'codigo-expirado' => 'O codigo expirou ou nao vale mais. Entre de novo para receber outro.',
            'escolha-expirada' => 'A escolha de organizacao expirou. Entre novamente.',
            'senha-definida'  => 'Senha definida. Entre com ela para continuar.',
            default           => null,
        };

        $this->ver('login', [
            'titulo' => 'Entrar',
            'aviso'  => $aviso,
        ], 'auth');
    }

    /**
     * Os dois 403 possiveis tem o MESMO status e significados diferentes. A ramificacao e
     * pelo 'title' -- o texto do 'detail' pode mudar sem aviso, o title e o discriminador.
     *
     * O SUCESSO tambem tem duas formas, e ai o discriminador e o STATUS: 200 traz o token e
     * encerra o login; 202 traz um desafio de segundo fator e nao traz token nenhum. Ler o
     * corpo para adivinhar qual chegou funcionaria hoje e quebraria no dia em que um dos
     * dois ganhasse um campo novo.
     */
    public function login(): never
    {
        Guard::exigeAnonimo();

        try {
            $resposta = $this->api->post('/api/auth/login', [
                'email' => $this->campo('email'),
                'senha' => $this->campo('senha'),
            ], exigeToken: false);
        } catch (ApiException $e) {
            $destino = match (true) {
                // Mensagem generica de proposito: distinguir "email inexistente" de "senha
                // errada" transformaria o login num verificador de cadastro.
                $e->status() === 401 => null,

                str_contains($e->title(), 'nao verificada')  => '/reenviar-verificacao',
                str_contains($e->title(), 'Redefinicao')     => '/esqueci-senha',

                default => null,
            };

            $this->ver('login', [
                'titulo'  => 'Entrar',
                'erro'    => $e->status() === 401
                    ? 'Email ou senha invalidos.'
                    : $e->detail(),
                'acaoUrl'   => $destino,
                'acaoTexto' => match ($destino) {
                    '/reenviar-verificacao' => 'Reenviar email de verificacao',
                    '/esqueci-senha'        => 'Definir uma nova senha',
                    default                 => null,
                },
                'email' => $this->campo('email'),
            ], 'auth');
        }

        $this->concluirLogin($resposta, $this->campo('email'));
    }

    /**
     * Traduz os TRES desfechos do login em navegacao.
     *
     * Compartilhado pelo login e pela escolha de organizacao, porque a escolha pode terminar
     * em token OU em desafio de segundo fator -- e repetir a ramificacao nos dois lugares
     * criaria a chance de um deles esquecer um caso.
     */
    private function concluirLogin(ApiResponse $resposta, string $email): never
    {
        // 300: a senha abriu mais de uma organizacao e falta escolher qual.
        if ($resposta->status() === 300) {
            Session::guardarEscolha($resposta->corpo());

            Respond::redirecionar('/escolher-organizacao');
        }

        if ($resposta->status() === 202) {
            Session::guardarDesafio($resposta->corpo(), $email);

            Respond::redirecionar('/2fa');
        }

        Session::autenticar($resposta->corpo());

        Respond::redirecionar('/');
    }

    /**
     * Tela de escolha da organizacao.
     *
     * Sem escolha pendente nao ha o que escolher, e mostrar a lista assim mesmo exibiria
     * organizacoes fora de qualquer contexto. Volta ao login.
     */
    public function formularioEscolha(): never
    {
        Guard::exigeAnonimo();

        $escolha = Session::escolha();

        if ($escolha === null) {
            Respond::redirecionar('/login');
        }

        $this->ver('escolher-organizacao', [
            'titulo'  => 'Escolher organizacao',
            'tenants' => $escolha['tenants'],
        ], 'auth');
    }

    /**
     * Conclui a escolha.
     *
     * O TOKEN de escolha vem da sessao; do formulario vem so o identificador da organizacao.
     * Aceitar o token do corpo permitiria apresentar um obtido de outra forma.
     *
     * A API confere se a organizacao pedida esta entre as candidatas daquele token -- esta e
     * a barreira de verdade. O que se faz aqui e nao ampliar a superficie sem necessidade.
     */
    public function escolherOrganizacao(): never
    {
        Guard::exigeAnonimo();

        $escolha = Session::escolha();

        if ($escolha === null) {
            Respond::redirecionar('/login');
        }

        try {
            $resposta = $this->api->post('/api/auth/select-tenant', [
                'selectionToken' => $escolha['token'],
                'tenantUuid'     => $this->campo('tenantUuid'),
            ], exigeToken: false);
        } catch (ApiException $e) {
            /*
             * 401 aqui significa token de escolha vencido ou organizacao fora da lista. Nos
             * dois casos nao ha o que corrigir na tela: o caminho e refazer o login.
             */
            if ($e->status() === 401) {
                Session::descartarEscolha();

                Respond::redirecionar('/login?motivo=escolha-expirada');
            }

            $this->ver('escolher-organizacao', [
                'titulo'  => 'Escolher organizacao',
                'tenants' => $escolha['tenants'],
                'erro'    => $e->detail(),
            ], 'auth');
        }

        Session::descartarEscolha();

        // Pode voltar token OU desafio de segundo fator: a conta escolhida tem os proprios
        // portoes, e eles so podem ser aplicados agora.
        $this->concluirLogin($resposta, '');
    }

    /**
     * Segundo passo do login: a tela do codigo.
     *
     * Sem desafio pendente nao ha o que confirmar, e mostrar o formulario assim mesmo daria
     * a entender que existe um codigo a caminho. Volta ao login.
     */
    public function formularioCodigo(): never
    {
        Guard::exigeDesafioEmAberto();

        $desafio = Session::desafio();

        if ($desafio === null) {
            Respond::redirecionar('/login');
        }

        $this->ver('codigo', [
            'titulo'  => 'Confirmar acesso',
            'desafio' => $desafio,
        ], 'auth');
    }

    /**
     * Confirma o codigo e abre a sessao.
     *
     * O identificador do desafio vem da SESSAO, nunca do formulario -- o navegador informa
     * so os seis digitos. Aceita-lo do corpo permitiria apontar a confirmacao para um
     * desafio de outra pessoa.
     *
     * Os 401 se dividem em dois, e a diferenca muda o que a tela faz: "Codigo expirado"
     * significa que nao adianta digitar de novo, entao o desafio e descartado e o caminho e
     * refazer o login; "Codigo invalido" deixa o formulario de pe para nova tentativa.
     */
    public function confirmarCodigo(): never
    {
        Guard::exigeDesafioEmAberto();

        $desafio = Session::desafio();

        if ($desafio === null) {
            Respond::redirecionar('/login');
        }

        try {
            $resposta = $this->api->post('/api/auth/two-factor', [
                'challenge' => $desafio['challenge'],
                'codigo'    => $this->campo('codigo'),
            ], exigeToken: false);
        } catch (ApiException $e) {
            if ($e->status() === 401 && str_contains($e->title(), 'expirado')) {
                Session::descartarDesafio();

                Respond::redirecionar('/login?motivo=codigo-expirado');
            }

            $this->ver('codigo', [
                'titulo'  => 'Confirmar acesso',
                'desafio' => $desafio,
                'erro'    => $e->detail(),
            ], 'auth');
        }

        // Descarta o desafio junto com a gravacao do token, dentro de autenticar().
        Session::autenticar($resposta->corpo());

        Respond::redirecionar('/');
    }

    /**
     * Desiste do segundo fator e volta ao login.
     *
     * O desafio segue vivo na API ate expirar -- nada aqui o cancela, e nao ha endpoint para
     * isso. O que esta rota faz e esquecer o desafio DESTA sessao, para a tela nao ficar
     * presa num codigo que o usuario nao vai digitar.
     *
     * GET, e portanto sem CSRF: o Router so exige o token nos metodos de escrita. O pior que
     * um pedido forjado consegue e apagar um desafio pendente de quem ainda nao entrou --
     * que se resolve refazendo o login. Nao ha sessao, dado nem privilegio em jogo.
     */
    /**
     * Organizacoes que a pessoa participa, para o seletor da barra.
     *
     * O token so carrega a organizacao ATUAL -- de quais outras ela participa e pergunta que
     * so a API responde.
     */
    public function organizacoes(): never
    {
        Guard::exigeLogin(true);

        $this->json($this->api->get('/api/auth/tenants')->corpo());
    }

    /**
     * Troca a organizacao da sessao sem passar pelo login.
     *
     * Devolve `destino` em vez de redirecionar: quem chama e o menu por XHR, e um 302 ali
     * seria seguido pelo fetch em silencio, trocando a pagina sem a tela saber.
     *
     * `202` significa que o vinculo de DESTINO exige codigo -- entrar como Admin pede o
     * segundo fator mesmo vindo de uma organizacao que nao pedia. A sessao atual NAO e
     * descartada aqui: se a pessoa desistir na tela do codigo, ela continua onde estava.
     */
    public function trocarOrganizacao(): never
    {
        Guard::exigeLogin(true);

        $resposta = $this->api->post('/api/auth/switch-tenant', [
            'tenantUuid' => $this->campo('tenantUuid'),
        ]);

        if ($resposta->status() === 202) {
            Session::guardarDesafio($resposta->corpo(), Session::email() ?? '');

            $this->json(['destino' => '/2fa']);
        }

        // Substitui o token: o novo carrega outro TenantId, e e ele que decide o que a
        // sessao enxerga daqui para a frente.
        Session::autenticar($resposta->corpo());

        $this->json(['destino' => '/']);
    }

    public function cancelarCodigo(): never
    {
        /*
         * Para onde voltar depende de haver sessao. Desistir de uma TROCA de organizacao nao
         * pode derrubar a sessao de origem: quem cancelou continua onde estava, e mandá-lo ao
         * login seria puni-lo por ter mudado de ideia.
         */
        $tinhaSessao = Session::autenticado();

        Session::descartarDesafio();

        Respond::redirecionar($tinhaSessao ? '/' : '/login');
    }

    /**
     * Troca de senha do proprio usuario, ja autenticado.
     *
     * O BFF nao decide nada aqui: repassa os tres campos e deixa a API conferir a senha
     * atual. Validar forca ou conferencia deste lado duplicaria a regra e criaria a chance
     * de as duas divergirem -- e a que vale seria sempre a de la.
     *
     * NAO envia identificador de usuario: a API resolve o alvo pelo token. Mandar um daqui
     * seria oferecer a ela a chance de trocar a senha de outra pessoa.
     */
    public function trocarSenha(): never
    {
        Guard::exigeLogin(true);

        $this->api->post('/api/auth/change-password', [
            'senhaAtual'       => $this->campo('senhaAtual'),
            'novaSenha'        => $this->campo('novaSenha'),
            'confirmacaoSenha' => $this->campo('confirmacaoSenha'),
        ]);

        // A API responde 204. O corpo aqui existe so para a tela ter o que exibir.
        $this->json(['mensagem' => 'Senha alterada com sucesso.']);
    }

    public function logout(): never
    {
        Session::encerrar();

        Respond::redirecionar('/login?motivo=saiu');
    }

    public function formularioEsqueciSenha(): never
    {
        Guard::exigeAnonimo();

        $this->ver('esqueci-senha', ['titulo' => 'Recuperar acesso'], 'auth');
    }

    /**
     * A API passou a DISTINGUIR o email inexistente: 404 quando nao ha usuario, 403 quando
     * a conta existe e ainda nao foi verificada, 202 quando o link foi enfileirado.
     *
     * Os dois erros sobem como ApiException e o Router os repassa ao navegador no formato
     * ProblemDetails, entao nao ha nada a tratar aqui -- a tela mostra o detail que a API
     * escreveu. O 403 leva o texto que aponta para o reenvio de verificacao.
     *
     * A mensagem de sucesso deixou de ser condicional: se chegou aqui, o email existe e o
     * envio foi enfileirado. O "se houver uma conta" era a forma de nao confirmar o
     * cadastro, e essa proteccao caiu por decisao de produto.
     */
    public function esqueciSenha(): never
    {
        $this->api->post('/api/auth/forgot-password', [
            'email' => $this->campo('email'),
        ], exigeToken: false);

        $this->json([
            'mensagem' => 'Link de redefinicao enviado. Verifique seu email.',
        ], 202);
    }

    public function formularioReenvio(): never
    {
        Guard::exigeAnonimo();

        $this->ver('reenviar-verificacao', ['titulo' => 'Reenviar verificacao'], 'auth');
    }

    public function reenviarVerificacao(): never
    {
        $this->api->post('/api/auth/resend-verification', [
            'email' => $this->campo('email'),
        ], exigeToken: false);

        $this->json([
            'mensagem' => 'Se houver uma conta pendente com esse email, o link foi enviado.',
        ], 202);
    }

    public function formularioDefinirSenha(): never
    {
        $this->formularioDeToken(
            '/api/auth/verify-account',
            'definir-senha',
            'Definir senha',
            '/api/definir-senha',
        );
    }

    public function definirSenha(): never
    {
        $this->concluirComToken('/api/auth/verify-account');
    }

    public function formularioRedefinirSenha(): never
    {
        $this->formularioDeToken(
            '/api/auth/reset-password',
            'definir-senha',
            'Redefinir senha',
            '/api/redefinir-senha',
        );
    }

    public function redefinirSenha(): never
    {
        $this->concluirComToken('/api/auth/reset-password');
    }

    /**
     * Valida o link ao ABRIR a pagina. Os dois GET nao devolvem corpo: 204 significa "link
     * valido, mostre o formulario" e 400 significa "invalido ou expirado".
     */
    private function formularioDeToken(
        string $endpoint,
        string $view,
        string $titulo,
        string $acao,
    ): never {
        /*
         * ENCERRA a sessao em vez de exigir anonimato.
         *
         * Aqui estava `Guard::exigeAnonimo()`, e ele quebrava o caso mais comum: o link do
         * convite e aberto no computador de quem cadastrou, que continua logado. O guarda
         * desviava para o painel, a pessoa nunca via o formulario -- e, se o token do outro ja
         * tivesse vencido, o painel tomava 401 e ela caia em "sua sessao expirou". Tres contas
         * em producao passaram por isso.
         *
         * Este link identifica OUTRA pessoa. A sessao que estiver aberta no navegador nao tem
         * nada a ver com ela, e mante-la seria pior que descarta-la: quem acabasse de definir a
         * senha continuaria navegando dentro da conta alheia.
         *
         * Desautenticar em vez de encerrar: destruir a sessao levaria junto o token CSRF que
         * esta pagina acabou de emitir, e o POST seguinte chegaria como 419.
         */
        Session::desautenticar();

        $token = $this->query('token');

        if ($token === '') {
            $this->ver('erro', [
                'titulo'   => $titulo,
                'status'   => 400,
                'mensagem' => 'O link esta incompleto. Abra-o direto do email, sem copiar pela metade.',
            ], 'auth');
        }

        try {
            $this->api->get($endpoint, ['token' => $token], exigeToken: false);
        } catch (ApiException) {
            $this->ver('erro', [
                'titulo'   => $titulo,
                'status'   => 400,
                'mensagem' => 'Este link e invalido ou ja expirou. Peca um novo.',
                'acaoUrl'  => '/esqueci-senha',
                'acaoTexto'=> 'Pedir um link novo',
            ], 'auth');
        }

        $this->ver($view, [
            'titulo' => $titulo,
            'token'  => $token,
            'acao'   => $acao,
        ], 'auth');
    }

    /**
     * O POST devolve TokenResponse: grava a sessao e leva direto ao dashboard. Mandar o
     * usuario fazer login logo depois de definir a propria senha e um passo sem funcao.
     */
    /**
     * Define a senha e manda fazer o login.
     *
     * A API responde 204 e NAO emite token: uma pessoa pode participar de varias
     * organizacoes, e este fluxo nao tem como saber em qual ela quer entrar. Quem responde
     * isso e o login -- que pergunta, quando ha mais de uma.
     *
     * Custa um passo a quem acabou de definir a senha. Em troca, este fluxo deixa de ter
     * opiniao sobre sessao: grava uma senha, e mais nada.
     */
    private function concluirComToken(string $endpoint): never
    {
        $this->api->post($endpoint, [
            'token' => $this->campo('token'),
            'senha' => $this->campo('senha'),

            // Campo do contrato: a API exige ConfirmacaoSenha e a compara com Senha. O BFF
            // repassa em vez de reconstruir a partir de 'senha' -- se as duas divergirem,
            // quem tem de recusar e a API, e um valor forjado aqui esconderia a divergencia.
            'confirmacaoSenha' => $this->campo('confirmacaoSenha'),
        ], exigeToken: false);

        /*
         * ENCERRA a sessao do navegador antes de mandar ao login.
         *
         * Definir uma senha e ato de OUTRA pessoa que nao a da sessao: quem abre o link do
         * convite quase sempre esta no computador de quem cadastrou, e esse alguem continua
         * logado. Sem encerrar, duas coisas davam errado ao mesmo tempo.
         *
         * A visivel: /login tem Guard::exigeAnonimo, entao quem ja esta logado era desviado
         * para o painel e o aviso "Senha definida" se perdia no caminho. Se o token do outro
         * ja tivesse vencido, o painel tomava 401 e o JS mandava para /login?motivo=expirado
         * -- e a pessoa lia "sua sessao expirou" logo apos definir a senha com sucesso,
         * concluindo que tinha falhado. Foi exatamente o que aconteceu tres vezes em producao.
         *
         * A silenciosa, e pior: a senha era gravada e a sessao do navegador continuava sendo a
         * de QUEM CADASTROU. A pessoa recem-ativada ficava dentro da conta alheia sem nada na
         * tela indicando isso.
         *
         * Vale igual para a redefinicao: trocar a senha e razao para a credencial antiga
         * morrer, nao para sobreviver.
         */
        Session::desautenticar();

        $this->json(['destino' => '/login?motivo=senha-definida']);
    }
}
