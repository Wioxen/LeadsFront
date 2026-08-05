<?php

use App\Auth\Csrf;
use App\View;

/** @var string|null $erro @var string|null $aviso @var string|null $acaoUrl @var string|null $acaoTexto */
$erro = $erro ?? null;
$aviso = $aviso ?? null;
$acaoUrl = $acaoUrl ?? null;
$acaoTexto = $acaoTexto ?? null;
$email = $email ?? '';
?>
<div class="cartao-auth animate__animated animate__fadeIn">

    <div class="text-center mb-4">
        <i class="fa-solid fa-layer-group" style="font-size:2rem;color:var(--brand-primary)"></i>
        <h1 class="h5 fw-semibold mt-2 mb-1">Entrar no CRM</h1>
        <p class="small mb-0" style="color:var(--text-secondary)">Acesse com sua conta da organizacao</p>
    </div>

    <?php if ($aviso !== null) : ?>
        <div class="alert alert-info py-2 small"><?= View::e($aviso) ?></div>
    <?php endif; ?>

    <?php if ($erro !== null) : ?>
        <div class="alert alert-danger py-2 small mb-3">
            <?= View::e($erro) ?>
            <?php if ($acaoUrl !== null && $acaoTexto !== null) : ?>
                <div class="mt-2">
                    <a href="<?= View::e($acaoUrl) ?>" class="alert-link"><?= View::e($acaoTexto) ?></a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php
    /*
     * POST tradicional, e nao AJAX: o login troca a sessao e redireciona, e um formulario
     * comum faz isso sem JavaScript nenhum -- inclusive com o gerenciador de senhas do
     * navegador funcionando, o que um POST por XHR costuma atrapalhar.
     */
    ?>
    <form method="post" action="/login" novalidate>
        <?= Csrf::campo() ?>

        <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input type="email" class="form-control" id="email" name="email"
                   value="<?= View::e($email) ?>" maxlength="255" required autofocus autocomplete="username">
        </div>

        <div class="mb-3">
            <label class="form-label" for="senha">Senha</label>
            <input type="password" class="form-control" id="senha" name="senha"
                   required autocomplete="current-password">
        </div>

        <button type="submit" class="btn btn-primary w-100">Entrar</button>
    </form>

    <div class="text-center mt-3">
        <a href="/esqueci-senha" class="small text-decoration-none">Esqueci minha senha</a>
    </div>

    <?php
    /*
     * Nao ha link de "criar conta": usuario nasce por convite de um Admin ou master, e
     * tenant nasce por token de plataforma. Um link aqui prometeria um fluxo inexistente.
     */
    ?>
</div>
