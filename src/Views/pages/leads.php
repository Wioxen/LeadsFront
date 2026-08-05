<?php

use App\View;
?>
<div class="d-flex flex-wrap align-items-center gap-3 mb-4">
    <div>
        <h1 class="h4 fw-semibold mb-1">Leads</h1>
        <p class="small mb-0" style="color:var(--text-secondary)">Contatos captados pela organizacao</p>
    </div>

    <button class="btn btn-primary btn-sm ms-auto" id="btn-novo">
        <i class="bi bi-plus-lg me-1"></i>Novo lead
    </button>
</div>

<div class="card">
    <div class="card-body">
        <div id="area-tabela">
            <div class="skeleton mb-2" style="height:2.5rem"></div>
            <div class="skeleton mb-2" style="height:2.5rem"></div>
            <div class="skeleton" style="height:2.5rem"></div>
        </div>

        <table class="table align-middle w-100 d-none" id="tabela-leads">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Cadastrado</th>
                    <th class="text-end">Acoes</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- Formulario no MODAL do Bootstrap. SweetAlert2 e so para decisao (confirmar exclusao):
     validacao de campo com is-invalid/invalid-feedback e do Bootstrap. -->
<div class="modal fade" id="modal-lead" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="form-lead" novalidate>
                <div class="modal-header">
                    <h2 class="modal-title h6 fw-semibold" id="modal-titulo">Novo lead</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="uuid">

                    <div class="mb-3">
                        <label class="form-label" for="lead-name">Nome</label>
                        <?php
                        /*
                         * maxlength espelha o limite da API (50). Replicar aqui faz o erro
                         * aparecer antes do envio, em vez de voltar como 400.
                         */
                        ?>
                        <input type="text" class="form-control" id="lead-name" name="name"
                               maxlength="50" required autocomplete="off">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="lead-email">Email</label>
                        <input type="email" class="form-control" id="lead-email" name="email"
                               maxlength="255" required autocomplete="off">
                        <div class="form-text">Unico dentro da organizacao.</div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-salvar">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>
