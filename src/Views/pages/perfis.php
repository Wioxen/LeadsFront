<div class="d-flex flex-wrap align-items-center gap-3 mb-4">
    <div>
        <h1 class="h4 fw-semibold mb-1">Perfis de acesso</h1>
        <p class="small mb-0" style="color:var(--text-secondary)">
            Conjuntos de permissoes por Controller e Action
        </p>
    </div>

    <button class="btn btn-primary btn-sm ms-auto" id="btn-novo">
        <i class="bi bi-plus-lg me-1"></i>Novo perfil
    </button>
</div>

<div class="alert alert-info py-2 small">
    <i class="bi bi-info-circle me-1"></i>
    Perfis governam apenas o <strong>Usuario comum</strong>. Quem tem papel Admin ou a marca
    master alcanca o sistema inteiro sem depender de vinculo algum.
</div>

<div class="card">
    <div class="card-body">
        <div id="area-tabela">
            <div class="skeleton mb-2" style="height:2.5rem"></div>
            <div class="skeleton" style="height:2.5rem"></div>
        </div>

        <table class="table align-middle w-100 d-none" id="tabela-perfis">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Descricao</th>
                    <th>Permissoes</th>
                    <th>Situacao</th>
                    <th class="text-end">Acoes</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modal-perfil" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form id="form-perfil" novalidate>
                <div class="modal-header">
                    <h2 class="modal-title h6 fw-semibold" id="modal-titulo">Novo perfil</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="uuid">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="p-name">Nome</label>
                            <input type="text" class="form-control" id="p-name" name="name" maxlength="100" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="p-status">Situacao</label>
                            <select class="form-select" id="p-status" name="status" data-numero="1">
                                <option value="1">Ativo</option>
                                <option value="2">Inativo</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="p-description">Descricao</label>
                            <input type="text" class="form-control" id="p-description" name="description" maxlength="256">
                        </div>
                    </div>

                    <label class="form-label">Permissoes</label>

                    <div class="border rounded p-3" style="max-height:360px;overflow-y:auto;border-color:var(--border)!important">
                        <div id="lista-permissoes">
                            <div class="skeleton mb-2" style="height:2rem"></div>
                            <div class="skeleton" style="height:2rem"></div>
                        </div>
                    </div>

                    <p class="small mt-2 mb-0" style="color:var(--text-muted)">
                        O envio <strong>substitui</strong> o conjunto. Permissoes inativas que o perfil
                        ja possuia sao mantidas e reenviadas &mdash; some-las da tela as removeria
                        sem ninguem perceber.
                    </p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-salvar">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>
