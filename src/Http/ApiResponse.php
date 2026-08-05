<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Resposta de sucesso da API. Erro nunca chega aqui -- vira ApiException no ApiClient.
 *
 * O corpo e nulo em 204, que a API usa nos DELETE e nos dois GET de validacao de link
 * (verify-account e reset-password): ali o 204 significa "link valido, mostre o
 * formulario" e o 400 significa "link invalido ou expirado".
 */
final class ApiResponse
{
    /** @param array<string,mixed>|null $corpo */
    public function __construct(
        private readonly int $status,
        private readonly ?array $corpo,
    ) {
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return array<string,mixed> */
    public function corpo(): array
    {
        return $this->corpo ?? [];
    }

    /**
     * Listagens da API vem como array JSON puro, sem envelope. O json_decode associativo
     * devolve uma lista de posicoes numericas, e e ela que interessa.
     *
     * @return array<int,array<string,mixed>>
     */
    public function lista(): array
    {
        if ($this->corpo === null) {
            return [];
        }

        return array_values(array_filter($this->corpo, 'is_array'));
    }

    public function vazio(): bool
    {
        return $this->corpo === null;
    }
}
