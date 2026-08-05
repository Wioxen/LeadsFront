<?php
/*
 * A API responde 202 exista o email ou nao, e esta tela mostra a MESMA mensagem nos dois
 * casos. Uma mensagem diferente para email inexistente transformaria o formulario num
 * verificador de cadastro -- qualquer um descobriria quem tem conta na organizacao.
 */
?>
<div class="cartao-auth animate__animated animate__fadeIn">

    <div class="text-center mb-4">
        <i class="bi bi-key" style="font-size:2rem;color:var(--brand-primary)"></i>
        <h1 class="h5 fw-semibold mt-2 mb-1">Recuperar acesso</h1>
        <p class="small mb-0" style="color:var(--text-secondary)">
            Enviaremos um link para voce definir uma senha nova
        </p>
    </div>

    <div id="resultado" class="d-none">
        <div class="alert alert-success py-3 small text-center">
            <i class="bi bi-envelope-check d-block mb-2" style="font-size:1.5rem"></i>
            <span id="resultado-texto"></span>
        </div>
        <a href="/login" class="btn btn-outline-secondary btn-sm w-100">Voltar ao login</a>
    </div>

    <form id="form-recuperar" novalidate>
        <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input type="email" class="form-control" id="email" name="email"
                   maxlength="255" required autofocus autocomplete="username">
        </div>

        <button type="submit" class="btn btn-primary w-100" id="btn-enviar">Enviar link</button>

        <div class="text-center mt-3">
            <a href="/login" class="small text-decoration-none">Voltar ao login</a>
        </div>
    </form>
</div>
