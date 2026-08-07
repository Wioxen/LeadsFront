<?php

use App\View;

/** @var string $titulo @var string $conteudo @var string $pagina @var string $csrf */
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= View::e($titulo) ?> · CRM</title>

<meta name="csrf-token" content="<?= View::e($csrf) ?>">

<script>
/*
 * ANTES da primeira pintura, e por isso inline. Resolver o tema no DOMContentLoaded
 * produziria um flash branco a cada navegacao -- em aplicacao de pagina inteira, a cada
 * clique.
 *
 * Precedencia: escolha do usuario > sistema operacional > claro.
 */
(function () {
  var salvo = null;
  try { salvo = localStorage.getItem('crm-tema'); } catch (e) {}
  var tema = salvo || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  document.documentElement.setAttribute('data-bs-theme', tema);
})();
</script>

<link rel="stylesheet" href="/assets/vendor/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="/assets/vendor/fontawesome/css/fontawesome.min.css">
<link rel="stylesheet" href="/assets/vendor/fontawesome/css/solid.min.css">
<link rel="stylesheet" href="/assets/vendor/datatables/dataTables.bootstrap5.min.css">
<!-- Sem este CSS o controle que reabre as colunas escondidas fica invisivel, e no celular
     as acoes do CRUD somem sem deixar caminho de volta. -->
<link rel="stylesheet" href="/assets/vendor/datatables/responsive.bootstrap5.min.css">
<link rel="stylesheet" href="/assets/vendor/select2/select2-bootstrap-5.min.css">
<link rel="stylesheet" href="/assets/vendor/flatpickr/flatpickr.min.css">
<link rel="stylesheet" href="/assets/vendor/toastify/toastify.min.css">
<link rel="stylesheet" href="/assets/vendor/sweetalert2/sweetalert2.min.css">
<link rel="stylesheet" href="/assets/vendor/animate/animate.min.css">
<link rel="stylesheet" href="/assets/vendor/aos/aos.css">

<!-- Depois das bibliotecas: os tokens tem de vencer o tema padrao de cada uma. -->
<link rel="stylesheet" href="/assets/css/tokens.css">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body data-page="<?= View::e($pagina) ?>">

<?php require dirname(__DIR__) . '/partials/navbar.php'; ?>
<?php require dirname(__DIR__) . '/partials/sidebar.php'; ?>

<main class="conteudo">
    <?= $conteudo ?>
</main>

<?php
/*
 * Modal de troca de senha, disponivel em TODA pagina autenticada -- e por isso mora no
 * layout, e nao numa tela. O gatilho e o menu do usuario, na navbar.
 *
 * Fora do <nav> de proposito: modal aninhado em elemento posicionado herda contexto de
 * empilhamento e aparece atras do fundo escurecido.
 */
?>
<div class="modal fade" id="modal-senha" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="form-senha" novalidate>
                <div class="modal-header">
                    <h2 class="modal-title h6 fw-semibold">Trocar senha</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="s-atual">Senha atual</label>
                        <input type="password" class="form-control" id="s-atual" name="senhaAtual"
                               required autocomplete="current-password">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="s-nova">Nova senha</label>
                        <input type="password" class="form-control" id="s-nova" name="novaSenha"
                               required autocomplete="new-password">
                        <div class="form-text">
                            Minimo 6 caracteres, com maiuscula, minuscula, numero e caractere especial.
                        </div>
                    </div>

                    <div class="mb-1">
                        <label class="form-label" for="s-confirma">Confirmar nova senha</label>
                        <input type="password" class="form-control" id="s-confirma" name="confirmacaoSenha"
                               required autocomplete="new-password">
                    </div>

                    <?php
                    /*
                     * Aviso honesto: nao ha revogacao de JWT no sistema, entao trocar a senha
                     * NAO derruba sessoes abertas. Quem descobre isso sozinho, descobre tarde.
                     */
                    ?>
                    <div class="alert alert-info py-2 small mt-3 mb-0">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Sessoes ja abertas continuam validas ate expirarem. Para cortar o acesso
                        de alguem agora, desative o usuario.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-salvar-senha">Trocar senha</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="/assets/vendor/jquery/jquery.min.js"></script>
<script src="/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="/assets/vendor/datatables/jquery.dataTables.min.js"></script>
<script src="/assets/vendor/datatables/dataTables.bootstrap5.min.js"></script>
<script src="/assets/vendor/datatables/dataTables.responsive.min.js"></script>
<script src="/assets/vendor/select2/select2.min.js"></script>
<script src="/assets/vendor/flatpickr/flatpickr.min.js"></script>
<script src="/assets/vendor/flatpickr/pt.js"></script>
<script src="/assets/vendor/inputmask/inputmask.min.js"></script>
<script src="/assets/vendor/apexcharts/apexcharts.min.js"></script>
<script src="/assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
<script src="/assets/vendor/toastify/toastify.js"></script>
<script src="/assets/vendor/aos/aos.js"></script>

<script src="/assets/js/theme.js"></script>
<script src="/assets/js/app.js"></script>
<script src="/assets/js/seletor-organizacao.js"></script>

<?php
// Um arquivo por tela, carregado pelo data-page do <body>. Sem isso, o JS de todas as
// telas roda em todas as telas.
$scriptDaPagina = "/assets/js/pages/{$pagina}.js";

if (is_file(dirname(__DIR__, 3) . '/public' . $scriptDaPagina)) : ?>
<script src="<?= View::e($scriptDaPagina) ?>"></script>
<?php endif; ?>

</body>
</html>
