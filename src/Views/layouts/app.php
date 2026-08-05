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

<?php
// Um arquivo por tela, carregado pelo data-page do <body>. Sem isso, o JS de todas as
// telas roda em todas as telas.
$scriptDaPagina = "/assets/js/pages/{$pagina}.js";

if (is_file(dirname(__DIR__, 3) . '/public' . $scriptDaPagina)) : ?>
<script src="<?= View::e($scriptDaPagina) ?>"></script>
<?php endif; ?>

</body>
</html>
