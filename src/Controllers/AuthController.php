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
            'expirado' => 'Sua sessao expirou. Entre novamente.',
            'csrf'     => 'Sua sessao ficou invalida. Entre novamente.',
            'saiu'     => 'Voce saiu com seguranca.',
            default    => null,
        };

        $this->ver('login', [
            'titulo' => 'Entrar',
            'aviso'  => $aviso,
        ], 'auth');
    }

    /**
     * Os dois 403 possiveis tem o MESMO status e significados diferentes. A ramificacao e
     * pelo 'title' -- o texto do 'detail' pode mudar sem aviso, o title e o discriminador.
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

        Session::autenticar($resposta->corpo());

        Respond::redirecionar('/');
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
     * Responde 202 exista o email ou nao, e a tela mostra a MESMA mensagem nos dois casos.
     * Uma mensagem diferente para email inexistente transformaria esta tela num
     * verificador de cadastro.
     */
    public function esqueciSenha(): never
    {
        $this->api->post('/api/auth/forgot-password', [
            'email' => $this->campo('email'),
        ], exigeToken: false);

        $this->json([
            'mensagem' => 'Se houver uma conta com esse email, o link foi enviado.',
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
        ], exigeToken: false);

        Session::autenticar($resposta->corpo());

        $this->json(['destino' => '/']);
    }
}
