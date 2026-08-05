<?php

use App\View;

/** @var int $status @var string $mensagem @var string|null $traceId */
$traceId = $traceId ?? null;
$acaoUrl = $acaoUrl ?? null;
$acaoTexto = $acaoTexto ?? null;

$icone = match (true) {
    $status === 404 => 'bi-compass',
    $status === 403 => 'bi-shield-lock',
    $status >= 500  => 'bi-exclamation-octagon',
    default         => 'bi-exclamation-triangle',
};
?>
<div class="cartao-auth text-center">
    <i class="bi <?= View::e($icone) ?>" style="font-size:2.5rem;color:var(--text-muted)"></i>

    <h1 class="h5 fw-semibold mt-3 mb-2"><?= View::e($status) ?></h1>

    <p class="small mb-4" style="color:var(--text-secondary)"><?= View::e($mensagem) ?></p>

    <?php
    /*
     * traceId so em 5xx, e e o que o suporte usa para correlacionar com o log do servidor.
     * O stack trace nunca chega aqui -- APP_DEBUG=false em producao.
     */
    ?>
    <?php if ($traceId !== null) : ?>
        <p class="small mb-4" style="color:var(--text-muted);font-family:ui-monospace,monospace">
            traceId: <?= View::e($traceId) ?>
        </p>
    <?php endif; ?>

    <?php if ($acaoUrl !== null && $acaoTexto !== null) : ?>
        <a href="<?= View::e($acaoUrl) ?>" class="btn btn-primary btn-sm w-100 mb-2"><?= View::e($acaoTexto) ?></a>
    <?php endif; ?>

    <a href="/" class="btn btn-outline-secondary btn-sm w-100">Voltar ao inicio</a>
</div>
