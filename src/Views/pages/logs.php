<div class="d-flex flex-wrap align-items-center gap-3 mb-4">
    <div>
        <h1 class="h4 fw-semibold mb-1">Registros de log</h1>
        <p class="small mb-0" style="color:var(--text-secondary)">
            Eventos da aplicacao dentro desta organizacao
        </p>
    </div>
</div>

<?php
/*
 * O aviso precisa estar NA TELA, e nao so na documentacao. A tabela de log exige
 * tenant_id e user_id, entao evento sem usuario resolvido nao chega nela.
 *
 * Sem este texto, alguem procura uma tentativa de login falha, nao encontra, e conclui
 * que o log esta quebrado.
 */
?>
<div class="alert alert-info py-2 small">
    <i class="fa-solid fa-circle-info me-1"></i>
    <strong>Eventos anonimos nao aparecem aqui.</strong>
    Login recusado, recuperacao de senha, verificacao de conta e os jobs de email ficam
    apenas no console do servidor &mdash; nenhum deles tem usuario e organizacao resolvidos
    no momento em que acontece.
</div>

<div class="card">
    <div class="card-body">
        <div id="area-tabela">
            <div class="skeleton mb-2" style="height:2.5rem"></div>
            <div class="skeleton" style="height:2.5rem"></div>
        </div>

        <table class="table align-middle w-100 d-none" id="tabela-logs">
            <thead>
                <tr>
                    <th>Nivel</th>
                    <th>Categoria</th>
                    <th>Mensagem</th>
                    <th>Quando</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>
