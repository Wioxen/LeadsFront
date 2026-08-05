<?php

declare(strict_types=1);

namespace App;

/**
 * Leitura do .env. Sem biblioteca: o arquivo tem seis chaves e um parser de dez linhas
 * resolve, enquanto uma dependencia a mais precisa ser atualizada e auditada para sempre.
 *
 * Os valores NUNCA chegam ao navegador. Em especial API_BASE_URL -- e o ponto inteiro da
 * arquitetura BFF que o JavaScript so conheca rotas relativas.
 */
final class Config
{
    /** @var array<string,string> */
    private static array $valores = [];

    private static bool $carregado = false;

    public static function carregar(string $caminho): void
    {
        if (self::$carregado) {
            return;
        }

        if (!is_file($caminho)) {
            throw new \RuntimeException(
                "Arquivo .env nao encontrado em {$caminho}. Copie o .env.example."
            );
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

        self::$carregado = true;
    }

    public static function get(string $chave, ?string $padrao = null): string
    {
        $valor = self::$valores[$chave] ?? $padrao;

        if ($valor === null) {
            throw new \RuntimeException(
                "Chave '{$chave}' ausente no .env e sem valor padrao. Ver .env.example."
            );
        }

        return $valor;
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
