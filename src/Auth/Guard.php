<?php

declare(strict_types=1);

namespace App\Auth;

use App\Http\Respond;

/**
 * Guardas de rota.
 *
 * O front NAO decide acesso -- a API decide. O que estas guardas fazem e evitar oferecer
 * o que ja se sabe que sera negado, e mandar para o login quem nao tem sessao. Um 403 que
 * escape daqui continua sendo tratado na tela, porque o front nao consegue prever tudo:
 * nao existe endpoint que devolva as permissoes do proprio usuario.
 */
final class Guard
{
    /** Exige sessao viva. Sem ela, login -- com o motivo, para a tela poder explicar. */
    public static function exigeLogin(bool $ehXhr): void
    {
        if (Session::autenticado()) {
            return;
        }

        Session::encerrar();

        if ($ehXhr) {
            Respond::json([
                'status' => 401,
                'title'  => 'Sessao expirada',
                'detail' => 'Faca login novamente para continuar.',
            ], 401);
        }

        Respond::redirecionar('/login?motivo=expirado');
    }

    /**
     * Exige papel Admin ou marca master -- a politica AdminOrMaster da API, espelhada
     * aqui apenas para nao renderizar tela que responderia 403.
     */
    public static function exigeAdministrador(bool $ehXhr): void
    {
        self::exigeLogin($ehXhr);

        if (Session::administra()) {
            return;
        }

        if ($ehXhr) {
            Respond::json([
                'status' => 403,
                'title'  => 'Acesso negado',
                'detail' => 'Voce nao tem permissao para esta acao.',
            ], 403);
        }

        Respond::redirecionar('/?erro=sem-permissao');
    }

    /**
     * Exige o papel <c>Admin</c>. A marca master NAO basta.
     *
     * Espelha a politica AdminOnly da API, que hoje protege a ALTERACAO do catalogo de
     * permissoes. A leitura continua aberta ao master -- e dela que sai a lista de caixas
     * da tela de perfis.
     */
    public static function exigeAdmin(bool $ehXhr): void
    {
        self::exigeLogin($ehXhr);

        if (Session::papel() === 'Admin') {
            return;
        }

        if ($ehXhr) {
            Respond::json([
                'status' => 403,
                'title'  => 'Acesso negado',
                'detail' => 'Apenas o Admin da organizacao pode alterar o catalogo de permissoes.',
            ], 403);
        }

        Respond::redirecionar('/?erro=sem-permissao');
    }

    /** Quem ja esta logado nao ve tela de login. */
    public static function exigeAnonimo(): void
    {
        if (Session::autenticado()) {
            Respond::redirecionar('/');
        }
    }

    /**
     * Tela do segundo fator: anonima no login, mas TAMBEM alcancavel por quem ja esta dentro.
     *
     * Trocar de organizacao para uma onde a pessoa e Admin devolve um desafio, e nesse momento
     * ela continua autenticada na organizacao de origem. Com `exigeAnonimo` ali, o
     * redirecionamento para /2fa cairia de volta no painel e a troca ficaria impossivel de
     * concluir -- travando justamente o caso que o seletor existe para resolver.
     *
     * O que continua barrado e o que importa: autenticado SEM desafio pendente nao tem o que
     * fazer nesta tela. E o desafio, sozinho, nao abre nada -- quem emite o token e a API,
     * depois de conferir o codigo.
     */
    public static function exigeDesafioEmAberto(): void
    {
        if (Session::autenticado() && Session::desafio() === null) {
            Respond::redirecionar('/');
        }
    }
}
