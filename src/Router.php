<?php

declare(strict_types=1);

namespace App;

use App\Auth\Csrf;
use App\Auth\Session;
use App\Http\ApiException;
use App\Http\Respond;
use App\Http\SessionExpiredException;

/**
 * Roteador. Tabela explicita, sem descoberta por convencao: com vinte rotas, ler o arquivo
 * e mais rapido do que deduzir o mapeamento -- e uma rota nova aparece no diff.
 *
 * Duas familias convivem aqui:
 *
 *   /algo          paginas HTML, navegadas pelo usuario
 *   /api/algo      chamadas do jQuery, sempre JSON
 *
 * O prefixo /api aqui e do PROPRIO PHP, na mesma origem -- nao e a API remota. O
 * JavaScript nunca conhece a URL de api-leads.digite.com.br.
 */
final class Router
{
    /** @var array<int,array{metodo:string,padrao:string,acao:callable-string|array{0:string,1:string}}> */
    private array $rotas = [];

    /** @param array{0:string,1:string} $acao */
    public function get(string $padrao, array $acao): void
    {
        $this->rotas[] = ['metodo' => 'GET', 'padrao' => $padrao, 'acao' => $acao];
    }

    /** @param array{0:string,1:string} $acao */
    public function post(string $padrao, array $acao): void
    {
        $this->rotas[] = ['metodo' => 'POST', 'padrao' => $padrao, 'acao' => $acao];
    }

    /** @param array{0:string,1:string} $acao */
    public function put(string $padrao, array $acao): void
    {
        $this->rotas[] = ['metodo' => 'PUT', 'padrao' => $padrao, 'acao' => $acao];
    }

    /** @param array{0:string,1:string} $acao */
    public function delete(string $padrao, array $acao): void
    {
        $this->rotas[] = ['metodo' => 'DELETE', 'padrao' => $padrao, 'acao' => $acao];
    }

    public function despachar(string $metodo, string $caminho): void
    {
        $caminho = '/' . trim(parse_url($caminho, PHP_URL_PATH) ?: '/', '/');
        $ehXhr = str_starts_with($caminho, '/api/');

        // CSRF em toda escrita, antes de qualquer rota rodar. O cookie de sessao e do
        // front, e o navegador o envia mesmo numa requisicao originada em outro site.
        if (in_array($metodo, ['POST', 'PUT', 'DELETE'], true) && !Csrf::valido()) {
            if ($ehXhr) {
                Respond::json([
                    'status' => 419,
                    'title'  => 'Sessao invalida',
                    'detail' => 'Recarregue a pagina e tente de novo.',
                ], 419);
            }

            Respond::redirecionar('/login?motivo=csrf');
        }

        foreach ($this->rotas as $rota) {
            if ($rota['metodo'] !== $metodo) {
                continue;
            }

            $regex = $this->paraRegex($rota['padrao']);

            if (preg_match($regex, $caminho, $encontrados) !== 1) {
                continue;
            }

            $parametros = array_filter($encontrados, 'is_string', ARRAY_FILTER_USE_KEY);

            [$classe, $acao] = $rota['acao'];

            $this->executar($classe, $acao, $parametros, $ehXhr);

            return;
        }

        $this->naoEncontrado($ehXhr);
    }

    /** @param array<string,string> $parametros */
    private function executar(string $classe, string $acao, array $parametros, bool $ehXhr): void
    {
        $controller = new $classe();

        try {
            $controller->{$acao}($parametros);
        } catch (SessionExpiredException) {
            // Nunca chega como erro de tela: a sessao morreu, e o unico caminho e o login.
            if ($ehXhr) {
                Respond::json([
                    'status' => 401,
                    'title'  => 'Sessao expirada',
                    'detail' => 'Faca login novamente para continuar.',
                ], 401);
            }

            Respond::redirecionar('/login?motivo=expirado');
        } catch (ApiException $e) {
            /*
             * 401 da API e a palavra FINAL sobre a sessao.
             *
             * Quem decide se o token vale e quem o assinou. O front nao tem autoridade
             * sobre isso -- e nem informacao: o token pode ter vencido, a chave de
             * assinatura pode ter mudado, o usuario pode ter sido desativado. Todos
             * chegam como 401, e todos terminam no mesmo lugar.
             *
             * Antes deste ramo o 401 caia na pagina de erro generica, com um "Erro 401" que
             * nao dizia ao usuario o que fazer nem limpava a sessao morta.
             */
            if ($e->status() === 401) {
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

            // O ProblemDetails da API atravessa intacto ate o navegador. O JavaScript
            // aprende UM contrato de erro, e o BFF nao vira tradutor com regra propria.
            if ($ehXhr) {
                Respond::json($e->paraJson(), $e->status());
            }

            Respond::html(View::render('erro', [
                'titulo'  => 'Erro',
                'status'  => $e->status(),
                'mensagem'=> $e->detail() !== '' ? $e->detail() : $e->title(),
                'traceId' => $e->status() >= 500 ? $e->traceId() : null,
            ], 'auth'), $e->status());
        }
    }

    private function naoEncontrado(bool $ehXhr): void
    {
        if ($ehXhr) {
            Respond::json([
                'status' => 404,
                'title'  => 'Rota nao encontrada',
                'detail' => 'O endereco chamado nao existe neste front.',
            ], 404);
        }

        Respond::html(View::render('erro', [
            'titulo'  => 'Pagina nao encontrada',
            'status'  => 404,
            'mensagem'=> 'A pagina que voce procurou nao existe.',
        ], 'auth'), 404);
    }

    /**
     * {uuid} vira um grupo nomeado que aceita SO o formato de GUID, e {id} so digitos.
     * Restringir aqui evita repassar lixo a API e transformar erro de digitacao em 400
     * vindo de longe.
     */
    private function paraRegex(string $padrao): string
    {
        $regex = preg_replace_callback(
            '/\{(\w+)\}/',
            static function (array $m): string {
                $nome = $m[1];

                $formato = match ($nome) {
                    'uuid'  => '[0-9a-fA-F-]{36}',
                    'id'    => '\d+',
                    default => '[^/]+',
                };

                return "(?P<{$nome}>{$formato})";
            },
            $padrao,
        );

        return '#^' . $regex . '$#';
    }
}
