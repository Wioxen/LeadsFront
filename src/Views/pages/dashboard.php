<?php

use App\View;

/** @var bool $semPermissao @var bool $administra */
$semPermissao = $semPermissao ?? false;
?>

<?php if ($semPermissao) : ?>
    <div class="alert alert-warning py-2 small">
        Voce nao tem permissao para acessar aquela area.
    </div>
<?php endif; ?>

<div class="d-flex flex-wrap align-items-center gap-3 mb-4">
    <div>
        <h1 class="h4 fw-semibold mb-1">Dashboard</h1>
        <p class="small mb-0" style="color:var(--text-secondary)">Visao geral da sua operacao</p>
    </div>

    <div class="ms-auto d-flex gap-2">
        <div class="btn-group btn-group-sm" role="group" id="filtro-periodo">
            <button type="button" class="btn btn-outline-secondary" data-periodo="7">7d</button>
            <button type="button" class="btn btn-outline-secondary active" data-periodo="30">30d</button>
            <button type="button" class="btn btn-outline-secondary" data-periodo="90">90d</button>
        </div>
        <a href="/leads?novo=1" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus me-1"></i>Novo lead
        </a>
    </div>
</div>

<div id="aviso-escala" class="alert alert-warning py-2 small d-none">
    <i class="fa-solid fa-triangle-exclamation me-1"></i>
    <span></span>
</div>

<!-- KPIs ------------------------------------------------------------------------------->
<div class="row g-3 mb-3">

    <div class="col-6 col-md-4 col-xl">
        <div class="card h-100"><div class="card-body">
            <div class="kpi-rotulo">Total de leads</div>
            <div class="kpi-valor" id="kpi-total"><span class="skeleton d-block" style="width:60%;height:2rem"></span></div>
        </div></div>
    </div>

    <div class="col-6 col-md-4 col-xl">
        <div class="card h-100"><div class="card-body">
            <div class="kpi-rotulo">Novos no periodo</div>
            <div class="kpi-valor" id="kpi-novos"><span class="skeleton d-block" style="width:60%;height:2rem"></span></div>
            <div class="kpi-variacao" id="kpi-variacao"></div>
        </div></div>
    </div>

    <?php
    /*
     * Os tres cards abaixo dependem de Lead.status, que NAO existe na API. Ficam visiveis
     * e desabilitados, com o motivo -- e nao preenchidos com zero.
     *
     * Um card "Convertidos: 0" e indistinguivel de "nenhuma conversao ainda", e alguem
     * toma decisao em cima disso. A lacuna explicita tambem serve de pauta para quem
     * prioriza o backend: Lead.status sozinho destrava quatro blocos desta tela.
     */
    $bloqueadosKpi = [
        ['rotulo' => 'Em atendimento', 'motivo' => 'requer Lead.status na API'],
        ['rotulo' => 'Convertidos',    'motivo' => 'requer Lead.status na API'],
        ['rotulo' => 'Perdidos',       'motivo' => 'requer Lead.status na API'],
    ];
    ?>
    <?php foreach ($bloqueadosKpi as $b) : ?>
        <div class="col-6 col-md-4 col-xl">
            <div class="card h-100 bloqueado" data-motivo="<?= View::e($b['motivo']) ?>">
                <div class="card-body pb-5">
                    <div class="kpi-rotulo"><?= View::e($b['rotulo']) ?></div>
                    <div class="kpi-valor" style="color:var(--text-muted)">--</div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Graficos --------------------------------------------------------------------------->
<div class="row g-3 mb-3">

    <div class="col-12 col-xl-6">
        <div class="card h-100">
            <div class="card-header">Leads por mes</div>
            <div class="card-body">
                <div id="grafico-mensal" style="height:280px"></div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="card h-100 bloqueado" data-motivo="requer Lead.source na API">
            <div class="card-header">Leads por origem</div>
            <div class="card-body d-grid" style="height:280px;place-items:center">
                <div class="text-center">
                    <i class="fa-solid fa-chart-pie" style="font-size:2rem;color:var(--text-muted)"></i>
                    <p class="small mt-2 mb-0" style="color:var(--text-secondary)">
                        O lead nao tem campo de origem.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="card h-100 bloqueado" data-motivo="requer etapas em Lead">
            <div class="card-header">Funil de vendas</div>
            <div class="card-body d-grid" style="height:260px;place-items:center">
                <div class="text-center">
                    <i class="fa-solid fa-filter" style="font-size:2rem;color:var(--text-muted)"></i>
                    <p class="small mt-2 mb-0" style="color:var(--text-secondary)">
                        Nao ha etapas cadastradas no lead.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="card h-100 bloqueado" data-motivo="requer Lead.status com data de mudanca">
            <div class="card-header">Conversoes</div>
            <div class="card-body d-grid" style="height:260px;place-items:center">
                <div class="text-center">
                    <i class="fa-solid fa-arrow-trend-up" style="font-size:2rem;color:var(--text-muted)"></i>
                    <p class="small mt-2 mb-0" style="color:var(--text-secondary)">
                        Sem status, nao ha conversao a medir.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Listas ----------------------------------------------------------------------------->
<div class="row g-3">

    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center">
                Ultimos leads
                <a href="/leads" class="ms-auto small text-decoration-none">Ver todos</a>
            </div>
            <div class="card-body p-0">
                <div id="ultimos-leads" class="p-3">
                    <div class="skeleton mb-2" style="height:2.5rem"></div>
                    <div class="skeleton mb-2" style="height:2.5rem"></div>
                    <div class="skeleton" style="height:2.5rem"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <?php if ($administra) : ?>
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center">
                    Atividades recentes
                    <a href="/logs" class="ms-auto small text-decoration-none">Ver log</a>
                </div>
                <div class="card-body p-0">
                    <div id="atividades" class="p-3">
                        <div class="skeleton mb-2" style="height:2rem"></div>
                        <div class="skeleton" style="height:2rem"></div>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <p class="small mb-0" style="color:var(--text-muted)">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Eventos anonimos nao aparecem aqui -- login recusado, recuperacao de
                        senha e jobs ficam so no console do servidor.
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <div class="card bloqueado" data-motivo="requer recurso de tarefas na API">
            <div class="card-header">Agenda e proximas tarefas</div>
            <div class="card-body d-grid" style="height:180px;place-items:center">
                <div class="text-center">
                    <i class="fa-solid fa-calendar-day" style="font-size:2rem;color:var(--text-muted)"></i>
                    <p class="small mt-2 mb-0" style="color:var(--text-secondary)">
                        A API nao tem recurso de tarefas.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
