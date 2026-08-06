<?php

use App\Auth\Session;
use App\View;

$meuUuid = Session::userUuid() ?? '';
?>
<div class="d-flex flex-wrap align-items-center gap-3 mb-4">
    <div>
        <h1 class="h4 fw-semibold mb-1">Meus dados</h1>
        <p class="small mb-0" style="color:var(--text-secondary)">Suas informacoes e preferencias de acesso</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-body text-center">
                <?php
                /*
                 * O src e definido pelo JavaScript, e nao aqui, porque a tela so sabe se ha
                 * foto depois de consultar /api/meus-dados. Um <img> nascendo com src fixo
                 * pisca o icone de imagem quebrada em quem nao tem foto.
                 */
                ?>
                <img id="m-foto" alt="" width="120" height="120"
                     class="rounded-circle border d-none mb-3"
                     style="object-fit:cover;background:var(--surface-muted)"
                     data-uuid="<?= View::e($meuUuid) ?>">

                <span id="m-foto-vazia"
                      class="rounded-circle border d-inline-flex align-items-center justify-content-center mb-3"
                      style="width:120px;height:120px;background:var(--surface-muted);color:var(--text-secondary);font-size:2rem">
                    <i class="fa-solid fa-user"></i>
                </span>

                <div class="d-flex justify-content-center gap-2">
                    <label class="btn btn-outline-secondary btn-sm mb-0" for="m-arquivo">
                        <i class="fa-solid fa-image me-1"></i>Trocar foto
                    </label>
                    <button type="button" class="btn btn-outline-danger btn-sm d-none" id="m-remover-foto">
                        <i class="fa-solid fa-trash-can me-1"></i>Remover
                    </button>
                </div>

                <p class="small mt-2 mb-0" style="color:var(--text-secondary)">
                    JPEG, PNG, GIF ou WebP, ate 2 MB. A imagem e convertida para JPEG e
                    reduzida a 512px; fundo transparente fica branco.
                </p>

                <input type="file" id="m-arquivo" class="d-none"
                       accept="image/jpeg,image/png,image/gif,image/webp">
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-body">
                <form id="form-meus-dados" novalidate>
                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="m-firstName">Nome</label>
                            <input type="text" class="form-control" id="m-firstName" name="firstName"
                                   maxlength="100" required>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label" for="m-lastName">Sobrenome</label>
                            <input type="text" class="form-control" id="m-lastName" name="lastName"
                                   maxlength="100" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="m-email">Email</label>
                            <?php
                            /*
                             * Somente leitura, e com o porque na tela.
                             *
                             * O email e identidade de login e unico por organizacao -- troca-lo
                             * e mudar quem a pessoa e para o sistema, nao atualizar um cadastro.
                             * A API nem aceita o campo. Um input editavel aqui prometeria algo
                             * que seria ignorado em silencio.
                             */
                            ?>
                            <input type="email" class="form-control" id="m-email" disabled>
                            <div class="form-text">
                                O email identifica seu acesso e nao pode ser alterado por aqui.
                            </div>
                        </div>

                        <div class="col-12 col-sm-7">
                            <label class="form-label" for="m-phone">Telefone</label>
                            <input type="text" class="form-control" id="m-phone" name="phone" maxlength="20">
                        </div>
                        <div class="col-12 col-sm-5 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="m-phoneWhats" name="phoneWhats">
                                <label class="form-check-label small" for="m-phoneWhats">Tem WhatsApp</label>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="m-2fa" name="twoFactorEnabled">
                        <label class="form-check-label" for="m-2fa">
                            <span class="fw-medium">Exigir codigo no login</span>
                            <span class="d-block small" style="color:var(--text-secondary)">
                                Alem da senha, um codigo de 6 digitos enviado por email &mdash; e
                                tambem por SMS, se houver celular cadastrado.
                            </span>
                        </label>
                    </div>

                    <?php
                    /*
                     * O campo de senha aparece SO ao desmarcar, e some ao remarcar. Ligar nao
                     * pede prova; desligar pede -- sem isso, uma sessao roubada removeria a
                     * barreira que existe justamente contra sessao roubada.
                     */
                    ?>
                    <div class="alert alert-warning py-2 small mt-3 d-none" id="m-aviso-2fa">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                        Desligar reduz a protecao da sua conta. Confirme com a sua senha.
                        <div class="mt-2">
                            <label class="form-label small" for="m-senha">Senha atual</label>
                            <input type="password" class="form-control form-control-sm" id="m-senha"
                                   name="senhaAtual" autocomplete="current-password">
                        </div>
                    </div>

                    <div class="alert alert-secondary py-2 small mt-3 d-none" id="m-2fa-travado">
                        <i class="fa-solid fa-lock me-1"></i>
                        Sua conta e de administracao, entao o codigo no login e obrigatorio e nao
                        pode ser desligado.
                    </div>

                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-primary btn-sm" id="m-salvar">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
