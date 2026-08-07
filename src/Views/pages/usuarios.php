<?php

use App\View;

/** @var bool $podeConcederMaster */
?>
<div class="d-flex flex-wrap align-items-center gap-3 mb-4">
    <div>
        <h1 class="h4 fw-semibold mb-1">Usuarios</h1>
        <p class="small mb-0" style="color:var(--text-secondary)">Equipe da organizacao</p>
    </div>

    <button class="btn btn-primary btn-sm ms-auto" id="btn-novo">
        <i class="fa-solid fa-plus me-1"></i>Novo usuario
    </button>
</div>

<div class="card">
    <div class="card-body">
        <div id="area-tabela">
            <div class="skeleton mb-2" style="height:2.5rem"></div>
            <div class="skeleton" style="height:2.5rem"></div>
        </div>

        <table class="table align-middle w-100 d-none" id="tabela-usuarios">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Papel</th>
                    <th>Situacao</th>
                    <th class="text-end">Acoes</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modal-usuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="form-usuario" novalidate>
                <div class="modal-header">
                    <h2 class="modal-title h6 fw-semibold" id="modal-titulo">Novo usuario</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="uuid">

                    <?php
                    /*
                     * A foto NAO faz parte do payload do cadastro: ela tem endpoint proprio,
                     * que grava o arquivo e aponta o registro no mesmo passo. Aqui ela e
                     * apenas escolhida; o envio acontece depois de salvar, quando ja existe
                     * um uuid para receber o arquivo (num cadastro novo ele so nasce ali).
                     *
                     * O 'name' do input fica de fora de proposito: App.dadosDoFormulario
                     * varre por [name], e um campo de arquivo entraria no JSON como o texto
                     * "C:\fakepath\foto.jpg", que e o que o navegador expoe.
                     */
                    ?>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <img id="u-foto-previa" alt="" width="64" height="64"
                             class="rounded-circle border d-none"
                             style="object-fit:cover;background:var(--surface-muted)">
                        <span id="u-foto-vazia"
                              class="rounded-circle border d-inline-flex align-items-center justify-content-center"
                              style="width:64px;height:64px;background:var(--surface-muted);color:var(--text-secondary)">
                            <i class="fa-solid fa-user"></i>
                        </span>

                        <div class="d-flex flex-column gap-1">
                            <div class="d-flex gap-2">
                                <label class="btn btn-outline-secondary btn-sm mb-0" for="u-foto">
                                    <i class="fa-solid fa-image me-1"></i>Escolher foto
                                </label>
                                <button type="button" class="btn btn-outline-danger btn-sm d-none" id="btn-remover-foto">
                                    <i class="fa-solid fa-trash-can me-1"></i>Remover
                                </button>
                            </div>
                            <?php
                            /*
                             * Avisar da conversao evita o chamado "por que minha imagem
                             * mudou?". Ela e convertida para JPEG e reduzida, entao fundo
                             * transparente vira branco e GIF animado para no primeiro
                             * quadro -- coisas que o usuario nota e nao teria como adivinhar.
                             */
                            ?>
                            <span class="small" style="color:var(--text-secondary)">
                                JPEG, PNG, GIF ou WebP, ate 2 MB. A imagem e convertida para
                                JPEG e reduzida a 512px; fundo transparente fica branco.
                            </span>
                        </div>

                        <input type="file" id="u-foto" class="d-none"
                               accept="image/jpeg,image/png,image/gif,image/webp">
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label" for="u-firstName">Nome</label>
                            <input type="text" class="form-control" id="u-firstName" name="firstName" maxlength="100" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="u-lastName">Sobrenome</label>
                            <input type="text" class="form-control" id="u-lastName" name="lastName" maxlength="100" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="u-email">Email</label>
                            <input type="email" class="form-control" id="u-email" name="email" maxlength="255" required>
                            <?php
                            /*
                             * So editavel na CRIACAO. Na edicao o campo e desabilitado pelo
                             * JavaScript, porque a API nao aceita mais email no PUT: ele
                             * identifica a pessoa no sistema inteiro, e trocar por aqui
                             * mudaria a credencial dela em TODAS as organizacoes de que
                             * participa -- inclusive nas que quem edita nao administra.
                             *
                             * Campo aberto e ignorado em silencio seria pior que campo
                             * fechado com o motivo a vista.
                             */
                            ?>
                            <div class="form-text d-none" id="u-email-travado">
                                O email identifica a pessoa no sistema e nao pode ser alterado.
                            </div>
                        </div>
                        <div class="col-7">
                            <label class="form-label" for="u-phone">Telefone</label>
                            <input type="text" class="form-control" id="u-phone" name="phone" maxlength="20">
                        </div>
                        <div class="col-5 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="u-phoneWhats" name="phoneWhats">
                                <label class="form-check-label small" for="u-phoneWhats">Tem WhatsApp</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="u-status">Situacao</label>
                            <select class="form-select" id="u-status" name="status" data-numero="1">
                                <option value="1">Ativo</option>
                                <option value="2">Inativo</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="u-level">Nivel</label>
                            <input type="number" class="form-control" id="u-level" name="level" value="0" min="0" data-numero="1">
                        </div>
                    </div>

                    <?php
                    /*
                     * NAO ha campo de senha em lugar nenhum deste formulario. A aplicacao
                     * gera uma provisoria que nunca e revelada e dispara o email de
                     * verificacao; o usuario define a dele pelo link.
                     *
                     * NAO ha campo de papel: todo usuario criado pela API nasce Usuario.
                     * Admin e atribuido so pela aplicacao, ao primeiro usuario do tenant.
                     */
                    ?>
                    <hr class="my-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="u-2fa" name="twoFactorEnabled">
                        <label class="form-check-label" for="u-2fa">
                            <span class="fw-medium">Exigir codigo no login</span>
                            <span class="d-block small" style="color:var(--text-secondary)">
                                Alem da senha, um codigo de 6 digitos enviado por email &mdash; e
                                tambem por SMS, se houver celular cadastrado.
                            </span>
                        </label>
                    </div>
                    <?php
                    /*
                     * Sem tratamento de "obrigatorio por papel" aqui.
                     *
                     * A API expoe twoFactorLocked, verdadeiro para Admin, cuja exigencia nao e
                     * desligavel. So que Admin nao aparece na listagem -- a API o exclui --, e
                     * este modal so abre a partir dela. O ramo nunca rodaria, e um ramo morto
                     * seria lido como caminho vivo por quem vier depois.
                     *
                     * Se um dia o Admin passar a ser listado, o campo ja esta na resposta:
                     * basta desabilitar a caixa quando twoFactorLocked vier verdadeiro.
                     */
                    ?>

                    <?php if ($podeConcederMaster) : ?>
                        <hr class="my-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="u-master" name="master">
                            <label class="form-check-label" for="u-master">
                                <span class="fw-medium">Usuario master</span>
                                <span class="d-block small" style="color:var(--text-secondary)">
                                    Acesso livre a tudo, sem perfil vinculado. Quem administra a equipe.
                                </span>
                            </label>
                        </div>
                        <div class="alert alert-warning py-2 small mt-2 mb-0 d-none" id="aviso-master">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i>
                            Master e perfil sao <strong>excludentes</strong>. Este usuario tem perfis
                            vinculados: remova-os antes de marca-lo como master.
                        </div>
                    <?php else : ?>
                        <?php
                        /*
                         * Quem nao e Admin nem master nao concede a marca: a API IGNORA o campo
                         * em silencio, entao a caixa nem aparece. Mostra-la seria exibir um
                         * controle que aceita o clique, diz "salvo" e nao muda nada.
                         */
                        ?>
                    <?php endif; ?>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-salvar">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Perfis do usuario --------------------------------------------------------------- -->
<div class="modal fade" id="modal-perfis" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h6 fw-semibold">Perfis de <span id="perfis-usuario"></span></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="perfis-uuid">

                <?php
                /*
                 * Nao ha aviso de master aqui: o botao que abre este modal nao aparece para
                 * eles. Master e perfil sao excludentes, e a promocao exige que os perfis
                 * ja tenham sido removidos -- entao um master sempre tem zero, e nao ha
                 * conjunto a exibir.
                 */
                ?>
                <div id="perfis-lista">
                    <div class="skeleton mb-2" style="height:2rem"></div>
                    <div class="skeleton" style="height:2rem"></div>
                </div>

                <p class="small mt-3 mb-0" style="color:var(--text-muted)">
                    O envio <strong>substitui</strong> o conjunto inteiro. Desmarcar tudo remove
                    todos os vinculos.
                </p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-salvar-perfis">Salvar perfis</button>
            </div>
        </div>
    </div>
</div>
