<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Erro devolvido pela API, ja traduzido de ProblemDetails.
 *
 * O corpo de erro da API tem sempre o mesmo formato:
 *
 *   { "status": 400, "title": "Falha de validacao",
 *     "detail": "Um ou mais campos estao invalidos.",
 *     "errors": { "Email": ["O email deve conter '@'."] },
 *     "traceId": "..." }
 *
 * As chaves de 'errors' sao os nomes das propriedades do comando em PascalCase (Email,
 * Name, Master) -- NAO camelCase. Quem monta o formulario mapeia por esse nome.
 */
final class ApiException extends \RuntimeException
{
    /**
     * @param array<string,array<int,string>> $errors
     */
    public function __construct(
        private readonly int $status,
        private readonly string $title,
        private readonly string $detail,
        private readonly array $errors = [],
        private readonly ?string $traceId = null,
    ) {
        parent::__construct($detail !== '' ? $detail : $title, $status);
    }

    /**
     * @param array<string,mixed>|null $corpo
     */
    public static function daResposta(int $status, ?array $corpo, string $bruto): self
    {
        // Corpo ausente ou ilegivel ainda produz excecao util: um 502 do proxy nao vem em
        // ProblemDetails, e engolir isso deixaria a tela com "erro desconhecido".
        if ($corpo === null) {
            return new self(
                $status,
                'Erro na comunicacao com a API',
                $bruto !== '' ? mb_substr($bruto, 0, 300) : "A API respondeu {$status} sem corpo.",
            );
        }

        /** @var array<string,array<int,string>> $errors */
        $errors = is_array($corpo['errors'] ?? null) ? $corpo['errors'] : [];

        return new self(
            (int) ($corpo['status'] ?? $status),
            (string) ($corpo['title'] ?? 'Erro'),
            (string) ($corpo['detail'] ?? ''),
            $errors,
            isset($corpo['traceId']) ? (string) $corpo['traceId'] : null,
        );
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * O DISCRIMINADOR dos fluxos de login. Os dois 403 possiveis -- "Conta nao verificada"
     * e "Redefinicao de senha pendente" -- tem o mesmo status e significados diferentes.
     * Ramifique por aqui, nunca pelo detail, cujo texto pode mudar.
     */
    public function title(): string
    {
        return $this->title;
    }

    public function detail(): string
    {
        return $this->detail;
    }

    /** @return array<string,array<int,string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function traceId(): ?string
    {
        return $this->traceId;
    }

    /**
     * Formato repassado ao navegador. E o MESMO ProblemDetails da API, de proposito: o
     * JavaScript aprende um contrato de erro so, e o BFF nao vira um tradutor com regra
     * propria para manter em sincronia.
     *
     * @return array<string,mixed>
     */
    public function paraJson(): array
    {
        $json = [
            'status' => $this->status,
            'title'  => $this->title,
            'detail' => $this->detail,
        ];

        if ($this->errors !== []) {
            $json['errors'] = $this->errors;
        }

        // traceId so vai para a tela em 5xx, onde serve ao suporte correlacionar. Em 4xx
        // ele nao ajuda o usuario e so polui a mensagem.
        if ($this->traceId !== null && $this->status >= 500) {
            $json['traceId'] = $this->traceId;
        }

        return $json;
    }
}
