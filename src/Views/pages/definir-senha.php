<?php

use App\View;

/**
 * Serve aos DOIS fluxos -- verificacao de conta (convite) e redefinicao de senha. A tela e
 * a mesma; muda o endpoint, que veio no $acao.
 *
 * O link ja foi validado no GET, antes desta pagina existir: 204 chegou ate aqui, 400
 * teria virado a tela de erro. Recusar o link so depois de o usuario digitar a senha duas
 * vezes e uma frustracao evitavel.
 *
 * @var string $token @var string $acao @var string $titulo
 */
?>
<div class="cartao-auth animate__animated animate__fadeIn">

    <div class="text-center mb-4">
        <i class="bi bi-shield-check" style="font-size:2rem;color:var(--brand-success)"></i>
        <h1 class="h5 fw-semibold mt-2 mb-1"><?= View::e($titulo) ?></h1>
        <p class="small mb-0" style="color:var(--text-secondary)">
            Escolha a senha que voce usara para entrar
        </p>
    </div>

    <form id="form-senha" data-acao="<?= View::e($acao) ?>" novalidate>
        <input type="hidden" name="token" value="<?= View::e($token) ?>">

        <div class="mb-3">
            <label class="form-label" for="senha">Nova senha</label>
            <input type="password" class="form-control" id="senha" name="senha"
                   required autofocus autocomplete="new-password">
            <div class="form-text">
                A politica de senha e validada pela API. Se algo faltar, ela dira o que.
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="confirmacao">Repita a senha</label>
            <?php
            /*
             * A confirmacao e do FRONT: a API nao tem esse campo. Ela existe so para pegar
             * erro de digitacao antes do envio, e por isso nao viaja no corpo.
             */
            ?>
            <input type="password" class="form-control" id="confirmacao" required autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary w-100" id="btn-definir">Definir senha e entrar</button>
    </form>
</div>
