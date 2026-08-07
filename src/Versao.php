<?php

declare(strict_types=1);

namespace App;

/**
 * Identidade do que esta DEPLOYADO.
 *
 * Existe porque a API tem `/health` com o commit e o front nao tinha nada: durante horas eu
 * afirmei que uma correcao estava no ar sem poder confirmar, e o Cloudflare servia o arquivo
 * antigo. Sem um numero para comparar, "ja subiu?" so tem resposta pedindo para alguem testar.
 *
 * NAO usa o commit do git nem variavel de ambiente, de proposito. As duas exigiriam um passo
 * no build ou no painel -- e passo de deploy que depende de alguem lembrar e exatamente a
 * classe de coisa que falhou aqui. O digest e derivado dos ARQUIVOS que estao rodando, entao
 * ele nasce correto sem ninguem configurar nada.
 *
 * O mesmo digest e calculavel localmente (`php bin/versao.php`), e a comparacao dos dois
 * responde a pergunta de uma vez.
 */
final class Versao
{
    /**
     * O que entra no digest: codigo que decide comportamento e assets servidos ao navegador.
     * Fora ficam `vendor/` -- que nao muda entre deploys nossos -- e imagens.
     *
     * @var array<int,array{0:string,1:string}> pares [diretorio, extensao]
     */
    private const FONTES = [
        ['src', 'php'],
        ['public/assets/js', 'js'],
        ['public/assets/css', 'css'],
    ];

    /** Arquivos soltos que tambem contam. */
    private const AVULSOS = ['public/index.php'];

    /** @var array<string,mixed>|null */
    private static ?array $cache = null;

    /**
     * @return array{digest:string,arquivos:int,bytes:int}
     */
    public static function calcular(?string $raiz = null): array
    {
        if ($raiz === null && self::$cache !== null) {
            /** @var array{digest:string,arquivos:int,bytes:int} */
            return self::$cache;
        }

        $raiz = rtrim($raiz ?? dirname(__DIR__), '/\\');
        $arquivos = [];

        foreach (self::FONTES as [$dir, $ext]) {
            $caminho = $raiz . '/' . $dir;

            if (!is_dir($caminho)) {
                continue;
            }

            $iterador = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($caminho, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterador as $item) {
                if ($item->isFile() && strtolower($item->getExtension()) === $ext) {
                    $arquivos[] = $item->getPathname();
                }
            }
        }

        foreach (self::AVULSOS as $avulso) {
            if (is_file($raiz . '/' . $avulso)) {
                $arquivos[] = $raiz . '/' . $avulso;
            }
        }

        // Ordem estavel: a varredura do sistema de arquivos nao garante ordem, e sem isto o
        // mesmo conteudo produziria digests diferentes em maquinas diferentes.
        $relativos = array_map(
            static fn (string $p): string => str_replace('\\', '/', substr($p, strlen($raiz) + 1)),
            $arquivos,
        );

        array_multisort($relativos, $arquivos);

        $hash = hash_init('sha256');
        $bytes = 0;

        foreach ($arquivos as $i => $arquivo) {
            $conteudo = (string) file_get_contents($arquivo);

            /*
             * Remove CR antes de somar.
             *
             * O checkout no Windows pode trazer CRLF e o do contêiner LF. Sem normalizar, o
             * digest local nunca bateria com o de producao e o endpoint seria inutil
             * justamente para quem precisa compara-los.
             */
            $conteudo = str_replace("\r", '', $conteudo);

            hash_update($hash, $relativos[$i] . "\0" . $conteudo . "\0");
            $bytes += strlen($conteudo);
        }

        $resultado = [
            // 16 caracteres: suficiente para comparar a olho e curto o bastante para caber
            // numa mensagem. Nao e uso criptografico -- ninguem se defende com ele.
            'digest'   => substr(hash_final($hash), 0, 16),
            'arquivos' => count($arquivos),
            'bytes'    => $bytes,
        ];

        if ($raiz === rtrim(dirname(__DIR__), '/\\')) {
            self::$cache = $resultado;
        }

        return $resultado;
    }
}
