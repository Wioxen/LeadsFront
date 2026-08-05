<?php

use App\View;

/**
 * Layout das telas anonimas: login, recuperacao, definicao de senha e erro.
 *
 * Sem navbar e sem sidebar de proposito -- quem esta aqui nao tem sessao, e um menu que
 * leva a telas protegidas so produziria uma volta ao login.
 *
 * @var string $titulo @var string $conteudo @var string $pagina @var string $csrf
 */
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= View::e($titulo) ?> · CRM</title>

<meta name="csrf-token" content="<?= View::e($csrf) ?>">

<script>
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
<link rel="stylesheet" href="/assets/vendor/toastify/toastify.min.css">
<link rel="stylesheet" href="/assets/vendor/animate/animate.min.css">
<link rel="stylesheet" href="/assets/css/tokens.css">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body data-page="<?= View::e($pagina) ?>">

<div class="tela-auth">
    <?= $conteudo ?>
</div>

<script src="/assets/vendor/jquery/jquery.min.js"></script>
<script src="/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="/assets/vendor/toastify/toastify.js"></script>
<script src="/assets/js/theme.js"></script>
<script src="/assets/js/app.js"></script>

<!-- Handlers das quatro telas anonimas. Cada um se guarda pela presenca do proprio
     formulario, entao um arquivo so evita duas copias do mesmo codigo. -->
<script src="/assets/js/auth.js"></script>

</body>
</html>
