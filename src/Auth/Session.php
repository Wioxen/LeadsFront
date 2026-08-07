<?php

declare(strict_types=1);

namespace App\Auth;

use App\Config;

/**
 * Sessao do PHP: token, expiracao e claims.
 *
 * O accessToken vive AQUI e em nenhum outro lugar. Guarda-lo em localStorage ou
 * sessionStorage o deixaria ao alcance de qualquer script injetado -- inclusive o de uma
 * dependencia comprometida. O navegador so carrega o cookie HttpOnly, que o JavaScript
 * nao enxerga.
 */
final class Session
{
    /**
     * Nomes das claims de papel e email no payload.
     *
     * A API emite com ClaimTypes.Role/ClaimTypes.Email atraves do construtor de
     * JwtSecurityToken -- caminho que NAO aplica o mapa de nomes curtos do
     * JwtSecurityTokenHandler. O resultado e a URI longa dentro do JWT, e nao 'role'.
     *
     * As duas formas estao listadas de proposito: o dia em que a API limpar o mapa de
     * saida, ou trocar para JsonWebTokenHandler, o nome vira o curto -- e este front
     * continua funcionando sem alteracao.
     */
    private const CLAIMS_PAPEL = [
        'http://schemas.microsoft.com/ws/2008/06/identity/claims/role',
        'role',
    ];

    private const CLAIMS_EMAIL = [
        'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
        'email',
    ];

    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name(Config::get('SESSION_NAME', 'crm_session'));

        /*
         * A sessao do PHP tem de durar AO MENOS o que dura o token, senao ela vence antes e
         * derruba o usuario com uma credencial ainda valida na mao.
         *
         * O padrao acompanha o Jwt_ExpiresInSeconds da API (86400). Se voce aumentar a
         * validade do token la, aumente esta aqui junto -- o contrario e inofensivo, porque
         * o ApiClient confere o expiresAtUtc antes de cada chamada e manda ao login assim
         * que o token vence.
         */
        $duracao = Config::int('SESSION_LIFETIME', 86400);

        /*
         * ESTE e o que derruba a sessao cedo, e nao o cookie.
         *
         * O coletor do PHP apaga o arquivo de sessao depois de session.gc_maxlifetime sem
         * acesso -- e o padrao dele e 1440 segundos, VINTE E QUATRO MINUTOS. O cookie
         * continua no navegador, com a validade que pedimos, mas do lado do servidor nao
         * ha mais nada para ele apontar: o usuario e deslogado no meio do trabalho, sem
         * relacao alguma com a validade do JWT.
         *
         * Precisa vir ANTES do session_start: depois disso o valor ja foi lido.
         */
        ini_set('session.gc_maxlifetime', (string) $duracao);

        session_set_cookie_params([
            'lifetime' => $duracao,
            'path'     => '/',
            'httponly' => true,
            // Secure so quando a conexao e HTTPS: ligado em http://localhost, o navegador
            // descarta o cookie e o login entra num laco silencioso de "credenciais ok,
            // mas continua deslogado".
            'secure'   => self::conexaoSegura(),
            // Lax, e nao Strict: com Strict o cookie nao acompanha a navegacao vinda do
            // link de verificacao no email, e o usuario cai no login logo apos definir a
            // senha.
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    /**
     * A conexao com o NAVEGADOR e HTTPS?
     *
     * <c>$_SERVER['HTTPS']</c> sozinho descreve o ultimo salto, e nao a conexao do usuario.
     * Com nginx ou Cloudflare terminando o TLS, o PHP recebe a requisicao em http interno
     * e aquela variavel vem vazia -- o cookie de sessao sairia SEM a marca Secure e
     * passaria a poder trafegar em claro. E a falha classica de portar para producao um
     * codigo escrito contra Apache com SSL direto.
     *
     * Por isso o X-Forwarded-Proto entra na decisao. Um cliente pode forjar esse cabecalho,
     * mas so consegue empurrar a resposta para MAIS restritivo: dizer "https" quando o
     * acesso e http faz o proprio navegador dele descartar o cookie. O sentido perigoso --
     * suprimir o Secure -- exigiria remover o cabecalho que o proxy acrescenta, e quem
     * consegue isso ja esta dentro da rede.
     *
     * O proxy deve, ainda assim, SOBRESCREVER o cabecalho em vez de repassar o do cliente.
     * Ver deploy/nginx.conf.
     */
    private static function conexaoSegura(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        $encaminhado = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';

        // Pode vir como lista quando ha mais de um proxy: "https, http". O primeiro
        // descreve a conexao do usuario.
        $primeiro = trim(explode(',', (string) $encaminhado)[0]);

        if (strtolower($primeiro) === 'https') {
            return true;
        }

        // Cloudflare acrescenta este quando o visitante chega por HTTPS.
        return ($_SERVER['HTTP_CF_VISITOR'] ?? '') !== ''
            && str_contains((string) $_SERVER['HTTP_CF_VISITOR'], '"https"');
    }

    /**
     * Grava o TokenResponse devolvido por login, verify-account ou reset-password.
     *
     * @param array<string,mixed> $tokenResponse
     */
    public static function autenticar(array $tokenResponse): void
    {
        self::iniciar();

        // Troca o id de sessao no momento em que o nivel de privilegio muda. Sem isso, um
        // id fixado por um atacante antes do login continuaria valido depois dele.
        session_regenerate_id(true);

        $token = (string) ($tokenResponse['accessToken'] ?? '');

        $_SESSION['access_token'] = $token;
        $_SESSION['expires_at']   = self::paraTimestamp($tokenResponse['expiresAtUtc'] ?? null);
        $_SESSION['claims']       = self::decodificar($token);

        // Login concluido: nem o desafio nem a escolha que levaram ate aqui servem mais.
        self::descartarDesafio();
        self::descartarEscolha();
    }

    /**
     * Guarda o desafio de segundo fator entre o POST /login e a confirmacao do codigo.
     *
     * Fica na SESSAO, e nao num campo oculto do formulario. Um campo oculto viaja pelo
     * navegador e volta editavel: bastaria trocar o valor para tentar concluir um desafio
     * alheio, e ainda deixaria o identificador no DOM e no historico. Aqui ele nunca sai
     * do servidor -- a tela do codigo nao precisa conhece-lo.
     *
     * NAO ha token nenhum neste estado. Continua sendo uma sessao anonima; a unica coisa
     * que ela ganhou foi a lembranca de que uma senha ja foi conferida.
     *
     * @param array<string,mixed> $desafio Corpo do 202 devolvido por POST /api/auth/login.
     */
    public static function guardarDesafio(array $desafio, string $email): void
    {
        self::iniciar();

        $_SESSION['2fa'] = [
            'challenge' => (string) ($desafio['challenge'] ?? ''),
            'expira_em' => self::paraTimestamp($desafio['expiresAtUtc'] ?? null),
            'canais'    => array_values(array_filter(
                (array) ($desafio['canais'] ?? []),
                'is_string',
            )),

            // Guardado so para a tela dizer para onde o codigo foi. Nunca reenviado a API:
            // o segundo passo se identifica pelo desafio, nao pelo email.
            'email'     => $email,
        ];
    }

    /**
     * O desafio pendente, ou null quando nao ha nenhum.
     *
     * @return array{challenge:string,expira_em:int,canais:list<string>,email:string}|null
     */
    public static function desafio(): ?array
    {
        self::iniciar();

        $desafio = $_SESSION['2fa'] ?? null;

        if (!is_array($desafio) || ($desafio['challenge'] ?? '') === '') {
            return null;
        }

        /** @var array{challenge:string,expira_em:int,canais:list<string>,email:string} $desafio */
        return $desafio;
    }

    public static function descartarDesafio(): void
    {
        self::iniciar();

        unset($_SESSION['2fa']);
    }

    /**
     * Guarda a escolha de organizacao pendente entre o 300 do login e a selecao.
     *
     * Mesma razao do desafio de segundo fator: fica na SESSAO, nao num campo oculto. O token
     * de escolha nao e credencial -- devolve no maximo o que a senha ja devolveria --, mas
     * deixa-lo no DOM e no historico nao traz beneficio nenhum, e um campo editavel convida a
     * troca do valor.
     *
     * @param array<string,mixed> $escolha Corpo do 300 devolvido por POST /api/auth/login.
     */
    public static function guardarEscolha(array $escolha): void
    {
        self::iniciar();

        $_SESSION['escolha_tenant'] = [
            'token'   => (string) ($escolha['selectionToken'] ?? ''),
            'tenants' => array_values(array_filter(
                (array) ($escolha['tenants'] ?? []),
                static fn ($t): bool => is_array($t) && isset($t['uuid'], $t['name']),
            )),
        ];
    }

    /**
     * A escolha pendente, ou null quando nao ha nenhuma.
     *
     * @return array{token:string,tenants:list<array{uuid:string,name:string}>}|null
     */
    public static function escolha(): ?array
    {
        self::iniciar();

        $escolha = $_SESSION['escolha_tenant'] ?? null;

        if (!is_array($escolha) || ($escolha['token'] ?? '') === '' || ($escolha['tenants'] ?? []) === []) {
            return null;
        }

        /** @var array{token:string,tenants:list<array{uuid:string,name:string}>} $escolha */
        return $escolha;
    }

    public static function descartarEscolha(): void
    {
        self::iniciar();

        unset($_SESSION['escolha_tenant']);
    }

    /**
     * Ha uma sessao com token.
     *
     * NAO julga a validade do token -- isso e da API, e a resposta dela e o 401. Aqui a
     * pergunta e outra e mais modesta: existe algo para enviar? Um token vencido chega ao
     * servidor, volta 401 e o Router encerra a sessao e manda ao login.
     */
    public static function autenticado(): bool
    {
        self::iniciar();

        $token = $_SESSION['access_token'] ?? null;

        return is_string($token) && $token !== '';
    }

    public static function token(): ?string
    {
        self::iniciar();

        return $_SESSION['access_token'] ?? null;
    }

    /**
     * Instante de expiracao do token, para EXIBICAO.
     *
     * Guardado porque a interface pode querer avisar ("sua sessao termina em 10 min"), e
     * nao para decidir acesso. A decisao e da API: token vencido volta 401, o Router
     * encerra a sessao e manda ao login.
     *
     * Nao ha refresh token nesta API. Sessao expirada significa login de novo.
     */
    public static function expiraEm(): ?int
    {
        self::iniciar();

        $quando = $_SESSION['expires_at'] ?? null;

        return is_int($quando) ? $quando : null;
    }

    public static function encerrar(): void
    {
        self::iniciar();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }

        session_destroy();
    }

    /** @return array<string,mixed> */
    public static function claims(): array
    {
        self::iniciar();

        return $_SESSION['claims'] ?? [];
    }

    public static function claim(string $nome, ?string $padrao = null): ?string
    {
        $valor = self::claims()[$nome] ?? null;

        return is_scalar($valor) ? (string) $valor : $padrao;
    }

    public static function papel(): ?string
    {
        return self::primeiraClaim(self::CLAIMS_PAPEL);
    }

    /**
     * Nome da organizacao da sessao.
     *
     * Passou a importar com o modelo N:N: a mesma pessoa pode ter sessao em organizacoes
     * diferentes, e sem isto a tela nao diz em qual ela esta -- e "por que nao vejo os
     * dados?" costuma ser exatamente estar na outra.
     *
     * EXIBICAO apenas. Nenhuma decisao depende deste valor, e ele congela na emissao do
     * token: renomear a organizacao so aparece no proximo login.
     */
    public static function organizacao(): ?string
    {
        return self::claim('TenantName');
    }

    public static function email(): ?string
    {
        return self::primeiraClaim(self::CLAIMS_EMAIL);
    }

    /**
     * A claim 'master' viaja como STRING "true"/"false", nao como booleano JSON. Comparar
     * pela veracidade do valor faria "false" passar, porque string nao vazia e verdadeira
     * em PHP -- e o usuario comum receberia o menu administrativo inteiro.
     */
    public static function master(): bool
    {
        return strtolower((string) self::claim('master')) === 'true';
    }

    /**
     * Alcanca os recursos administrativos: usuarios, perfis, permissoes e log.
     *
     * O teste e papel OU marca, nunca so o papel. Um master e 'Usuario' no papel, e olhar
     * apenas para ele esconderia dele justamente as telas que ele existe para operar.
     */
    public static function administra(): bool
    {
        return self::papel() === 'Admin' || self::master();
    }

    public static function userUuid(): ?string
    {
        return self::claim('UserUuid');
    }

    /**
     * Nome de exibicao. A API nao emite nome no token, entao o que ha e o email -- usar a
     * parte antes do @ e melhor do que exibir o endereco inteiro na navbar.
     */
    public static function nomeExibicao(): string
    {
        $email = self::email();

        if ($email === null || $email === '') {
            return 'Usuario';
        }

        return ucfirst(explode('@', $email)[0]);
    }

    /** @param array<int,string> $nomes */
    private static function primeiraClaim(array $nomes): ?string
    {
        foreach ($nomes as $nome) {
            $valor = self::claim($nome);

            if ($valor !== null && $valor !== '') {
                return $valor;
            }
        }

        return null;
    }

    /**
     * Decodifica o payload SEM validar a assinatura, de proposito.
     *
     * A API e a unica autoridade sobre o token: ela o assinou e a revalida a cada
     * requisicao. Um front que "confia" no que ele mesmo decodificou apenas duplica uma
     * decisao que nao e dele -- e o que sai daqui serve so para montar a interface.
     *
     * @return array<string,mixed>
     */
    private static function decodificar(string $jwt): array
    {
        $partes = explode('.', $jwt);

        if (count($partes) !== 3) {
            return [];
        }

        $payload = base64_decode(strtr($partes[1], '-_', '+/'), false);

        if ($payload === false) {
            return [];
        }

        $claims = json_decode($payload, true);

        return is_array($claims) ? $claims : [];
    }

    private static function paraTimestamp(mixed $expiresAtUtc): int
    {
        if (!is_string($expiresAtUtc) || $expiresAtUtc === '') {
            return time();
        }

        // A API devolve em UTC. Sem forcar o fuso aqui, o strtotime interpretaria no fuso
        // do servidor e a sessao venceria horas antes ou depois do que deveria.
        $data = \DateTimeImmutable::createFromFormat(
            'Y-m-d\TH:i:s.uP',
            $expiresAtUtc,
            new \DateTimeZone('UTC'),
        ) ?: new \DateTimeImmutable($expiresAtUtc, new \DateTimeZone('UTC'));

        return $data->getTimestamp();
    }
}
