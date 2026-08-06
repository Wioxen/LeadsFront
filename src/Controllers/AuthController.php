<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Guard;
use App\Auth\Session;
use App\Http\ApiException;
use App\Http\Respond;

/**
 * Login e os fluxos de conta conduzidos pelo proprio dono.
 *
 * Nao existe cadastro publico: usuario nasce por convite de um Admin ou master. Tambem
 * nao existe troca de senha autenticado -- o caminho e sempre forgot-password.
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

        if ($resposta->status() === 202) {
            Session::guardarDesafio($resposta->corpo(), $this->campo('email'));

            Respond::redirecionar('/codigo');
        }

        Session::autenticar($resposta->corpo());

        Respond::redirecionar('/');
    }

    /**
     * Segundo passo do login: a tela do codigo.
     *
     * Sem desafio pendente nao ha o que confirmar, e mostrar o formulario assim mesmo daria
     * a entender que existe um codigo a caminho. Volta ao login.
     */
    public function formularioCodigo(): never
    {
        Guard::exigeAnonimo();

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
        Guard::exigeAnonimo();

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
    public function cancelarCodigo(): never
    {
        Session::descartarDesafio();

        Respond::redirecionar('/login');
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
        Guard::exigeAnonimo();

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
    private function concluirComToken(string $endpoint): never
    {
        $resposta = $this->api->post($endpoint, [
            'token' => $this->campo('token'),
            'senha' => $this->campo('senha'),

            // Campo do contrato: a API exige ConfirmacaoSenha e a compara com Senha. O BFF
            // repassa em vez de reconstruir a partir de 'senha' -- se as duas divergirem,
            // quem tem de recusar e a API, e um valor forjado aqui esconderia a divergencia.
            'confirmacaoSenha' => $this->campo('confirmacaoSenha'),
        ], exigeToken: false);

        Session::autenticar($resposta->corpo());

        $this->json(['destino' => '/']);
    }
}
