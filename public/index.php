<?php

declare(strict_types=1);

/**
 * Front controller. UNICO arquivo PHP alcancavel pela web.
 *
 * Tudo o que importa -- .env, cliente HTTP, sessao, views -- vive em src/, um nivel acima
 * e fora da pasta servida. Se src/ ficar acessivel, o .env vira leitura publica.
 */

use App\Auth\Session;
use App\Config;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\LeadsController;
use App\Controllers\LoggersController;
use App\Controllers\PermissionsController;
use App\Controllers\ProfilesController;
use App\Controllers\UsersController;
use App\Router;

/*
 * Servidor embutido (php -S) apenas.
 *
 * Com um script de roteamento, ele manda TODA requisicao para ca -- inclusive /assets/*.
 * Devolver false faz o proprio servidor entregar o arquivo estatico. Sob Apache isso nao
 * acontece: o RewriteCond -f do .htaccess ja resolve antes de chegar aqui.
 */
if (PHP_SAPI === 'cli-server') {
    $arquivo = __DIR__ . urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

    if (is_file($arquivo)) {
        return false;
    }
}

$raiz = dirname(__DIR__);

// Autoload por convencao de diretorio. App\Http\ApiClient -> src/Http/ApiClient.php.
// Um projeto de trinta classes nao precisa de Composer para isto.
spl_autoload_register(static function (string $classe) use ($raiz): void {
    if (!str_starts_with($classe, 'App\\')) {
        return;
    }

    $caminho = $raiz . '/src/' . str_replace('\\', '/', substr($classe, 4)) . '.php';

    if (is_file($caminho)) {
        require $caminho;
    }
});

Config::carregar($raiz . '/.env');

// Fuso de EXIBICAO. A API sempre fala UTC; a conversao acontece na borda, em View::data().
date_default_timezone_set('America/Sao_Paulo');

if (Config::debug()) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    // Em producao o stack trace nao vai para a tela. O traceId de um 5xx da API pode ir --
    // e o que o suporte usa para correlacionar.
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED);
}

Session::iniciar();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

$router = new Router();

// --- Paginas anonimas -------------------------------------------------------------------
$router->get('/login', [AuthController::class, 'formularioLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);

$router->get('/esqueci-senha', [AuthController::class, 'formularioEsqueciSenha']);
$router->post('/api/esqueci-senha', [AuthController::class, 'esqueciSenha']);

$router->get('/reenviar-verificacao', [AuthController::class, 'formularioReenvio']);
$router->post('/api/reenviar-verificacao', [AuthController::class, 'reenviarVerificacao']);

// Os dois GET de link chamam a API ao ABRIR a pagina: 204 mostra o formulario, 400 mostra
// o erro. Recusar o link so depois de o usuario digitar a senha duas vezes e uma
// frustracao evitavel.
$router->get('/definir-senha', [AuthController::class, 'formularioDefinirSenha']);
$router->post('/api/definir-senha', [AuthController::class, 'definirSenha']);

$router->get('/redefinir-senha', [AuthController::class, 'formularioRedefinirSenha']);
$router->post('/api/redefinir-senha', [AuthController::class, 'redefinirSenha']);

// --- Paginas autenticadas ---------------------------------------------------------------
$router->get('/', [DashboardController::class, 'index']);
$router->get('/api/dashboard', [DashboardController::class, 'resumo']);

$router->get('/leads', [LeadsController::class, 'index']);
$router->get('/api/leads', [LeadsController::class, 'listar']);
$router->post('/api/leads', [LeadsController::class, 'criar']);
$router->put('/api/leads/{uuid}', [LeadsController::class, 'atualizar']);
$router->delete('/api/leads/{uuid}', [LeadsController::class, 'excluir']);

// --- Paginas administrativas (Admin ou master) ------------------------------------------
$router->get('/usuarios', [UsersController::class, 'index']);
$router->get('/api/usuarios', [UsersController::class, 'listar']);
$router->post('/api/usuarios', [UsersController::class, 'criar']);
$router->put('/api/usuarios/{uuid}', [UsersController::class, 'atualizar']);
$router->delete('/api/usuarios/{uuid}', [UsersController::class, 'excluir']);
$router->get('/api/usuarios/{uuid}/perfis', [UsersController::class, 'perfis']);
$router->put('/api/usuarios/{uuid}/perfis', [UsersController::class, 'substituirPerfis']);

$router->get('/perfis', [ProfilesController::class, 'index']);
$router->get('/api/perfis', [ProfilesController::class, 'listar']);
$router->get('/api/perfis/{uuid}', [ProfilesController::class, 'obter']);
$router->post('/api/perfis', [ProfilesController::class, 'criar']);
$router->put('/api/perfis/{uuid}', [ProfilesController::class, 'atualizar']);
$router->delete('/api/perfis/{uuid}', [ProfilesController::class, 'excluir']);

$router->get('/permissoes', [PermissionsController::class, 'index']);
$router->get('/api/permissoes', [PermissionsController::class, 'listar']);
$router->put('/api/permissoes/{uuid}', [PermissionsController::class, 'atualizar']);

$router->get('/logs', [LoggersController::class, 'index']);
$router->get('/api/logs', [LoggersController::class, 'listar']);

$router->despachar(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_SERVER['REQUEST_URI'] ?? '/',
);
