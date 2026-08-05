<?php

declare(strict_types=1);

namespace App;

/**
 * Configuracao, de DUAS fontes: o ambiente do processo e, quando existir, um arquivo .env.
 *
 * O ambiente vence. Em container -- Nixpacks, Docker, Easypanel -- nao ha arquivo algum: o
 * .env e ignorado pelo git de proposito, entao a imagem construida a partir do repositorio
 * nunca o contem, e a configuracao chega como variavel de ambiente do painel.
 *
 * O arquivo continua servindo ao desenvolvimento local, onde exportar seis variaveis a
 * cada shell seria pior. Por isso ele e OPCIONAL, e nao obrigatorio: exigi-lo fazia a
 * aplicacao morrer no boot em producao, com um erro que acusava o sintoma (arquivo
 * ausente) e escondia a causa (o ambiente ja tinha tudo o que era preciso).
 *
 * Sem biblioteca: sao seis chaves, e um parser de dez linhas resolve o que uma dependencia
 * a mais obrigaria a atualizar e auditar para sempre.
 *
 * Os valores NUNCA chegam ao navegador. Em especial API_BASE_URL -- e o ponto inteiro da
 * arquitetura BFF que o JavaScript so conheca rotas relativas.
 */
final class Config
{
    /** @var array<string,string> */
    private static array $valores = [];

    private static bool $carregado = false;

    /**
     * @param string|null $caminho Arquivo .env, se houver. Ausente nao e erro.
     */
    public static function carregar(?string $caminho = null): void
    {
        if (self::$carregado) {
            return;
        }

        self::$carregado = true;

        if ($caminho === null || !is_file($caminho)) {
            return;
        }

        foreach (file($caminho, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linha) {
            $linha = trim($linha);

            if ($linha === '' || str_starts_with($linha, '#')) {
                continue;
            }

            $posicao = strpos($linha, '=');

            if ($posicao === false) {
                continue;
            }

            $chave = trim(substr($linha, 0, $posicao));
            $valor = trim(substr($linha, $posicao + 1));

            // Aspas em volta do valor sao delimitador, nao conteudo.
            if (strlen($valor) > 1 && $valor[0] === '"' && str_ends_with($valor, '"')) {
                $valor = substr($valor, 1, -1);
            }

            self::$valores[$chave] = $valor;
        }
    }

    public static function get(string $chave, ?string $padrao = null): string
    {
        $valor = self::doAmbiente($chave) ?? self::$valores[$chave] ?? $padrao;

        if ($valor === null) {
            // Nomeia as duas fontes. O erro antigo dizia apenas "ausente no .env", o que
            // mandava procurar um arquivo em ambiente onde arquivo nenhum deveria existir.
            throw new \RuntimeException(
                "Configuracao '{$chave}' nao encontrada. Defina-a como variavel de "
              . 'ambiente (painel do servico) ou no arquivo .env local. Ver .env.example.'
            );
        }

        return $valor;
    }

    /**
     * Le a variavel do processo.
     *
     * Tres fontes porque nenhuma isolada e confiavel em todo SAPI: <c>$_ENV</c> depende do
     * <c>variables_order</c> do php.ini, e <c>getenv()</c> devolve vazio quando o PHP-FPM
     * roda com <c>clear_env = yes</c>, que e o padrao dele. <c>$_SERVER</c> pega o que o
     * servidor repassa por fastcgi_param.
     *
     * Vazio conta como ausente: uma variavel declarada no painel e deixada em branco e
     * quase sempre esquecimento, e cair para o padrao documentado e melhor do que operar
     * com string vazia.
     */
    private static function doAmbiente(string $chave): ?string
    {
        foreach ([$_SERVER[$chave] ?? null, $_ENV[$chave] ?? null, getenv($chave)] as $valor) {
            if (is_string($valor) && $valor !== '') {
                return $valor;
            }
        }

        return null;
    }

    public static function int(string $chave, int $padrao): int
    {
        return (int) self::get($chave, (string) $padrao);
    }

    /**
     * Trata apenas 'true' como verdadeiro. Qualquer outra coisa -- inclusive vazio, '0' e
     * lixo digitado -- e falso, porque a unica chave booleana aqui e APP_DEBUG e o erro
     * seguro dela e ficar desligada.
     */
    public static function bool(string $chave, bool $padrao = false): bool
    {
        $valor = strtolower(trim(self::get($chave, $padrao ? 'true' : 'false')));

        return $valor === 'true';
    }

    public static function debug(): bool
    {
        return self::bool('APP_DEBUG');
    }
}
