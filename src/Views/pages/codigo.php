<?php

use App\Auth\Csrf;
use App\View;

/**
 * @var array{challenge:string,expira_em:int,canais:list<string>,email:string} $desafio
 * @var string|null $erro
 */
$erro = $erro ?? null;

$canais = $desafio['canais'] ?? [];

/*
 * Onde procurar o codigo. A API devolve os canais por NOME -- "email", "sms" -- sem revelar
 * o endereco nem o numero. O email aqui e o que a propria pessoa acabou de digitar no login,
 * entao repeti-lo nao conta nada que ela ja nao soubesse; o telefone nunca chega ao front.
 */
$temSms = in_array('sms', $canais, true);

$restam = max(0, ($desafio['expira_em'] ?? 0) - time());
?>
<div class="cartao-auth animate__animated animate__fadeIn">

    <div class="text-center mb-4">
        <i class="fa-solid fa-shield-halved" style="font-size:2rem;color:var(--brand-primary)"></i>
        <h1 class="h5 fw-semibold mt-2 mb-1">Confirme o acesso</h1>
        <p class="small mb-0" style="color:var(--text-secondary)">
            Enviamos um codigo de 6 digitos para
            <strong><?= View::e($desafio['email']) ?></strong><?php if ($temSms) : ?>
                e para o seu celular<?php endif; ?>.
        </p>
    </div>

    <?php if ($erro !== null) : ?>
        <div class="alert alert-danger py-2 small mb-3"><?= View::e($erro) ?></div>
    <?php endif; ?>

    <form method="post" action="/codigo" novalidate id="form-codigo">
        <?= Csrf::campo() ?>

        <div class="mb-3">
            <label class="form-label" for="codigo">Codigo</label>
            <?php
            /*
             * inputmode="numeric" traz o teclado numerico no celular sem recusar colagem, o
             * que type="number" faria de forma desajeitada (setas, rolagem, e zeros a
             * esquerda perdidos -- e zero a esquerda faz parte do codigo).
             *
             * autocomplete="one-time-code" e o que faz iOS e Android oferecerem o codigo
             * recebido por SMS direto no teclado.
             */
            ?>
            <input type="text" class="form-control form-control-lg text-center"
                   id="codigo" name="codigo"
                   inputmode="numeric" pattern="[0-9]*" maxlength="6"
                   autocomplete="one-time-code" required autofocus
                   style="letter-spacing:.6rem;font-weight:600">
        </div>

        <button type="submit" class="btn btn-primary w-100" id="btn-confirmar">Confirmar</button>
    </form>

    <div class="text-center mt-3 small" style="color:var(--text-secondary)">
        <?php if ($restam > 0) : ?>
            <span id="contagem" data-restam="<?= (int) $restam ?>">
                O codigo expira em <?= (int) ceil($restam / 60) ?> minuto(s).
            </span>
        <?php else : ?>
            <span>O codigo ja expirou. Entre novamente para receber outro.</span>
        <?php endif; ?>
    </div>

    <div class="text-center mt-2">
        <?php
        /*
         * "Entrar com outra conta", e nao "reenviar codigo": nao existe rota de reenvio na
         * API. Pedir outro codigo e refazer o login, que emite um desafio novo e derruba
         * este. Um botao chamado "reenviar" prometeria algo que nao existe.
         */
        ?>
        <a href="/codigo/cancelar" class="small text-decoration-none">Entrar com outra conta</a>
    </div>
</div>
