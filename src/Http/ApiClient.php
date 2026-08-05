<?php

declare(strict_types=1);

namespace App\Http;

use App\Auth\Session;
use App\Config;

/**
 * Unico ponto que fala com api-leads.digite.com.br. O navegador nunca chega aqui: ele
 * conversa com este PHP, na mesma origem, e o PHP repassa.
 *
 * Isso resolve tres coisas de uma vez -- o JWT fora do alcance de XSS (fica na sessao,
 * atras de um cookie HttpOnly), a ausencia de CORS (todo XHR e de mesma origem) e a URL
 * da API fora do cliente.
 */
final class ApiClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeout,
    ) {
    }

    public static function criar(): self
    {
        return new self(
            rtrim(Config::get('API_BASE_URL'), '/'),
            Config::int('API_TIMEOUT', 15),
        );
    }

    /**
     * @param array<string,mixed>|null $corpo
     * @param array<string,string>     $query
     *
     * @throws ApiException             a API respondeu 4xx/5xx
     * @throws SessionExpiredException  o token venceu antes do envio
     */
    public function requisitar(
        string $metodo,
        string $caminho,
        ?array $corpo = null,
        array $query = [],
        bool $exigeToken = true,
    ): ApiResponse {
        $token = null;

        if ($exigeToken) {
            $token = Session::token();

            /*
             * A UNICA guarda local: nao ha token nenhum. Nesse caso a requisicao seria
             * anonima e voltaria 401 de qualquer forma -- so que depois de uma ida a rede.
             *
             * O que NAO se faz aqui e comparar o expiresAtUtc com o relogio para decidir
             * se o token ainda vale. Quem assinou o token e quem julga se ele vale, e a
             * resposta disso e o 401. Uma decisao local depende do relogio DESTE
             * contentor: adiantado, ele desloga alguem com credencial boa; atrasado, deixa
             * passar uma morta -- e nos dois casos discorda de quem tem autoridade.
             *
             * A checagem previa tambem nao poupava trabalho do usuario, que era a razao
             * pela qual ela existia: ela roda no mesmo instante em que o 401 chegaria, na
             * hora da requisicao. Um formulario preenchido durante a expiracao se perde
             * igual nos dois caminhos.
             */
            if ($token === null || $token === '') {
                Session::encerrar();

                throw new SessionExpiredException();
            }
        }

        $url = $this->baseUrl . '/' . ltrim($caminho, '/');

        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $cabecalhos = ['Accept: application/json'];

        if ($token !== null) {
            $cabecalhos[] = 'Authorization: Bearer ' . $token;
        }

        $opcoes = [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => strtoupper($metodo),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => min(5, $this->timeout),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        if ($corpo !== null) {
            $json = json_encode($corpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $opcoes[CURLOPT_POSTFIELDS] = $json;
            $cabecalhos[] = 'Content-Type: application/json';
        }

        $opcoes[CURLOPT_HTTPHEADER] = $cabecalhos;

        $curl = curl_init();
        curl_setopt_array($curl, $opcoes);

        $bruto = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $erroCurl = curl_error($curl);

        curl_close($curl);

        if ($bruto === false) {
            // A API nao respondeu: DNS, timeout, TLS. Nao e ProblemDetails, e nao adianta
            // fingir que e -- 504 descreve melhor o que aconteceu do que um 500 generico.
            throw new ApiException(
                504,
                'A API nao respondeu',
                Config::debug()
                    ? "Falha de rede ao chamar {$url}: {$erroCurl}"
                    : 'Nao foi possivel falar com o servidor. Tente novamente em instantes.',
            );
        }

        /** @var array<string,mixed>|null $decodificado */
        $decodificado = $bruto === '' ? null : json_decode((string) $bruto, true);

        if (!is_array($decodificado)) {
            $decodificado = null;
        }

        if ($status >= 400) {
            throw ApiException::daResposta($status, $decodificado, (string) $bruto);
        }

        return new ApiResponse($status, $decodificado);
    }

    /** @param array<string,string> $query */
    public function get(string $caminho, array $query = [], bool $exigeToken = true): ApiResponse
    {
        return $this->requisitar('GET', $caminho, null, $query, $exigeToken);
    }

    /** @param array<string,mixed> $corpo */
    public function post(string $caminho, array $corpo = [], bool $exigeToken = true): ApiResponse
    {
        return $this->requisitar('POST', $caminho, $corpo, [], $exigeToken);
    }

    /** @param array<string,mixed> $corpo */
    public function put(string $caminho, array $corpo = []): ApiResponse
    {
        return $this->requisitar('PUT', $caminho, $corpo);
    }

    public function delete(string $caminho): ApiResponse
    {
        return $this->requisitar('DELETE', $caminho);
    }
}
