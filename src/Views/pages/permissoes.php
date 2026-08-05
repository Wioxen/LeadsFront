<div class="d-flex flex-wrap align-items-center gap-3 mb-4">
    <div>
        <h1 class="h4 fw-semibold mb-1">Permissoes</h1>
        <p class="small mb-0" style="color:var(--text-secondary)">
            Catalogo de Controller e Action, gerado pelo codigo da API
        </p>
    </div>

    <div class="form-check form-switch ms-auto">
        <input class="form-check-input" type="checkbox" id="chk-ocultas" checked>
        <label class="form-check-label small" for="chk-ocultas">Mostrar ocultas</label>
    </div>
</div>

<div class="alert alert-info py-2 small">
    <i class="bi bi-info-circle me-1"></i>
    Nao ha cadastro nem exclusao aqui: o catalogo vem do <strong>codigo</strong> da API. Esta
    tela edita apenas os rotulos amigaveis e a visibilidade &mdash; <code>controller</code>,
    <code>action</code> e <code>isActive</code> sao do servidor.
</div>

<div class="card">
    <div class="card-body">
        <div id="area-tabela">
            <div class="skeleton mb-2" style="height:2.5rem"></div>
            <div class="skeleton" style="height:2.5rem"></div>
        </div>

        <table class="table align-middle w-100 d-none" id="tabela-permissoes">
            <thead>
                <tr>
                    <th>Recurso</th>
                    <th>Acao</th>
                    <th>Controller.Action</th>
                    <th>Situacao</th>
                    <th class="text-end">Acoes</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modal-permissao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="form-permissao" novalidate>
                <div class="modal-header">
                    <h2 class="modal-title h6 fw-semibold">Editar rotulos</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="uuid">

                    <div class="mb-3">
                        <label class="form-label">Identificacao no codigo</label>
                        <input type="text" class="form-control" id="pm-tecnico" disabled>
                        <div class="form-text">Definida pela API. Nao editavel.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="pm-controller">Nome do recurso</label>
                        <input type="text" class="form-control" id="pm-controller"
                               name="controllerDescription" maxlength="150" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="pm-action">Nome da acao</label>
                        <input type="text" class="form-control" id="pm-action"
                               name="actionDescription" maxlength="150" required>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="pm-visivel" name="isVisible">
                        <label class="form-check-label" for="pm-visivel">
                            Visivel na montagem de perfis
                            <span class="d-block small" style="color:var(--text-secondary)">
                                Ocultar nao revoga acesso: apenas tira do seletor.
                            </span>
                        </label>
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
