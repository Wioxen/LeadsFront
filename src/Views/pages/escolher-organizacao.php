<?php

use App\Auth\Csrf;
use App\View;

/**
 * @var list<array{uuid:string,name:string}> $tenants
 * @var string|null $erro
 */
$erro = $erro ?? null;
?>
<div class="cartao-auth animate__animated animate__fadeIn">

    <div class="text-center mb-4">
        <i class="fa-solid fa-building" style="font-size:2rem;color:var(--brand-primary)"></i>
        <h1 class="h5 fw-semibold mt-2 mb-1">Escolha a organizacao</h1>
        <p class="small mb-0" style="color:var(--text-secondary)">
            <?php
            /*
             * A explicacao importa: cair nesta tela sem contexto parece erro. Ela so aparece
             * quando a MESMA senha vale em mais de uma organizacao -- quem tem senhas
             * distintas entra direto e nunca a ve.
             */
            ?>
            Seu acesso vale em mais de uma. Escolha por onde entrar.
        </p>
    </div>

    <?php if ($erro !== null) : ?>
        <div class="alert alert-danger py-2 small mb-3"><?= View::e($erro) ?></div>
    <?php endif; ?>

    <form method="post" action="/escolher-organizacao" novalidate>
        <?= Csrf::campo() ?>

        <?php
        /*
         * Um botao por organizacao, e nao um <select> com "continuar". Sao poucas opcoes e a
         * escolha e a unica acao da tela: dois cliques viram um.
         *
         * O token de escolha NAO esta aqui -- ele vive na sessao. O formulario carrega apenas
         * qual organizacao foi escolhida.
         */
        ?>
        <div class="d-grid gap-2">
            <?php foreach ($tenants as $t) : ?>
                <button type="submit" name="tenantUuid" value="<?= View::e($t['uuid']) ?>"
                        class="btn btn-outline-secondary text-start d-flex align-items-center gap-2">
                    <i class="fa-solid fa-building-user" style="color:var(--brand-primary)"></i>
                    <span class="fw-medium"><?= View::e($t['name']) ?></span>
                    <i class="fa-solid fa-chevron-right ms-auto small"></i>
                </button>
            <?php endforeach; ?>
        </div>
    </form>

    <div class="text-center mt-3">
        <a href="/login" class="small text-decoration-none">Entrar com outra conta</a>
    </div>
</div>
