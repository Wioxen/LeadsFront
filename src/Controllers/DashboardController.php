<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Guard;
use App\Auth\Session;
use App\Http\ApiException;

/**
 * Dashboard.
 *
 * A API nao tem endpoint de agregacao, entao a conta acontece AQUI, no BFF, e nunca no
 * navegador: a lista inteira nao precisa trafegar ate o cliente so para virar cinco
 * numeros, e o cache serve a todas as abas do mesmo usuario.
 *
 * ISSO NAO ESCALA, e a fronteira e conhecida -- ate uns 5.000 leads por tenant a resposta
 * sai em milissegundos. Acima disso a agregacao tem de ir para a API, em SQL com GROUP BY.
 * O tempo medido vai no campo 'agregacaoMs' de cada resposta justamente para avisar
 * quando a hora chegar, em vez de descobrir por reclamacao.
 */
final class DashboardController extends Controller
{
    private const CACHE_SEGUNDOS = 60;

    /** Acima disto, a agregacao no PHP deixou de ser adequada. */
    private const LIMITE_ALERTA = 5000;

    public function index(): never
    {
        Guard::exigeLogin(false);

        $this->ver('dashboard', [
            'titulo' => 'Dashboard',
            'semPermissao' => $this->query('erro') === 'sem-permissao',
        ]);
    }

    public function resumo(): never
    {
        Guard::exigeLogin(true);

        $periodo = (int) ($this->query('periodo', '30') ?: '30');
        $periodo = in_array($periodo, [7, 30, 90], true) ? $periodo : 30;

        /*
         * A ORGANIZACAO entra na chave, e nao so o periodo.
         *
         * Estes numeros sao de um tenant especifico. Sem isto, trocar de organizacao servia o
         * painel da anterior ate o cache vencer -- com o cabecalho ja mostrando a nova, que e
         * a pior forma de errar: o dado parece conferido justamente porque o rotulo ao lado
         * esta certo.
         *
         * O defeito e antigo e era inalcancavel: so se trocava de organizacao SAINDO, e sair
         * destroi a sessao junto com o cache. O seletor da barra tornou o caminho possivel.
         */
        $organizacao = Session::claim('TenantUuid') ?? '';

        $cache = $_SESSION['dashboard_cache'] ?? null;

        if (is_array($cache)
            && ($cache['periodo'] ?? null) === $periodo
            && ($cache['organizacao'] ?? null) === $organizacao
            && ($cache['expira'] ?? 0) > time()
        ) {
            $this->json($cache['dados'] + ['cacheado' => true]);
        }

        $inicio = microtime(true);

        $leads = $this->api->get('/api/leads')->lista();

        $dados = $this->agregar($leads, $periodo);
        $dados['agregacaoMs'] = (int) round((microtime(true) - $inicio) * 1000);
        $dados['atividades'] = $this->atividades();

        $_SESSION['dashboard_cache'] = [
            'periodo'     => $periodo,
            'organizacao' => $organizacao,
            'expira'      => time() + self::CACHE_SEGUNDOS,
            'dados'       => $dados,
        ];

        $this->json($dados + ['cacheado' => false]);
    }

    /**
     * @param array<int,array<string,mixed>> $leads
     *
     * @return array<string,mixed>
     */
    private function agregar(array $leads, int $periodo): array
    {
        $agora = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $corte = $agora->modify("-{$periodo} days");
        $corteAnterior = $agora->modify('-' . ($periodo * 2) . ' days');

        $total = count($leads);
        $noPeriodo = 0;
        $noPeriodoAnterior = 0;

        // Doze meses fixos, inclusive os vazios. Sem isso o grafico pula de marco para
        // maio e a linha mente sobre a evolucao.
        $serie = [];

        for ($i = 11; $i >= 0; $i--) {
            $mes = $agora->modify("-{$i} months")->format('Y-m');
            $serie[$mes] = 0;
        }

        foreach ($leads as $lead) {
            $criado = $this->instante($lead['createdAtUtc'] ?? null);

            if ($criado === null) {
                continue;
            }

            if ($criado >= $corte) {
                $noPeriodo++;
            } elseif ($criado >= $corteAnterior) {
                $noPeriodoAnterior++;
            }

            $mes = $criado->format('Y-m');

            if (array_key_exists($mes, $serie)) {
                $serie[$mes]++;
            }
        }

        $ultimos = array_slice($leads, 0, 10);

        return [
            'periodo' => $periodo,
            'total'   => $total,
            'novos'   => $noPeriodo,

            // Nulo quando nao ha periodo anterior com que comparar. "+100% contra zero" e
            // matematicamente vazio e parece otimo -- a tela omite em vez de mentir.
            'variacaoNovos' => $noPeriodoAnterior > 0
                ? round((($noPeriodo - $noPeriodoAnterior) / $noPeriodoAnterior) * 100, 1)
                : null,

            'serieMensal' => [
                'rotulos' => array_keys($serie),
                'valores' => array_values($serie),
            ],

            'ultimos' => array_map(static fn (array $l): array => [
                'uuid'         => $l['uuid'] ?? '',
                'name'         => $l['name'] ?? '',
                'email'        => $l['email'] ?? '',
                'createdAtUtc' => $l['createdAtUtc'] ?? null,
            ], $ultimos),

            'excedeuLimite' => $total > self::LIMITE_ALERTA,

            /*
             * Os blocos que a API ainda nao sustenta. Vao para a tela DESABILITADOS, com o
             * motivo -- e nao preenchidos com zero ou com numero inventado.
             *
             * Um card "Convertidos: 0" e indistinguivel de "nenhuma conversao ainda", e
             * alguem toma decisao em cima disso. Lead tem quatro campos: name, email,
             * createdAtUtc e updatedAtUtc.
             */
            'bloqueados' => [
                'emAtendimento' => 'requer Lead.status',
                'convertidos'   => 'requer Lead.status',
                'perdidos'      => 'requer Lead.status',
                'porOrigem'     => 'requer Lead.source',
                'conversoes'    => 'requer Lead.status com data de mudanca',
                'funil'         => 'requer etapas em Lead',
                'agenda'        => 'requer recurso de tarefas na API',
            ],
        ];
    }

    /**
     * Atividades recentes vem de /api/loggers, que exige Admin ou master.
     *
     * Para o Usuario comum o card inteiro nao e renderizado -- devolver lista vazia seria
     * pior, porque ele leria "nada aconteceu" onde o certo e "isto nao e para voce".
     *
     * @return array<int,array<string,mixed>>|null
     */
    private function atividades(): ?array
    {
        if (!Session::administra()) {
            return null;
        }

        try {
            return array_slice($this->api->get('/api/loggers')->lista(), 0, 5);
        } catch (ApiException) {
            // O dashboard nao cai porque o log falhou. O card mostra o proprio vazio.
            return [];
        }
    }

    private function instante(mixed $utc): ?\DateTimeImmutable
    {
        if (!is_string($utc) || $utc === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($utc, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return null;
        }
    }
}
